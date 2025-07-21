<?php
/**
 * Garbage Collection Optimization System
 * 
 * Optimizes PHP's garbage collection for better performance and
 * reduced memory overhead with intelligent GC scheduling.
 */

namespace MoneyQuiz\Performance\Memory;

use Exception;

class GarbageCollectionOptimizer {
    private array $config;
    private array $metrics = [
        'gc_runs' => 0,
        'gc_collected' => 0,
        'gc_time' => 0,
        'forced_collections' => 0,
        'scheduled_collections' => 0
    ];
    private bool $isEnabled = false;
    private array $thresholds = [];
    private float $lastGCTime = 0;
    private array $gcHistory = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'auto_optimize' => true,
            'min_interval' => 30, // Minimum seconds between GC runs
            'memory_threshold' => 0.8, // 80% of memory limit
            'roots_threshold' => 10000,
            'enable_monitoring' => true,
            'adaptive_scheduling' => true,
            'profile_gc' => true,
            'max_gc_time' => 0.5, // Maximum seconds for GC
            'history_size' => 100
        ], $config);
        
        if (!function_exists('gc_status')) {
            throw new Exception('GC optimization requires PHP 7.3 or higher');
        }
        
        $this->initializeThresholds();
    }
    
    /**
     * Enable GC optimization
     */
    public function enable(): void {
        if ($this->isEnabled) {
            return;
        }
        
        $this->isEnabled = true;
        
        // Store original GC settings
        $this->originalSettings = [
            'gc_enabled' => gc_enabled(),
            'gc_collect_cycles' => ini_get('zend.enable_gc')
        ];
        
        // Enable GC
        gc_enable();
        
        if ($this->config['auto_optimize']) {
            $this->optimizeGCSettings();
        }
        
        // Set up monitoring
        if ($this->config['enable_monitoring']) {
            $this->startMonitoring();
        }
    }
    
    /**
     * Disable GC optimization
     */
    public function disable(): void {
        if (!$this->isEnabled) {
            return;
        }
        
        $this->isEnabled = false;
        
        // Restore original settings
        if (isset($this->originalSettings)) {
            if (!$this->originalSettings['gc_enabled']) {
                gc_disable();
            }
        }
    }
    
    /**
     * Optimize GC settings based on application profile
     */
    public function optimizeGCSettings(): void {
        $status = gc_status();
        
        // Adjust thresholds based on current state
        if ($this->config['adaptive_scheduling']) {
            $this->adaptiveThresholdAdjustment($status);
        }
        
        // Set GC probability based on memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryRatio = $memoryUsage / $memoryLimit;
        
        if ($memoryRatio > 0.7) {
            // High memory usage - more aggressive GC
            ini_set('zend.gc_probability', 100);
            ini_set('zend.gc_divisor', 100);
        } elseif ($memoryRatio > 0.5) {
            // Medium memory usage
            ini_set('zend.gc_probability', 10);
            ini_set('zend.gc_divisor', 100);
        } else {
            // Low memory usage - less frequent GC
            ini_set('zend.gc_probability', 1);
            ini_set('zend.gc_divisor', 1000);
        }
    }
    
    /**
     * Check if garbage collection should run
     */
    public function shouldCollect(): bool {
        if (!$this->isEnabled) {
            return false;
        }
        
        // Check minimum interval
        if (microtime(true) - $this->lastGCTime < $this->config['min_interval']) {
            return false;
        }
        
        $status = gc_status();
        
        // Check roots threshold
        if ($status['roots'] >= $this->thresholds['roots']) {
            return true;
        }
        
        // Check memory threshold
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        if ($memoryUsage / $memoryLimit >= $this->config['memory_threshold']) {
            return true;
        }
        
        // Check buffer threshold
        if (isset($status['buffer_size']) && $status['buffer_size'] >= $this->thresholds['buffer_size']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Perform optimized garbage collection
     */
    public function collect(bool $force = false): array {
        if (!$force && !$this->shouldCollect()) {
            return ['collected' => 0, 'duration' => 0, 'skipped' => true];
        }
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $statusBefore = gc_status();
        
        // Perform collection
        $collected = gc_collect_cycles();
        
        $duration = microtime(true) - $startTime;
        $memoryFreed = $startMemory - memory_get_usage(true);
        $statusAfter = gc_status();
        
        // Update metrics
        $this->metrics['gc_runs']++;
        $this->metrics['gc_collected'] += $collected;
        $this->metrics['gc_time'] += $duration;
        
        if ($force) {
            $this->metrics['forced_collections']++;
        } else {
            $this->metrics['scheduled_collections']++;
        }
        
        $this->lastGCTime = microtime(true);
        
        // Record in history
        $this->recordGCRun([
            'timestamp' => $this->lastGCTime,
            'collected' => $collected,
            'duration' => $duration,
            'memory_freed' => $memoryFreed,
            'roots_before' => $statusBefore['roots'],
            'roots_after' => $statusAfter['roots'],
            'forced' => $force
        ]);
        
        // Analyze performance
        if ($this->config['profile_gc']) {
            $this->analyzeGCPerformance($duration, $collected, $memoryFreed);
        }
        
        return [
            'collected' => $collected,
            'duration' => $duration,
            'memory_freed' => $memoryFreed,
            'efficiency' => $collected > 0 ? $duration / $collected : 0
        ];
    }
    
    /**
     * Schedule garbage collection at optimal times
     */
    public function scheduleCollection(callable $callback = null): void {
        if (!$this->isEnabled) {
            return;
        }
        
        // Register tick function for periodic checks
        declare(ticks=1000);
        register_tick_function(function () use ($callback) {
            if ($this->shouldCollect()) {
                $result = $this->collect();
                
                if ($callback) {
                    $callback($result);
                }
            }
        });
    }
    
    /**
     * Analyze GC performance and adjust thresholds
     */
    private function analyzeGCPerformance(float $duration, int $collected, int $memoryFreed): void {
        // Check if GC is taking too long
        if ($duration > $this->config['max_gc_time']) {
            // Increase thresholds to reduce frequency
            $this->thresholds['roots'] = (int)($this->thresholds['roots'] * 1.2);
            $this->thresholds['buffer_size'] = (int)($this->thresholds['buffer_size'] * 1.2);
        }
        
        // Check efficiency
        $efficiency = $collected > 0 ? $duration / $collected : PHP_FLOAT_MAX;
        
        if ($efficiency > 0.001) { // More than 1ms per object
            // GC is inefficient, adjust thresholds
            $this->adjustForInefficiency();
        }
        
        // Check memory freed
        if ($memoryFreed < 1024 * 1024 && $collected > 100) { // Less than 1MB freed
            // Small objects, might want to batch more
            $this->thresholds['roots'] = (int)($this->thresholds['roots'] * 1.5);
        }
    }
    
    /**
     * Adaptive threshold adjustment based on patterns
     */
    private function adaptiveThresholdAdjustment(array $status): void {
        if (count($this->gcHistory) < 10) {
            return; // Not enough data
        }
        
        // Analyze recent GC patterns
        $recentRuns = array_slice($this->gcHistory, -10);
        $avgInterval = $this->calculateAverageInterval($recentRuns);
        $avgCollected = array_sum(array_column($recentRuns, 'collected')) / count($recentRuns);
        $avgDuration = array_sum(array_column($recentRuns, 'duration')) / count($recentRuns);
        
        // Adjust based on patterns
        if ($avgInterval < 60 && $avgCollected < 100) {
            // Too frequent with low yield
            $this->thresholds['roots'] *= 1.5;
        } elseif ($avgInterval > 300 && $avgDuration > 0.5) {
            // Too infrequent causing long pauses
            $this->thresholds['roots'] *= 0.8;
        }
        
        // Memory pressure adjustment
        $memoryPressure = memory_get_usage(true) / $this->getMemoryLimit();
        if ($memoryPressure > 0.9) {
            // High memory pressure - be more aggressive
            $this->thresholds['roots'] = (int)($this->thresholds['roots'] * 0.5);
        }
    }
    
    /**
     * Initialize thresholds
     */
    private function initializeThresholds(): void {
        $this->thresholds = [
            'roots' => $this->config['roots_threshold'],
            'buffer_size' => 1000,
            'memory_ratio' => $this->config['memory_threshold']
        ];
    }
    
    /**
     * Adjust thresholds for inefficiency
     */
    private function adjustForInefficiency(): void {
        // Reduce GC frequency when it's inefficient
        $this->thresholds['roots'] = min(
            $this->thresholds['roots'] * 1.3,
            50000 // Maximum threshold
        );
        
        $this->config['min_interval'] = min(
            $this->config['min_interval'] * 1.2,
            300 // Maximum 5 minutes
        );
    }
    
    /**
     * Record GC run in history
     */
    private function recordGCRun(array $data): void {
        $this->gcHistory[] = $data;
        
        // Limit history size
        if (count($this->gcHistory) > $this->config['history_size']) {
            array_shift($this->gcHistory);
        }
    }
    
    /**
     * Calculate average interval between GC runs
     */
    private function calculateAverageInterval(array $runs): float {
        if (count($runs) < 2) {
            return 0;
        }
        
        $intervals = [];
        for ($i = 1; $i < count($runs); $i++) {
            $intervals[] = $runs[$i]['timestamp'] - $runs[$i-1]['timestamp'];
        }
        
        return array_sum($intervals) / count($intervals);
    }
    
    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit(): int {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
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
     * Start monitoring
     */
    private function startMonitoring(): void {
        // Monitor memory usage patterns
        register_tick_function(function () {
            static $lastCheck = 0;
            
            if (time() - $lastCheck < 10) {
                return; // Check every 10 seconds
            }
            
            $lastCheck = time();
            $this->checkMemoryPatterns();
        });
    }
    
    /**
     * Check memory usage patterns
     */
    private function checkMemoryPatterns(): void {
        static $memoryHistory = [];
        
        $current = memory_get_usage(true);
        $memoryHistory[] = [
            'time' => microtime(true),
            'memory' => $current
        ];
        
        // Keep last 5 minutes of data
        $cutoff = microtime(true) - 300;
        $memoryHistory = array_filter($memoryHistory, fn($item) => $item['time'] > $cutoff);
        
        if (count($memoryHistory) < 10) {
            return;
        }
        
        // Detect memory leak patterns
        $trend = $this->calculateMemoryTrend($memoryHistory);
        
        if ($trend > 1024 * 1024) { // Growing by more than 1MB/minute
            // Potential memory leak - increase GC frequency
            $this->thresholds['roots'] = (int)($this->thresholds['roots'] * 0.7);
            $this->config['min_interval'] = max(10, $this->config['min_interval'] * 0.8);
        }
    }
    
    /**
     * Calculate memory usage trend
     */
    private function calculateMemoryTrend(array $history): float {
        if (count($history) < 2) {
            return 0;
        }
        
        // Simple linear regression
        $n = count($history);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        $startTime = $history[0]['time'];
        
        foreach ($history as $point) {
            $x = $point['time'] - $startTime;
            $y = $point['memory'];
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        return $slope * 60; // Convert to bytes per minute
    }
    
    /**
     * Get optimization report
     */
    public function getReport(): array {
        $status = gc_status();
        
        return [
            'metrics' => $this->metrics,
            'current_status' => $status,
            'thresholds' => $this->thresholds,
            'gc_efficiency' => $this->metrics['gc_runs'] > 0 
                ? $this->metrics['gc_collected'] / $this->metrics['gc_runs'] 
                : 0,
            'avg_gc_time' => $this->metrics['gc_runs'] > 0 
                ? $this->metrics['gc_time'] / $this->metrics['gc_runs'] 
                : 0,
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->getMemoryLimit()
            ],
            'recent_history' => array_slice($this->gcHistory, -10)
        ];
    }
    
    /**
     * Force immediate garbage collection
     */
    public function forceCollect(): array {
        return $this->collect(true);
    }
    
    /**
     * Optimize for specific workload type
     */
    public function optimizeFor(string $workloadType): void {
        switch ($workloadType) {
            case 'web_request':
                // Quick, frequent GC for short-lived requests
                $this->thresholds['roots'] = 1000;
                $this->config['min_interval'] = 5;
                break;
                
            case 'batch_processing':
                // Less frequent but thorough GC
                $this->thresholds['roots'] = 10000;
                $this->config['min_interval'] = 60;
                break;
                
            case 'long_running':
                // Adaptive GC for daemons
                $this->config['adaptive_scheduling'] = true;
                $this->thresholds['roots'] = 5000;
                break;
                
            case 'memory_intensive':
                // Aggressive GC for high memory apps
                $this->thresholds['roots'] = 500;
                $this->config['min_interval'] = 10;
                $this->config['memory_threshold'] = 0.6;
                break;
        }
    }
}

/**
 * Reference manager for preventing circular references
 */
class ReferenceManager {
    private array $references = [];
    private array $circularRefs = [];
    
    /**
     * Track object reference
     */
    public function track(object $object, string $property, $value): void {
        $objectHash = spl_object_hash($object);
        
        if (!isset($this->references[$objectHash])) {
            $this->references[$objectHash] = [];
        }
        
        if (is_object($value)) {
            $valueHash = spl_object_hash($value);
            $this->references[$objectHash][$property] = $valueHash;
            
            // Check for circular reference
            if ($this->hasCircularReference($objectHash, $valueHash)) {
                $this->circularRefs[] = [
                    'object' => get_class($object),
                    'property' => $property,
                    'target' => get_class($value)
                ];
            }
        }
    }
    
    /**
     * Check for circular reference
     */
    private function hasCircularReference(string $from, string $to, array $visited = []): bool {
        if (in_array($from, $visited)) {
            return true;
        }
        
        $visited[] = $from;
        
        if (!isset($this->references[$to])) {
            return false;
        }
        
        foreach ($this->references[$to] as $property => $ref) {
            if ($ref === $from) {
                return true;
            }
            
            if ($this->hasCircularReference($ref, $from, $visited)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get circular references
     */
    public function getCircularReferences(): array {
        return $this->circularRefs;
    }
    
    /**
     * Clear reference for object
     */
    public function clear(object $object): void {
        $hash = spl_object_hash($object);
        unset($this->references[$hash]);
    }
    
    /**
     * Clear all references
     */
    public function clearAll(): void {
        $this->references = [];
        $this->circularRefs = [];
    }
}