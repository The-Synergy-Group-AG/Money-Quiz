<?php
/**
 * Memory Usage Profiling System
 * 
 * Comprehensive memory profiling tools for identifying memory leaks,
 * tracking allocations, and optimizing memory usage patterns.
 */

namespace MoneyQuiz\Performance\Memory;

use Exception;
use SplObjectStorage;
use WeakMap;

class MemoryProfiler {
    private array $snapshots = [];
    private array $allocations = [];
    private array $objectRegistry;
    private bool $isEnabled = false;
    private array $config;
    private array $hooks = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'track_allocations' => true,
            'track_objects' => true,
            'stack_depth' => 5,
            'threshold_kb' => 100, // Only track allocations > 100KB
            'max_snapshots' => 100,
            'enable_gc_stats' => true,
            'profile_includes' => true
        ], $config);
        
        if (PHP_VERSION_ID >= 80000) {
            $this->objectRegistry = new WeakMap();
        } else {
            $this->objectRegistry = [];
        }
    }
    
    /**
     * Enable profiling
     */
    public function enable(): void {
        if ($this->isEnabled) {
            return;
        }
        
        $this->isEnabled = true;
        
        // Register tick function for allocation tracking
        if ($this->config['track_allocations']) {
            declare(ticks=1);
            register_tick_function([$this, 'tickHandler']);
        }
        
        // Set up memory limit handler
        $this->setupMemoryLimitHandler();
        
        // Enable GC stats collection
        if ($this->config['enable_gc_stats'] && function_exists('gc_enable')) {
            gc_enable();
        }
    }
    
    /**
     * Disable profiling
     */
    public function disable(): void {
        if (!$this->isEnabled) {
            return;
        }
        
        $this->isEnabled = false;
        
        if ($this->config['track_allocations']) {
            unregister_tick_function([$this, 'tickHandler']);
        }
    }
    
    /**
     * Take a memory snapshot
     */
    public function snapshot(string $label = ''): array {
        $snapshot = [
            'label' => $label,
            'timestamp' => microtime(true),
            'memory' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'real' => memory_get_usage(false),
                'real_peak' => memory_get_peak_usage(false)
            ],
            'objects' => $this->getObjectStats(),
            'gc' => $this->getGCStats(),
            'system' => $this->getSystemMemoryInfo()
        ];
        
        // Store snapshot
        $this->snapshots[] = $snapshot;
        
        // Limit snapshot storage
        if (count($this->snapshots) > $this->config['max_snapshots']) {
            array_shift($this->snapshots);
        }
        
        return $snapshot;
    }
    
    /**
     * Compare two snapshots
     */
    public function compareSnapshots(int $index1, int $index2): array {
        if (!isset($this->snapshots[$index1]) || !isset($this->snapshots[$index2])) {
            throw new Exception('Invalid snapshot indices');
        }
        
        $snapshot1 = $this->snapshots[$index1];
        $snapshot2 = $this->snapshots[$index2];
        
        return [
            'time_diff' => $snapshot2['timestamp'] - $snapshot1['timestamp'],
            'memory_diff' => [
                'current' => $snapshot2['memory']['current'] - $snapshot1['memory']['current'],
                'peak' => $snapshot2['memory']['peak'] - $snapshot1['memory']['peak'],
                'real' => $snapshot2['memory']['real'] - $snapshot1['memory']['real']
            ],
            'object_diff' => $this->compareObjectStats(
                $snapshot1['objects'],
                $snapshot2['objects']
            ),
            'gc_diff' => [
                'runs' => ($snapshot2['gc']['runs'] ?? 0) - ($snapshot1['gc']['runs'] ?? 0),
                'collected' => ($snapshot2['gc']['collected'] ?? 0) - ($snapshot1['gc']['collected'] ?? 0)
            ]
        ];
    }
    
    /**
     * Find memory leaks between snapshots
     */
    public function findLeaks(int $startSnapshot = 0, int $endSnapshot = -1): array {
        if ($endSnapshot === -1) {
            $endSnapshot = count($this->snapshots) - 1;
        }
        
        $leaks = [];
        $comparison = $this->compareSnapshots($startSnapshot, $endSnapshot);
        
        // Check for continuous memory growth
        if ($comparison['memory_diff']['real'] > 1024 * 1024) { // 1MB growth
            $leaks['memory_growth'] = [
                'amount' => $comparison['memory_diff']['real'],
                'rate' => $comparison['memory_diff']['real'] / $comparison['time_diff']
            ];
        }
        
        // Check for object leaks
        foreach ($comparison['object_diff'] as $class => $diff) {
            if ($diff['count'] > 10 && $diff['memory'] > 102400) { // 100KB
                $leaks['objects'][$class] = $diff;
            }
        }
        
        // Analyze allocation patterns
        $leaks['suspicious_allocations'] = $this->findSuspiciousAllocations();
        
        return $leaks;
    }
    
    /**
     * Track memory allocation
     */
    public function trackAllocation(string $type, int $size, array $context = []): void {
        if (!$this->isEnabled || $size < $this->config['threshold_kb'] * 1024) {
            return;
        }
        
        $allocation = [
            'type' => $type,
            'size' => $size,
            'timestamp' => microtime(true),
            'backtrace' => $this->getBacktrace(),
            'context' => $context
        ];
        
        $this->allocations[] = $allocation;
        
        // Trigger hooks
        foreach ($this->hooks as $hook) {
            if ($hook['type'] === 'allocation' && $size >= $hook['threshold']) {
                call_user_func($hook['callback'], $allocation);
            }
        }
    }
    
    /**
     * Track object creation
     */
    public function trackObject(object $object): void {
        if (!$this->isEnabled || !$this->config['track_objects']) {
            return;
        }
        
        $class = get_class($object);
        $size = $this->getObjectSize($object);
        
        if (PHP_VERSION_ID >= 80000) {
            $this->objectRegistry[$object] = [
                'class' => $class,
                'size' => $size,
                'created_at' => microtime(true),
                'backtrace' => $this->getBacktrace()
            ];
        } else {
            $hash = spl_object_hash($object);
            $this->objectRegistry[$hash] = [
                'class' => $class,
                'size' => $size,
                'created_at' => microtime(true),
                'backtrace' => $this->getBacktrace()
            ];
        }
        
        $this->trackAllocation('object', $size, ['class' => $class]);
    }
    
    /**
     * Get object statistics
     */
    private function getObjectStats(): array {
        $stats = [];
        
        foreach ($this->objectRegistry as $object => $info) {
            $class = $info['class'];
            
            if (!isset($stats[$class])) {
                $stats[$class] = [
                    'count' => 0,
                    'memory' => 0,
                    'avg_size' => 0
                ];
            }
            
            $stats[$class]['count']++;
            $stats[$class]['memory'] += $info['size'];
        }
        
        foreach ($stats as $class => &$stat) {
            $stat['avg_size'] = $stat['count'] > 0 
                ? $stat['memory'] / $stat['count'] 
                : 0;
        }
        
        return $stats;
    }
    
    /**
     * Compare object statistics
     */
    private function compareObjectStats(array $stats1, array $stats2): array {
        $diff = [];
        $allClasses = array_unique(array_merge(
            array_keys($stats1),
            array_keys($stats2)
        ));
        
        foreach ($allClasses as $class) {
            $count1 = $stats1[$class]['count'] ?? 0;
            $count2 = $stats2[$class]['count'] ?? 0;
            $memory1 = $stats1[$class]['memory'] ?? 0;
            $memory2 = $stats2[$class]['memory'] ?? 0;
            
            if ($count2 !== $count1 || $memory2 !== $memory1) {
                $diff[$class] = [
                    'count' => $count2 - $count1,
                    'memory' => $memory2 - $memory1
                ];
            }
        }
        
        return $diff;
    }
    
    /**
     * Get GC statistics
     */
    private function getGCStats(): array {
        if (!function_exists('gc_status')) {
            return [];
        }
        
        $status = gc_status();
        
        return [
            'runs' => $status['runs'] ?? 0,
            'collected' => $status['collected'] ?? 0,
            'threshold' => $status['threshold'] ?? 0,
            'roots' => $status['roots'] ?? 0
        ];
    }
    
    /**
     * Get system memory information
     */
    private function getSystemMemoryInfo(): array {
        $info = [];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Parse /proc/meminfo
            if (file_exists('/proc/meminfo')) {
                $meminfo = file_get_contents('/proc/meminfo');
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
                $info['total'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
                
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches);
                $info['available'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
            }
            
            // Parse /proc/self/status for process-specific info
            if (file_exists('/proc/self/status')) {
                $status = file_get_contents('/proc/self/status');
                preg_match('/VmRSS:\s+(\d+)/', $status, $matches);
                $info['rss'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
                
                preg_match('/VmPeak:\s+(\d+)/', $status, $matches);
                $info['peak'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
            }
        }
        
        return $info;
    }
    
    /**
     * Find suspicious allocations
     */
    private function findSuspiciousAllocations(): array {
        $suspicious = [];
        
        // Group allocations by backtrace
        $byBacktrace = [];
        foreach ($this->allocations as $allocation) {
            $key = md5(serialize($allocation['backtrace']));
            
            if (!isset($byBacktrace[$key])) {
                $byBacktrace[$key] = [
                    'count' => 0,
                    'total_size' => 0,
                    'backtrace' => $allocation['backtrace'],
                    'type' => $allocation['type']
                ];
            }
            
            $byBacktrace[$key]['count']++;
            $byBacktrace[$key]['total_size'] += $allocation['size'];
        }
        
        // Find patterns
        foreach ($byBacktrace as $key => $group) {
            // High frequency allocations
            if ($group['count'] > 100) {
                $suspicious['high_frequency'][] = [
                    'location' => $this->formatBacktrace($group['backtrace']),
                    'count' => $group['count'],
                    'total_size' => $group['total_size'],
                    'avg_size' => $group['total_size'] / $group['count']
                ];
            }
            
            // Large cumulative allocations
            if ($group['total_size'] > 10 * 1024 * 1024) { // 10MB
                $suspicious['large_cumulative'][] = [
                    'location' => $this->formatBacktrace($group['backtrace']),
                    'count' => $group['count'],
                    'total_size' => $group['total_size']
                ];
            }
        }
        
        return $suspicious;
    }
    
    /**
     * Get simplified backtrace
     */
    private function getBacktrace(): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->config['stack_depth'] + 2);
        
        // Remove profiler frames
        array_shift($trace);
        array_shift($trace);
        
        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null
            ];
        }, $trace);
    }
    
    /**
     * Format backtrace for display
     */
    private function formatBacktrace(array $backtrace): string {
        if (empty($backtrace)) {
            return 'unknown';
        }
        
        $frame = $backtrace[0];
        $location = basename($frame['file']) . ':' . $frame['line'];
        
        if (isset($frame['class'])) {
            $location .= ' ' . $frame['class'] . '::' . $frame['function'];
        } elseif (isset($frame['function'])) {
            $location .= ' ' . $frame['function'];
        }
        
        return $location;
    }
    
    /**
     * Estimate object size
     */
    private function getObjectSize(object $object): int {
        // This is an estimation - PHP doesn't provide exact object sizes
        $size = 0;
        
        // Base object overhead
        $size += 144; // Approximate zval + object overhead
        
        // Properties
        $reflection = new \ReflectionObject($object);
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);
            
            $size += 32; // Property overhead
            $size += $this->getValueSize($value);
        }
        
        return $size;
    }
    
    /**
     * Estimate value size
     */
    private function getValueSize($value): int {
        if (is_null($value)) {
            return 16;
        } elseif (is_bool($value)) {
            return 16;
        } elseif (is_int($value)) {
            return 24;
        } elseif (is_float($value)) {
            return 24;
        } elseif (is_string($value)) {
            return 32 + strlen($value);
        } elseif (is_array($value)) {
            $size = 64; // Array overhead
            foreach ($value as $k => $v) {
                $size += $this->getValueSize($k);
                $size += $this->getValueSize($v);
            }
            return $size;
        } elseif (is_object($value)) {
            return 144; // Just count object reference
        } else {
            return 32; // Default
        }
    }
    
    /**
     * Set up memory limit handler
     */
    private function setupMemoryLimitHandler(): void {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return;
        }
        
        $limitBytes = $this->parseMemoryLimit($memoryLimit);
        $threshold = $limitBytes * 0.9; // 90% threshold
        
        register_tick_function(function () use ($threshold) {
            if (memory_get_usage(true) > $threshold) {
                $this->handleMemoryLimitApproaching();
            }
        });
    }
    
    /**
     * Handle approaching memory limit
     */
    private function handleMemoryLimitApproaching(): void {
        // Take emergency snapshot
        $this->snapshot('MEMORY_LIMIT_WARNING');
        
        // Try to free memory
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Notify hooks
        foreach ($this->hooks as $hook) {
            if ($hook['type'] === 'memory_limit') {
                call_user_func($hook['callback'], [
                    'current' => memory_get_usage(true),
                    'limit' => ini_get('memory_limit'),
                    'peak' => memory_get_peak_usage(true)
                ]);
            }
        }
    }
    
    /**
     * Register hook
     */
    public function registerHook(string $type, callable $callback, array $options = []): void {
        $this->hooks[] = [
            'type' => $type,
            'callback' => $callback,
            'threshold' => $options['threshold'] ?? 0
        ];
    }
    
    /**
     * Generate memory report
     */
    public function generateReport(): array {
        return [
            'summary' => [
                'current_memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'snapshot_count' => count($this->snapshots),
                'tracked_allocations' => count($this->allocations),
                'tracked_objects' => count($this->objectRegistry)
            ],
            'snapshots' => $this->snapshots,
            'leaks' => $this->findLeaks(),
            'top_allocations' => $this->getTopAllocations(),
            'object_summary' => $this->getObjectStats()
        ];
    }
    
    /**
     * Get top memory allocations
     */
    private function getTopAllocations(int $limit = 10): array {
        $sorted = $this->allocations;
        usort($sorted, fn($a, $b) => $b['size'] <=> $a['size']);
        
        return array_slice($sorted, 0, $limit);
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
     * Tick handler for allocation tracking
     */
    public function tickHandler(): void {
        static $lastMemory = 0;
        
        $currentMemory = memory_get_usage(true);
        $diff = $currentMemory - $lastMemory;
        
        if ($diff > $this->config['threshold_kb'] * 1024) {
            $this->trackAllocation('tick', $diff, [
                'memory_before' => $lastMemory,
                'memory_after' => $currentMemory
            ]);
        }
        
        $lastMemory = $currentMemory;
    }
}