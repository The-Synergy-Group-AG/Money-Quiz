<?php
/**
 * Performance Monitor
 *
 * Monitors and logs performance metrics for service methods.
 *
 * @package MoneyQuiz\Core\Performance
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Performance;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Performance monitor class.
 *
 * @since 7.0.0
 */
class PerformanceMonitor {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Performance thresholds in milliseconds.
     *
     * @var array
     */
    private array $thresholds;
    
    /**
     * Active measurements.
     *
     * @var array
     */
    private array $measurements = [];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->load_thresholds();
    }
    
    /**
     * Load performance thresholds from configuration.
     *
     * @return void
     */
    private function load_thresholds(): void {
        // Get thresholds from options with real-world based defaults
        $this->thresholds = [
            'fast' => (int) get_option('money_quiz_perf_threshold_fast', 150),      // < 150ms (adjusted for WordPress)
            'normal' => (int) get_option('money_quiz_perf_threshold_normal', 800),  // < 800ms (adjusted for typical WP)
            'slow' => (int) get_option('money_quiz_perf_threshold_slow', 2000),     // < 2000ms (user patience limit)
            'critical' => (int) get_option('money_quiz_perf_threshold_critical', 5000) // >= 5000ms (timeout territory)
        ];
        
        // Allow filter for dynamic adjustment based on environment
        $this->thresholds = apply_filters('money_quiz_performance_thresholds', $this->thresholds);
    }
    
    /**
     * Start measuring performance.
     *
     * @param string $operation Operation name.
     * @param array  $context   Additional context.
     * @return string Measurement ID.
     */
    public function start(string $operation, array $context = []): string {
        $id = uniqid($operation . '_', true);
        
        $this->measurements[$id] = [
            'operation' => $operation,
            'context' => $context,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
        
        return $id;
    }
    
    /**
     * End measurement and log results.
     *
     * @param string $id     Measurement ID.
     * @param bool   $success Whether operation succeeded.
     * @param array  $data    Additional data to log.
     * @return array Performance metrics.
     */
    public function end(string $id, bool $success = true, array $data = []): array {
        if (!isset($this->measurements[$id])) {
            $this->logger->warning('Performance measurement not found', ['id' => $id]);
            return [];
        }
        
        $measurement = $this->measurements[$id];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        // Calculate metrics
        $duration_ms = ($end_time - $measurement['start_time']) * 1000;
        $memory_used = $end_memory - $measurement['start_memory'];
        
        $metrics = [
            'operation' => $measurement['operation'],
            'duration_ms' => round($duration_ms, 2),
            'memory_bytes' => $memory_used,
            'memory_mb' => round($memory_used / 1024 / 1024, 2),
            'success' => $success,
            'level' => $this->get_performance_level($duration_ms)
        ];
        
        // Merge with context and additional data
        $log_data = array_merge(
            $metrics,
            $measurement['context'],
            $data
        );
        
        // Log based on performance level
        if ($metrics['level'] === 'critical') {
            $this->logger->error('Critical performance issue', $log_data);
        } elseif ($metrics['level'] === 'slow') {
            $this->logger->warning('Slow operation detected', $log_data);
        } else {
            $this->logger->info('Performance metrics', $log_data);
        }
        
        // Clean up measurement
        unset($this->measurements[$id]);
        
        return $metrics;
    }
    
    /**
     * Get performance level based on duration.
     *
     * @param float $duration_ms Duration in milliseconds.
     * @return string Performance level.
     */
    private function get_performance_level(float $duration_ms): string {
        if ($duration_ms < $this->thresholds['fast']) {
            return 'fast';
        } elseif ($duration_ms < $this->thresholds['normal']) {
            return 'normal';
        } elseif ($duration_ms < $this->thresholds['slow']) {
            return 'slow';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Set custom threshold.
     *
     * @param string $level     Performance level.
     * @param int    $threshold Threshold in milliseconds.
     * @return void
     */
    public function set_threshold(string $level, int $threshold): void {
        $this->thresholds[$level] = $threshold;
    }
    
    /**
     * Get current thresholds.
     *
     * @return array Current thresholds.
     */
    public function get_thresholds(): array {
        return $this->thresholds;
    }
}