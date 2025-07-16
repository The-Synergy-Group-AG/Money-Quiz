<?php
/**
 * AI Performance Optimizer
 * 
 * @package MoneyQuiz\AI\Performance
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Performance;

/**
 * Performance Optimization Engine
 */
class AIPerformanceOptimizer {
    
    private static $instance = null;
    private $metrics = [];
    
    private function __construct() {
        $this->loadMetrics();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadMetrics() {
        $this->metrics = get_option('money_quiz_ai_performance', [
            'response_times' => [],
            'memory_usage' => [],
            'query_counts' => []
        ]);
    }
    
    public function startMonitoring($operation) {
        return [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_queries' => get_num_queries()
        ];
    }
    
    public function endMonitoring($context) {
        $metrics = [
            'duration' => microtime(true) - $context['start_time'],
            'memory_delta' => memory_get_usage(true) - $context['start_memory'],
            'query_count' => get_num_queries() - $context['start_queries']
        ];
        
        $this->recordMetrics($context['operation'], $metrics);
        
        return $metrics;
    }
    
    private function recordMetrics($operation, $metrics) {
        if (!isset($this->metrics['response_times'][$operation])) {
            $this->metrics['response_times'][$operation] = [];
        }
        
        $this->metrics['response_times'][$operation][] = $metrics['duration'];
        $this->metrics['memory_usage'][$operation][] = $metrics['memory_delta'];
        $this->metrics['query_counts'][$operation][] = $metrics['query_count'];
        
        // Keep only recent data
        foreach (['response_times', 'memory_usage', 'query_counts'] as $type) {
            if (count($this->metrics[$type][$operation]) > 100) {
                array_shift($this->metrics[$type][$operation]);
            }
        }
        
        if (rand(1, 10) === 1) {
            update_option('money_quiz_ai_performance', $this->metrics);
        }
    }
    
    public function optimize($operation, $callback, $args = []) {
        $context = $this->startMonitoring($operation);
        
        // Check if result is cached
        $cache_key = md5($operation . serialize($args));
        $cached = wp_cache_get($cache_key, 'ai_results');
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Execute with optimization
        $result = $this->executeOptimized($callback, $args);
        
        // Cache result
        $ttl = $this->calculateOptimalTTL($operation);
        wp_cache_set($cache_key, $result, 'ai_results', $ttl);
        
        $this->endMonitoring($context);
        
        return $result;
    }
    
    private function executeOptimized($callback, $args) {
        // Batch database queries if possible
        if ($this->canBatch($callback)) {
            return $this->executeBatched($callback, $args);
        }
        
        // Execute normally
        return call_user_func_array($callback, $args);
    }
    
    private function canBatch($callback) {
        // Check if operation supports batching
        return method_exists($callback[0], 'supportsBatching') && 
               $callback[0]->supportsBatching();
    }
    
    private function executeBatched($callback, $args) {
        // Implementation for batched execution
        return call_user_func_array($callback, $args);
    }
    
    private function calculateOptimalTTL($operation) {
        if (!isset($this->metrics['response_times'][$operation])) {
            return 300; // 5 minutes default
        }
        
        $avg_time = array_sum($this->metrics['response_times'][$operation]) / 
                   count($this->metrics['response_times'][$operation]);
        
        // Longer operations get longer cache
        if ($avg_time > 1.0) return 3600; // 1 hour
        if ($avg_time > 0.5) return 1800; // 30 minutes
        if ($avg_time > 0.1) return 600;  // 10 minutes
        
        return 300; // 5 minutes
    }
    
    public function getPerformanceReport() {
        $report = [];
        
        foreach ($this->metrics['response_times'] as $op => $times) {
            if (empty($times)) continue;
            
            $report[$op] = [
                'avg_time' => array_sum($times) / count($times),
                'max_time' => max($times),
                'min_time' => min($times),
                'avg_memory' => array_sum($this->metrics['memory_usage'][$op]) / 
                               count($this->metrics['memory_usage'][$op]),
                'avg_queries' => array_sum($this->metrics['query_counts'][$op]) / 
                                count($this->metrics['query_counts'][$op])
            ];
        }
        
        return $report;
    }
    
    public function suggestOptimizations() {
        $suggestions = [];
        $report = $this->getPerformanceReport();
        
        foreach ($report as $op => $metrics) {
            if ($metrics['avg_time'] > 0.5) {
                $suggestions[] = "Operation '$op' is slow. Consider caching or optimization.";
            }
            
            if ($metrics['avg_queries'] > 10) {
                $suggestions[] = "Operation '$op' uses many queries. Consider batching.";
            }
            
            if ($metrics['avg_memory'] > 10485760) { // 10MB
                $suggestions[] = "Operation '$op' uses excessive memory.";
            }
        }
        
        return $suggestions;
    }
}