<?php
/**
 * Asynchronous Task Processing System
 * 
 * Implements non-blocking task execution with ReactPHP for high-performance
 * background job processing and concurrent task handling.
 */

namespace MoneyQuiz\Performance\BackgroundJobs;

use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\WritableResourceStream;
use React\ChildProcess\Process;
use Exception;

class AsyncTaskProcessor {
    private LoopInterface $loop;
    private array $tasks = [];
    private array $workers = [];
    private array $config;
    private array $metrics = [
        'tasks_processed' => 0,
        'tasks_failed' => 0,
        'average_processing_time' => 0,
        'current_workers' => 0
    ];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'max_workers' => 10,
            'worker_timeout' => 300,
            'task_timeout' => 60,
            'retry_attempts' => 3,
            'retry_delay' => 5,
            'memory_limit' => '256M',
            'batch_size' => 100
        ], $config);
        
        $this->loop = LoopFactory::create();
    }
    
    /**
     * Add a task to the processing queue
     */
    public function addTask(callable $task, array $params = [], array $options = []): PromiseInterface {
        $taskId = uniqid('task_', true);
        
        $taskData = [
            'id' => $taskId,
            'callable' => $task,
            'params' => $params,
            'options' => array_merge([
                'timeout' => $this->config['task_timeout'],
                'retries' => $this->config['retry_attempts'],
                'priority' => 0
            ], $options),
            'attempts' => 0,
            'created_at' => microtime(true)
        ];
        
        $this->tasks[$taskId] = $taskData;
        
        return new Promise(function ($resolve, $reject) use ($taskId) {
            $this->tasks[$taskId]['promise'] = [
                'resolve' => $resolve,
                'reject' => $reject
            ];
        });
    }
    
    /**
     * Process tasks in batches
     */
    public function processBatch(array $tasks): PromiseInterface {
        $promises = [];
        
        foreach ($tasks as $task) {
            $promises[] = $this->addTask($task['callable'], $task['params'] ?? [], $task['options'] ?? []);
        }
        
        return \React\Promise\all($promises);
    }
    
    /**
     * Start the async task processor
     */
    public function run(): void {
        // Set up periodic task processor
        $this->loop->addPeriodicTimer(0.1, [$this, 'processTasks']);
        
        // Set up worker management
        $this->loop->addPeriodicTimer(1, [$this, 'manageWorkers']);
        
        // Set up metrics collector
        $this->loop->addPeriodicTimer(5, [$this, 'collectMetrics']);
        
        // Run the event loop
        $this->loop->run();
    }
    
    /**
     * Process pending tasks
     */
    public function processTasks(): void {
        // Sort tasks by priority
        uasort($this->tasks, function ($a, $b) {
            return $b['options']['priority'] <=> $a['options']['priority'];
        });
        
        foreach ($this->tasks as $taskId => $task) {
            if ($this->canProcessTask($task)) {
                $this->executeTask($taskId, $task);
            }
        }
    }
    
    /**
     * Execute a single task
     */
    private function executeTask(string $taskId, array $task): void {
        $startTime = microtime(true);
        
        // Create a child process for isolation
        $process = new Process($this->buildWorkerCommand($task));
        $process->start($this->loop);
        
        $output = '';
        $error = '';
        
        $process->stdout->on('data', function ($chunk) use (&$output) {
            $output .= $chunk;
        });
        
        $process->stderr->on('data', function ($chunk) use (&$error) {
            $error .= $chunk;
        });
        
        // Set up timeout
        $timeout = $this->loop->addTimer($task['options']['timeout'], function () use ($process, $taskId) {
            $process->terminate();
            $this->handleTaskFailure($taskId, new Exception('Task timeout'));
        });
        
        $process->on('exit', function ($exitCode) use ($taskId, &$output, &$error, $timeout, $startTime) {
            $this->loop->cancelTimer($timeout);
            
            $processingTime = microtime(true) - $startTime;
            $this->updateMetrics($processingTime);
            
            if ($exitCode === 0) {
                $this->handleTaskSuccess($taskId, unserialize($output));
            } else {
                $this->handleTaskFailure($taskId, new Exception($error ?: 'Task failed'));
            }
        });
        
        $this->workers[$taskId] = $process;
        unset($this->tasks[$taskId]);
    }
    
    /**
     * Handle successful task completion
     */
    private function handleTaskSuccess(string $taskId, $result): void {
        if (isset($this->tasks[$taskId]['promise'])) {
            $this->tasks[$taskId]['promise']['resolve']($result);
        }
        
        unset($this->workers[$taskId]);
        $this->metrics['tasks_processed']++;
    }
    
    /**
     * Handle task failure
     */
    private function handleTaskFailure(string $taskId, Exception $error): void {
        $task = $this->tasks[$taskId] ?? null;
        
        if ($task && $task['attempts'] < $task['options']['retries']) {
            // Retry the task
            $task['attempts']++;
            $this->tasks[$taskId] = $task;
            
            // Schedule retry with delay
            $this->loop->addTimer($this->config['retry_delay'], function () use ($taskId, $task) {
                if (isset($this->tasks[$taskId])) {
                    $this->executeTask($taskId, $task);
                }
            });
        } else {
            // Final failure
            if (isset($task['promise'])) {
                $task['promise']['reject']($error);
            }
            
            unset($this->workers[$taskId]);
            unset($this->tasks[$taskId]);
            $this->metrics['tasks_failed']++;
        }
    }
    
    /**
     * Check if task can be processed
     */
    private function canProcessTask(array $task): bool {
        return count($this->workers) < $this->config['max_workers'] &&
               !isset($this->workers[$task['id']]);
    }
    
    /**
     * Build worker command
     */
    private function buildWorkerCommand(array $task): string {
        $serialized = base64_encode(serialize([
            'callable' => $task['callable'],
            'params' => $task['params']
        ]));
        
        return sprintf(
            'php -d memory_limit=%s -r \'
                $data = unserialize(base64_decode("%s"));
                $result = call_user_func_array($data["callable"], $data["params"]);
                echo serialize($result);
            \'',
            $this->config['memory_limit'],
            $serialized
        );
    }
    
    /**
     * Manage worker pool
     */
    public function manageWorkers(): void {
        // Clean up finished workers
        foreach ($this->workers as $taskId => $process) {
            if (!$process->isRunning()) {
                unset($this->workers[$taskId]);
            }
        }
        
        $this->metrics['current_workers'] = count($this->workers);
    }
    
    /**
     * Update processing metrics
     */
    private function updateMetrics(float $processingTime): void {
        $totalTasks = $this->metrics['tasks_processed'] + 1;
        $currentAverage = $this->metrics['average_processing_time'];
        
        $this->metrics['average_processing_time'] = 
            (($currentAverage * ($totalTasks - 1)) + $processingTime) / $totalTasks;
    }
    
    /**
     * Collect and report metrics
     */
    public function collectMetrics(): void {
        $metrics = array_merge($this->metrics, [
            'pending_tasks' => count($this->tasks),
            'active_workers' => count($this->workers),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
        
        // Log or send metrics to monitoring system
        error_log('AsyncTaskProcessor Metrics: ' . json_encode($metrics));
    }
    
    /**
     * Get current metrics
     */
    public function getMetrics(): array {
        return $this->metrics;
    }
    
    /**
     * Stop the processor
     */
    public function stop(): void {
        // Terminate all workers
        foreach ($this->workers as $process) {
            $process->terminate();
        }
        
        $this->loop->stop();
    }
}

/**
 * Task runner for specific job types
 */
class AsyncTaskRunner {
    private AsyncTaskProcessor $processor;
    
    public function __construct(AsyncTaskProcessor $processor) {
        $this->processor = $processor;
    }
    
    /**
     * Run email sending task
     */
    public function sendEmailAsync(array $emailData): PromiseInterface {
        return $this->processor->addTask(
            [$this, 'sendEmail'],
            [$emailData],
            ['priority' => 5, 'timeout' => 30]
        );
    }
    
    /**
     * Run image processing task
     */
    public function processImageAsync(string $imagePath, array $operations): PromiseInterface {
        return $this->processor->addTask(
            [$this, 'processImage'],
            [$imagePath, $operations],
            ['priority' => 3, 'timeout' => 120, 'memory_limit' => '512M']
        );
    }
    
    /**
     * Run data export task
     */
    public function exportDataAsync(string $format, array $filters): PromiseInterface {
        return $this->processor->addTask(
            [$this, 'exportData'],
            [$format, $filters],
            ['priority' => 2, 'timeout' => 300]
        );
    }
    
    /**
     * Run batch operation
     */
    public function runBatchAsync(array $operations): PromiseInterface {
        $tasks = array_map(function ($op) {
            return [
                'callable' => $op['callable'],
                'params' => $op['params'] ?? [],
                'options' => $op['options'] ?? []
            ];
        }, $operations);
        
        return $this->processor->processBatch($tasks);
    }
    
    // Actual task implementations
    public function sendEmail(array $emailData): bool {
        // Email sending logic
        return true;
    }
    
    public function processImage(string $imagePath, array $operations): array {
        // Image processing logic
        return ['processed' => true, 'path' => $imagePath];
    }
    
    public function exportData(string $format, array $filters): string {
        // Data export logic
        return '/path/to/export.' . $format;
    }
}

/**
 * Async HTTP request handler
 */
class AsyncHttpClient {
    private LoopInterface $loop;
    
    public function __construct(LoopInterface $loop) {
        $this->loop = $loop;
    }
    
    /**
     * Make async HTTP request
     */
    public function request(string $method, string $url, array $options = []): PromiseInterface {
        return new Promise(function ($resolve, $reject) use ($method, $url, $options) {
            $client = new \React\Http\Client($this->loop);
            
            $request = $client->request($method, $url, $options['headers'] ?? []);
            
            if (isset($options['body'])) {
                $request->write($options['body']);
            }
            
            $request->on('response', function ($response) use ($resolve) {
                $body = '';
                
                $response->on('data', function ($chunk) use (&$body) {
                    $body .= $chunk;
                });
                
                $response->on('end', function () use ($resolve, &$body, $response) {
                    $resolve([
                        'status' => $response->getCode(),
                        'headers' => $response->getHeaders(),
                        'body' => $body
                    ]);
                });
            });
            
            $request->on('error', function ($error) use ($reject) {
                $reject($error);
            });
            
            $request->end();
        });
    }
    
    /**
     * Make multiple concurrent requests
     */
    public function requestMultiple(array $requests): PromiseInterface {
        $promises = [];
        
        foreach ($requests as $request) {
            $promises[] = $this->request(
                $request['method'] ?? 'GET',
                $request['url'],
                $request['options'] ?? []
            );
        }
        
        return \React\Promise\all($promises);
    }
}