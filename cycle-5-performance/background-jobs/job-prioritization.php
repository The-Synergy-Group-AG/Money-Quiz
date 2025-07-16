<?php
/**
 * Priority-based Job Scheduling System
 * 
 * Implements intelligent job prioritization with dynamic priority adjustment,
 * deadline-aware scheduling, and resource-based throttling.
 */

namespace MoneyQuiz\Performance\BackgroundJobs;

use SplPriorityQueue;
use DateTime;
use Exception;

class JobPrioritizer {
    private array $priorityRules = [];
    private array $resourceLimits = [];
    private array $jobStats = [];
    private array $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'priority_levels' => [
                'critical' => 1000,
                'high' => 750,
                'normal' => 500,
                'low' => 250,
                'background' => 100
            ],
            'aging_factor' => 10, // Priority boost per minute waiting
            'deadline_boost' => 200, // Priority boost for approaching deadlines
            'resource_weight' => 0.3, // Weight for resource-based priority
            'history_weight' => 0.2 // Weight for historical performance
        ], $config);
        
        $this->initializeDefaultRules();
    }
    
    /**
     * Calculate job priority based on multiple factors
     */
    public function calculatePriority(array $job): int {
        $basePriority = $this->getBasePriority($job);
        $agingBoost = $this->calculateAgingBoost($job);
        $deadlineBoost = $this->calculateDeadlineBoost($job);
        $resourceScore = $this->calculateResourceScore($job);
        $historyScore = $this->calculateHistoryScore($job);
        
        $finalPriority = $basePriority + 
                        $agingBoost + 
                        $deadlineBoost +
                        ($resourceScore * $this->config['resource_weight']) +
                        ($historyScore * $this->config['history_weight']);
        
        // Apply custom rules
        foreach ($this->priorityRules as $rule) {
            if ($rule['condition']($job)) {
                $finalPriority = $rule['modifier']($finalPriority, $job);
            }
        }
        
        return max(0, min(9999, (int)$finalPriority));
    }
    
    /**
     * Get base priority from job type
     */
    private function getBasePriority(array $job): int {
        $type = $job['type'] ?? 'normal';
        $priority = $job['priority'] ?? null;
        
        if (is_numeric($priority)) {
            return (int)$priority;
        }
        
        return $this->config['priority_levels'][$type] ?? 
               $this->config['priority_levels']['normal'];
    }
    
    /**
     * Calculate aging boost (older jobs get higher priority)
     */
    private function calculateAgingBoost(array $job): int {
        if (!isset($job['created_at'])) {
            return 0;
        }
        
        $ageMinutes = (time() - $job['created_at']) / 60;
        return (int)($ageMinutes * $this->config['aging_factor']);
    }
    
    /**
     * Calculate deadline boost
     */
    private function calculateDeadlineBoost(array $job): int {
        if (!isset($job['deadline'])) {
            return 0;
        }
        
        $deadline = $job['deadline'];
        if ($deadline instanceof DateTime) {
            $deadline = $deadline->getTimestamp();
        }
        
        $timeUntilDeadline = $deadline - time();
        
        if ($timeUntilDeadline <= 0) {
            // Past deadline - maximum boost
            return $this->config['deadline_boost'] * 2;
        }
        
        // Linear boost as deadline approaches
        $hoursUntilDeadline = $timeUntilDeadline / 3600;
        if ($hoursUntilDeadline < 24) {
            $boost = $this->config['deadline_boost'] * (1 - ($hoursUntilDeadline / 24));
            return (int)$boost;
        }
        
        return 0;
    }
    
    /**
     * Calculate resource-based priority score
     */
    private function calculateResourceScore(array $job): float {
        $resourceUsage = $this->estimateResourceUsage($job);
        $availableResources = $this->getAvailableResources();
        
        $score = 100;
        
        // Lower priority for resource-intensive jobs when resources are scarce
        if ($availableResources['cpu'] < 30 && $resourceUsage['cpu'] > 50) {
            $score -= 30;
        }
        
        if ($availableResources['memory'] < 20 && $resourceUsage['memory'] > 40) {
            $score -= 40;
        }
        
        // Boost priority for lightweight jobs when resources are scarce
        if ($availableResources['cpu'] < 50 && $resourceUsage['cpu'] < 20) {
            $score += 20;
        }
        
        return $score;
    }
    
    /**
     * Calculate history-based priority score
     */
    private function calculateHistoryScore(array $job): float {
        $jobType = $job['class'] ?? $job['type'] ?? 'unknown';
        
        if (!isset($this->jobStats[$jobType])) {
            return 0;
        }
        
        $stats = $this->jobStats[$jobType];
        $score = 0;
        
        // Boost priority for jobs with good success rate
        if ($stats['success_rate'] > 0.95) {
            $score += 30;
        }
        
        // Lower priority for frequently failing jobs
        if ($stats['failure_rate'] > 0.2) {
            $score -= 50;
        }
        
        // Adjust based on average execution time
        if ($stats['avg_execution_time'] < 5) {
            $score += 20; // Quick jobs
        } elseif ($stats['avg_execution_time'] > 60) {
            $score -= 20; // Long-running jobs
        }
        
        return $score;
    }
    
    /**
     * Add custom priority rule
     */
    public function addPriorityRule(callable $condition, callable $modifier): void {
        $this->priorityRules[] = [
            'condition' => $condition,
            'modifier' => $modifier
        ];
    }
    
    /**
     * Initialize default priority rules
     */
    private function initializeDefaultRules(): void {
        // User-facing jobs get priority boost
        $this->addPriorityRule(
            fn($job) => isset($job['user_facing']) && $job['user_facing'],
            fn($priority, $job) => $priority + 100
        );
        
        // Retry jobs get slight penalty
        $this->addPriorityRule(
            fn($job) => isset($job['attempts']) && $job['attempts'] > 0,
            fn($priority, $job) => $priority - ($job['attempts'] * 10)
        );
        
        // Business hours boost
        $this->addPriorityRule(
            fn($job) => $this->isBusinessHours(),
            fn($priority, $job) => isset($job['business_critical']) && $job['business_critical'] 
                ? $priority + 50 : $priority
        );
    }
    
    /**
     * Estimate resource usage for a job
     */
    private function estimateResourceUsage(array $job): array {
        $jobType = $job['class'] ?? $job['type'] ?? 'unknown';
        
        // Use historical data if available
        if (isset($this->jobStats[$jobType])) {
            return $this->jobStats[$jobType]['avg_resources'] ?? [
                'cpu' => 20,
                'memory' => 30
            ];
        }
        
        // Default estimates based on job type
        $estimates = [
            'email' => ['cpu' => 10, 'memory' => 20],
            'report' => ['cpu' => 40, 'memory' => 60],
            'image_processing' => ['cpu' => 80, 'memory' => 70],
            'data_export' => ['cpu' => 50, 'memory' => 80],
            'api_call' => ['cpu' => 15, 'memory' => 25]
        ];
        
        return $estimates[$jobType] ?? ['cpu' => 20, 'memory' => 30];
    }
    
    /**
     * Get current available resources
     */
    private function getAvailableResources(): array {
        // In real implementation, this would check actual system resources
        $load = sys_getloadavg();
        $memoryUsage = memory_get_usage(true) / memory_get_peak_usage(true) * 100;
        
        return [
            'cpu' => max(0, 100 - ($load[0] * 25)),
            'memory' => max(0, 100 - $memoryUsage)
        ];
    }
    
    /**
     * Check if current time is business hours
     */
    private function isBusinessHours(): bool {
        $hour = (int)date('H');
        $dayOfWeek = (int)date('w');
        
        return $dayOfWeek >= 1 && $dayOfWeek <= 5 && $hour >= 9 && $hour < 18;
    }
    
    /**
     * Update job statistics
     */
    public function updateJobStats(string $jobType, array $stats): void {
        if (!isset($this->jobStats[$jobType])) {
            $this->jobStats[$jobType] = [
                'total_runs' => 0,
                'successful_runs' => 0,
                'failed_runs' => 0,
                'total_execution_time' => 0,
                'avg_execution_time' => 0,
                'success_rate' => 0,
                'failure_rate' => 0,
                'avg_resources' => ['cpu' => 0, 'memory' => 0]
            ];
        }
        
        $current = &$this->jobStats[$jobType];
        
        if (isset($stats['execution_time'])) {
            $current['total_runs']++;
            $current['total_execution_time'] += $stats['execution_time'];
            $current['avg_execution_time'] = $current['total_execution_time'] / $current['total_runs'];
        }
        
        if (isset($stats['success'])) {
            if ($stats['success']) {
                $current['successful_runs']++;
            } else {
                $current['failed_runs']++;
            }
            
            $current['success_rate'] = $current['successful_runs'] / $current['total_runs'];
            $current['failure_rate'] = $current['failed_runs'] / $current['total_runs'];
        }
        
        if (isset($stats['resources'])) {
            // Update rolling average of resource usage
            $weight = 0.1; // Weight for new data
            $current['avg_resources']['cpu'] = 
                $current['avg_resources']['cpu'] * (1 - $weight) + 
                $stats['resources']['cpu'] * $weight;
            $current['avg_resources']['memory'] = 
                $current['avg_resources']['memory'] * (1 - $weight) + 
                $stats['resources']['memory'] * $weight;
        }
    }
}

/**
 * Priority queue implementation for jobs
 */
class PriorityJobQueue extends SplPriorityQueue {
    private JobPrioritizer $prioritizer;
    private array $jobIndex = [];
    
    public function __construct(JobPrioritizer $prioritizer) {
        $this->prioritizer = $prioritizer;
    }
    
    /**
     * Add job to queue
     */
    public function enqueue($job): void {
        $priority = $this->prioritizer->calculatePriority($job);
        $job['calculated_priority'] = $priority;
        
        parent::insert($job, $priority);
        
        if (isset($job['id'])) {
            $this->jobIndex[$job['id']] = $job;
        }
    }
    
    /**
     * Get next job from queue
     */
    public function dequeue() {
        if ($this->isEmpty()) {
            return null;
        }
        
        $job = parent::extract();
        
        if (isset($job['id'])) {
            unset($this->jobIndex[$job['id']]);
        }
        
        return $job;
    }
    
    /**
     * Re-prioritize all jobs in queue
     */
    public function reprioritize(): void {
        $jobs = [];
        
        // Extract all jobs
        while (!$this->isEmpty()) {
            $jobs[] = parent::extract();
        }
        
        // Re-insert with new priorities
        foreach ($jobs as $job) {
            $this->enqueue($job);
        }
    }
    
    /**
     * Get job by ID without removing from queue
     */
    public function getJob(string $jobId): ?array {
        return $this->jobIndex[$jobId] ?? null;
    }
    
    /**
     * Remove specific job from queue
     */
    public function removeJob(string $jobId): bool {
        if (!isset($this->jobIndex[$jobId])) {
            return false;
        }
        
        $jobs = [];
        $removed = false;
        
        // Extract all jobs
        while (!$this->isEmpty()) {
            $job = parent::extract();
            if ($job['id'] === $jobId) {
                $removed = true;
                continue;
            }
            $jobs[] = $job;
        }
        
        // Re-insert remaining jobs
        foreach ($jobs as $job) {
            $this->enqueue($job);
        }
        
        unset($this->jobIndex[$jobId]);
        return $removed;
    }
}

/**
 * Scheduler for managing job execution timing
 */
class JobScheduler {
    private PriorityJobQueue $queue;
    private JobPrioritizer $prioritizer;
    private array $schedule = [];
    private array $runningJobs = [];
    
    public function __construct(JobPrioritizer $prioritizer) {
        $this->prioritizer = $prioritizer;
        $this->queue = new PriorityJobQueue($prioritizer);
    }
    
    /**
     * Schedule a job
     */
    public function schedule(array $job, ?DateTime $runAt = null): string {
        $job['id'] = $job['id'] ?? uniqid('job_', true);
        $job['scheduled_at'] = $runAt ? $runAt->getTimestamp() : time();
        $job['created_at'] = time();
        
        if ($runAt && $runAt->getTimestamp() > time()) {
            // Future job - add to schedule
            $this->schedule[] = $job;
        } else {
            // Ready to run - add to queue
            $this->queue->enqueue($job);
        }
        
        return $job['id'];
    }
    
    /**
     * Get next job to execute
     */
    public function getNextJob(): ?array {
        // Check scheduled jobs
        $this->processScheduledJobs();
        
        // Get highest priority job
        return $this->queue->dequeue();
    }
    
    /**
     * Process scheduled jobs that are ready
     */
    private function processScheduledJobs(): void {
        $now = time();
        $ready = [];
        
        foreach ($this->schedule as $key => $job) {
            if ($job['scheduled_at'] <= $now) {
                $ready[] = $job;
                unset($this->schedule[$key]);
            }
        }
        
        foreach ($ready as $job) {
            $this->queue->enqueue($job);
        }
    }
    
    /**
     * Mark job as running
     */
    public function markRunning(string $jobId): void {
        $this->runningJobs[$jobId] = [
            'started_at' => microtime(true),
            'pid' => getmypid()
        ];
    }
    
    /**
     * Mark job as completed
     */
    public function markCompleted(string $jobId, array $stats = []): void {
        if (isset($this->runningJobs[$jobId])) {
            $runTime = microtime(true) - $this->runningJobs[$jobId]['started_at'];
            unset($this->runningJobs[$jobId]);
            
            // Update job statistics
            $job = $this->queue->getJob($jobId);
            if ($job) {
                $jobType = $job['class'] ?? $job['type'] ?? 'unknown';
                $this->prioritizer->updateJobStats($jobType, array_merge($stats, [
                    'execution_time' => $runTime,
                    'success' => true
                ]));
            }
        }
    }
    
    /**
     * Re-prioritize queue based on current conditions
     */
    public function optimizeQueue(): void {
        $this->queue->reprioritize();
    }
}