<?php
/**
 * Worker Pool Management System
 * 
 * Manages a pool of worker processes for parallel job execution with
 * automatic scaling, health monitoring, and resource optimization.
 */

namespace MoneyQuiz\Performance\BackgroundJobs;

use Psr\Log\LoggerInterface;
use Exception;

class WorkerPoolManager {
    private array $workers = [];
    private array $config;
    private array $metrics;
    private LoggerInterface $logger;
    private $masterSocket;
    private array $workerSockets = [];
    
    public function __construct(array $config = [], LoggerInterface $logger = null) {
        $this->config = array_merge([
            'min_workers' => 2,
            'max_workers' => 10,
            'idle_timeout' => 300, // 5 minutes
            'health_check_interval' => 30,
            'scale_up_threshold' => 0.8, // 80% busy
            'scale_down_threshold' => 0.2, // 20% busy
            'worker_memory_limit' => '256M',
            'worker_time_limit' => 3600, // 1 hour
            'restart_on_memory_limit' => true,
            'socket_path' => '/tmp/worker_pool.sock'
        ], $config);
        
        $this->logger = $logger ?: new \Psr\Log\NullLogger();
        $this->initializeMetrics();
    }
    
    /**
     * Start the worker pool
     */
    public function start(): void {
        $this->logger->info('Starting worker pool manager');
        
        // Create master socket for IPC
        $this->createMasterSocket();
        
        // Start initial workers
        for ($i = 0; $i < $this->config['min_workers']; $i++) {
            $this->spawnWorker();
        }
        
        // Start monitoring loop
        $this->runMonitoringLoop();
    }
    
    /**
     * Spawn a new worker process
     */
    private function spawnWorker(): string {
        $workerId = uniqid('worker_', true);
        
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (!$socketPair) {
            throw new Exception('Failed to create socket pair');
        }
        
        $pid = pcntl_fork();
        
        if ($pid === -1) {
            throw new Exception('Failed to fork worker process');
        } elseif ($pid === 0) {
            // Child process
            fclose($socketPair[0]);
            $this->runWorker($workerId, $socketPair[1]);
            exit(0);
        } else {
            // Parent process
            fclose($socketPair[1]);
            
            $this->workers[$workerId] = [
                'pid' => $pid,
                'started_at' => time(),
                'status' => 'idle',
                'jobs_processed' => 0,
                'last_activity' => time(),
                'memory_usage' => 0,
                'cpu_usage' => 0,
                'socket' => $socketPair[0]
            ];
            
            $this->workerSockets[$workerId] = $socketPair[0];
            
            $this->logger->info("Spawned worker {$workerId} with PID {$pid}");
            
            return $workerId;
        }
    }
    
    /**
     * Run worker process
     */
    private function runWorker(string $workerId, $socket): void {
        // Set resource limits
        ini_set('memory_limit', $this->config['worker_memory_limit']);
        set_time_limit($this->config['worker_time_limit']);
        
        // Set process title
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title("worker_pool:{$workerId}");
        }
        
        $this->logger->info("Worker {$workerId} started");
        
        while (true) {
            // Wait for job from master
            $data = $this->receiveMessage($socket);
            
            if ($data === false || $data['command'] === 'shutdown') {
                break;
            }
            
            if ($data['command'] === 'execute') {
                $this->executeJob($workerId, $data['job'], $socket);
            } elseif ($data['command'] === 'health_check') {
                $this->sendHealthStatus($workerId, $socket);
            }
            
            // Check memory usage
            if ($this->config['restart_on_memory_limit']) {
                $memoryUsage = memory_get_usage(true);
                $memoryLimit = $this->parseMemoryLimit($this->config['worker_memory_limit']);
                
                if ($memoryUsage > $memoryLimit * 0.9) {
                    $this->logger->warning("Worker {$workerId} approaching memory limit");
                    $this->sendMessage($socket, [
                        'type' => 'restart_needed',
                        'reason' => 'memory_limit'
                    ]);
                    break;
                }
            }
        }
        
        fclose($socket);
        $this->logger->info("Worker {$workerId} shutting down");
    }
    
    /**
     * Execute a job in the worker
     */
    private function executeJob(string $workerId, array $job, $socket): void {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            // Send status update
            $this->sendMessage($socket, [
                'type' => 'status',
                'status' => 'processing',
                'job_id' => $job['id']
            ]);
            
            // Execute the job
            $result = $this->processJob($job);
            
            $executionTime = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;
            
            // Send result back to master
            $this->sendMessage($socket, [
                'type' => 'job_complete',
                'job_id' => $job['id'],
                'result' => $result,
                'stats' => [
                    'execution_time' => $executionTime,
                    'memory_used' => $memoryUsed,
                    'success' => true
                ]
            ]);
            
        } catch (Exception $e) {
            $this->sendMessage($socket, [
                'type' => 'job_failed',
                'job_id' => $job['id'],
                'error' => $e->getMessage(),
                'stats' => [
                    'execution_time' => microtime(true) - $startTime,
                    'memory_used' => memory_get_usage(true) - $startMemory,
                    'success' => false
                ]
            ]);
        }
    }
    
    /**
     * Process a job
     */
    private function processJob(array $job): mixed {
        if (isset($job['callable'])) {
            return call_user_func_array($job['callable'], $job['params'] ?? []);
        }
        
        if (isset($job['class']) && isset($job['method'])) {
            $instance = new $job['class']();
            return call_user_func_array([$instance, $job['method']], $job['params'] ?? []);
        }
        
        throw new Exception('Invalid job format');
    }
    
    /**
     * Run the monitoring loop
     */
    private function runMonitoringLoop(): void {
        $lastHealthCheck = time();
        $lastScaleCheck = time();
        
        while (true) {
            // Handle signals
            pcntl_signal_dispatch();
            
            // Check for completed workers
            $this->reapWorkers();
            
            // Health check
            if (time() - $lastHealthCheck >= $this->config['health_check_interval']) {
                $this->performHealthCheck();
                $lastHealthCheck = time();
            }
            
            // Auto-scaling
            if (time() - $lastScaleCheck >= 10) {
                $this->autoScale();
                $lastScaleCheck = time();
            }
            
            // Process worker messages
            $this->processWorkerMessages();
            
            // Small sleep to prevent CPU spinning
            usleep(100000); // 100ms
        }
    }
    
    /**
     * Reap zombie workers
     */
    private function reapWorkers(): void {
        foreach ($this->workers as $workerId => $worker) {
            $status = null;
            $pid = pcntl_waitpid($worker['pid'], $status, WNOHANG);
            
            if ($pid > 0) {
                // Worker has exited
                $this->logger->warning("Worker {$workerId} (PID {$pid}) has exited");
                $this->removeWorker($workerId);
                
                // Spawn replacement if below minimum
                if (count($this->workers) < $this->config['min_workers']) {
                    $this->spawnWorker();
                }
            }
        }
    }
    
    /**
     * Perform health check on all workers
     */
    private function performHealthCheck(): void {
        foreach ($this->workers as $workerId => $worker) {
            $this->sendMessage($worker['socket'], ['command' => 'health_check']);
        }
        
        // Update metrics
        $this->updateMetrics();
    }
    
    /**
     * Auto-scale worker pool based on load
     */
    private function autoScale(): void {
        $totalWorkers = count($this->workers);
        $busyWorkers = count(array_filter($this->workers, fn($w) => $w['status'] === 'busy'));
        
        if ($totalWorkers === 0) {
            return;
        }
        
        $utilizationRate = $busyWorkers / $totalWorkers;
        
        // Scale up
        if ($utilizationRate >= $this->config['scale_up_threshold'] && 
            $totalWorkers < $this->config['max_workers']) {
            
            $newWorkers = min(
                ceil($totalWorkers * 0.5), // 50% increase
                $this->config['max_workers'] - $totalWorkers
            );
            
            for ($i = 0; $i < $newWorkers; $i++) {
                $this->spawnWorker();
            }
            
            $this->logger->info("Scaled up by {$newWorkers} workers");
        }
        
        // Scale down
        if ($utilizationRate <= $this->config['scale_down_threshold'] && 
            $totalWorkers > $this->config['min_workers']) {
            
            $removeWorkers = min(
                ceil($totalWorkers * 0.25), // 25% decrease
                $totalWorkers - $this->config['min_workers']
            );
            
            $idleWorkers = array_filter($this->workers, fn($w) => $w['status'] === 'idle');
            $toRemove = array_slice(array_keys($idleWorkers), 0, $removeWorkers);
            
            foreach ($toRemove as $workerId) {
                $this->shutdownWorker($workerId);
            }
            
            $this->logger->info("Scaled down by {$removeWorkers} workers");
        }
    }
    
    /**
     * Process messages from workers
     */
    private function processWorkerMessages(): void {
        $read = $this->workerSockets;
        $write = null;
        $except = null;
        
        if (empty($read)) {
            return;
        }
        
        $changed = stream_select($read, $write, $except, 0, 100000);
        
        if ($changed === false) {
            return;
        }
        
        foreach ($read as $socket) {
            $workerId = array_search($socket, $this->workerSockets);
            if ($workerId === false) {
                continue;
            }
            
            $message = $this->receiveMessage($socket);
            if ($message === false) {
                continue;
            }
            
            $this->handleWorkerMessage($workerId, $message);
        }
    }
    
    /**
     * Handle message from worker
     */
    private function handleWorkerMessage(string $workerId, array $message): void {
        switch ($message['type']) {
            case 'status':
                $this->workers[$workerId]['status'] = $message['status'];
                $this->workers[$workerId]['last_activity'] = time();
                break;
                
            case 'job_complete':
                $this->workers[$workerId]['status'] = 'idle';
                $this->workers[$workerId]['jobs_processed']++;
                $this->metrics['total_jobs_processed']++;
                
                if (isset($message['stats'])) {
                    $this->updateJobMetrics($message['stats']);
                }
                break;
                
            case 'job_failed':
                $this->workers[$workerId]['status'] = 'idle';
                $this->metrics['total_jobs_failed']++;
                break;
                
            case 'health_status':
                $this->workers[$workerId]['memory_usage'] = $message['memory_usage'];
                $this->workers[$workerId]['cpu_usage'] = $message['cpu_usage'];
                break;
                
            case 'restart_needed':
                $this->logger->info("Worker {$workerId} requested restart: {$message['reason']}");
                $this->restartWorker($workerId);
                break;
        }
    }
    
    /**
     * Dispatch job to available worker
     */
    public function dispatch(array $job): bool {
        $idleWorker = $this->findIdleWorker();
        
        if (!$idleWorker) {
            return false;
        }
        
        $this->sendMessage($this->workers[$idleWorker]['socket'], [
            'command' => 'execute',
            'job' => $job
        ]);
        
        $this->workers[$idleWorker]['status'] = 'busy';
        
        return true;
    }
    
    /**
     * Find an idle worker
     */
    private function findIdleWorker(): ?string {
        foreach ($this->workers as $workerId => $worker) {
            if ($worker['status'] === 'idle') {
                return $workerId;
            }
        }
        
        return null;
    }
    
    /**
     * Shutdown a worker
     */
    private function shutdownWorker(string $workerId): void {
        if (!isset($this->workers[$workerId])) {
            return;
        }
        
        $this->sendMessage($this->workers[$workerId]['socket'], ['command' => 'shutdown']);
        
        // Give worker time to shutdown gracefully
        $timeout = 5;
        $start = time();
        
        while (time() - $start < $timeout) {
            $status = null;
            $pid = pcntl_waitpid($this->workers[$workerId]['pid'], $status, WNOHANG);
            
            if ($pid > 0) {
                break;
            }
            
            usleep(100000);
        }
        
        // Force kill if still running
        if (posix_kill($this->workers[$workerId]['pid'], 0)) {
            posix_kill($this->workers[$workerId]['pid'], SIGKILL);
        }
        
        $this->removeWorker($workerId);
    }
    
    /**
     * Restart a worker
     */
    private function restartWorker(string $workerId): void {
        $this->shutdownWorker($workerId);
        $this->spawnWorker();
    }
    
    /**
     * Remove worker from pool
     */
    private function removeWorker(string $workerId): void {
        if (isset($this->workers[$workerId]['socket'])) {
            fclose($this->workers[$workerId]['socket']);
        }
        
        unset($this->workers[$workerId]);
        unset($this->workerSockets[$workerId]);
    }
    
    /**
     * Send message through socket
     */
    private function sendMessage($socket, array $data): bool {
        $serialized = serialize($data);
        $length = strlen($serialized);
        
        $header = pack('N', $length);
        $written = fwrite($socket, $header . $serialized);
        
        return $written === ($length + 4);
    }
    
    /**
     * Receive message from socket
     */
    private function receiveMessage($socket): mixed {
        $header = fread($socket, 4);
        if (strlen($header) !== 4) {
            return false;
        }
        
        $length = unpack('N', $header)[1];
        $data = fread($socket, $length);
        
        if (strlen($data) !== $length) {
            return false;
        }
        
        return unserialize($data);
    }
    
    /**
     * Create master socket
     */
    private function createMasterSocket(): void {
        if (file_exists($this->config['socket_path'])) {
            unlink($this->config['socket_path']);
        }
        
        $this->masterSocket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_bind($this->masterSocket, $this->config['socket_path']);
        socket_listen($this->masterSocket, 128);
    }
    
    /**
     * Initialize metrics
     */
    private function initializeMetrics(): void {
        $this->metrics = [
            'total_jobs_processed' => 0,
            'total_jobs_failed' => 0,
            'average_execution_time' => 0,
            'average_memory_usage' => 0,
            'worker_utilization' => 0
        ];
    }
    
    /**
     * Update metrics
     */
    private function updateMetrics(): void {
        $totalWorkers = count($this->workers);
        $busyWorkers = count(array_filter($this->workers, fn($w) => $w['status'] === 'busy'));
        
        $this->metrics['worker_utilization'] = $totalWorkers > 0 
            ? $busyWorkers / $totalWorkers 
            : 0;
    }
    
    /**
     * Update job metrics
     */
    private function updateJobMetrics(array $stats): void {
        if (isset($stats['execution_time'])) {
            $total = $this->metrics['total_jobs_processed'];
            $currentAvg = $this->metrics['average_execution_time'];
            
            $this->metrics['average_execution_time'] = 
                (($currentAvg * ($total - 1)) + $stats['execution_time']) / $total;
        }
        
        if (isset($stats['memory_used'])) {
            $total = $this->metrics['total_jobs_processed'];
            $currentAvg = $this->metrics['average_memory_usage'];
            
            $this->metrics['average_memory_usage'] = 
                (($currentAvg * ($total - 1)) + $stats['memory_used']) / $total;
        }
    }
    
    /**
     * Get pool metrics
     */
    public function getMetrics(): array {
        return array_merge($this->metrics, [
            'worker_count' => count($this->workers),
            'idle_workers' => count(array_filter($this->workers, fn($w) => $w['status'] === 'idle')),
            'busy_workers' => count(array_filter($this->workers, fn($w) => $w['status'] === 'busy'))
        ]);
    }
    
    /**
     * Parse memory limit string
     */
    private function parseMemoryLimit(string $limit): int {
        $unit = strtolower(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int)$limit;
        }
    }
    
    /**
     * Send health status
     */
    private function sendHealthStatus(string $workerId, $socket): void {
        $this->sendMessage($socket, [
            'type' => 'health_status',
            'memory_usage' => memory_get_usage(true),
            'cpu_usage' => $this->getProcessCpuUsage()
        ]);
    }
    
    /**
     * Get process CPU usage
     */
    private function getProcessCpuUsage(): float {
        if (!file_exists('/proc/self/stat')) {
            return 0;
        }
        
        $stat = file_get_contents('/proc/self/stat');
        $stats = explode(' ', $stat);
        
        $utime = (int)$stats[13];
        $stime = (int)$stats[14];
        
        return ($utime + $stime) / 100; // Convert to percentage
    }
    
    /**
     * Shutdown the worker pool
     */
    public function shutdown(): void {
        $this->logger->info('Shutting down worker pool');
        
        foreach (array_keys($this->workers) as $workerId) {
            $this->shutdownWorker($workerId);
        }
        
        if ($this->masterSocket) {
            socket_close($this->masterSocket);
        }
        
        if (file_exists($this->config['socket_path'])) {
            unlink($this->config['socket_path']);
        }
    }
}