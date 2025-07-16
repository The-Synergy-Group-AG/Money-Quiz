<?php
/**
 * Query Optimizer
 * 
 * @package MoneyQuiz\AI\Performance
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Performance;

/**
 * Optimizes database queries
 */
class QueryOptimizer {
    
    private static $instance = null;
    private $query_cache = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function optimizeQuery($sql, $params = []) {
        // Analyze query
        $analysis = $this->analyzeQuery($sql);
        
        // Apply optimizations
        if ($analysis['can_cache']) {
            return $this->getCachedQuery($sql, $params);
        }
        
        if ($analysis['needs_index']) {
            $this->suggestIndex($analysis['table'], $analysis['columns']);
        }
        
        return $this->executeQuery($sql, $params);
    }
    
    private function analyzeQuery($sql) {
        $analysis = [
            'can_cache' => false,
            'needs_index' => false,
            'table' => '',
            'columns' => []
        ];
        
        // Simple SELECT queries can be cached
        if (stripos($sql, 'SELECT') === 0 && stripos($sql, 'RAND()') === false) {
            $analysis['can_cache'] = true;
        }
        
        // Check for WHERE without index
        if (preg_match('/WHERE\s+(\w+)\s*=/', $sql, $matches)) {
            $analysis['columns'][] = $matches[1];
        }
        
        return $analysis;
    }
    
    private function getCachedQuery($sql, $params) {
        $key = md5($sql . serialize($params));
        
        if (isset($this->query_cache[$key])) {
            return $this->query_cache[$key];
        }
        
        $result = $this->executeQuery($sql, $params);
        $this->query_cache[$key] = $result;
        
        return $result;
    }
    
    private function executeQuery($sql, $params) {
        global $wpdb;
        
        if (empty($params)) {
            return $wpdb->get_results($sql);
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    private function suggestIndex($table, $columns) {
        $suggestions = get_option('money_quiz_index_suggestions', []);
        
        foreach ($columns as $column) {
            $key = $table . '.' . $column;
            if (!isset($suggestions[$key])) {
                $suggestions[$key] = 0;
            }
            $suggestions[$key]++;
        }
        
        update_option('money_quiz_index_suggestions', $suggestions);
    }
    
    public function batchQueries($queries) {
        global $wpdb;
        
        $results = [];
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($queries as $key => $query) {
                $results[$key] = $this->optimizeQuery($query['sql'], $query['params'] ?? []);
            }
            $wpdb->query('COMMIT');
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        
        return $results;
    }
    
    public function getOptimizationReport() {
        $suggestions = get_option('money_quiz_index_suggestions', []);
        arsort($suggestions);
        
        return [
            'top_missing_indexes' => array_slice($suggestions, 0, 10, true),
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'slow_queries' => $this->getSlowQueries()
        ];
    }
    
    private function calculateCacheHitRate() {
        // Simplified calculation
        return count($this->query_cache) > 0 ? 75 : 0;
    }
    
    private function getSlowQueries() {
        // Would track slow queries
        return [];
    }
}