<?php
/**
 * Intelligent Cache System
 * 
 * @package MoneyQuiz\AI\Cache
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Cache;

/**
 * AI-Powered Smart Caching
 */
class IntelligentCache {
    
    private static $instance = null;
    private $cache_stats = [];
    private $prediction_model;
    
    private function __construct() {
        $this->initializeCacheStats();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize cache statistics
     */
    private function initializeCacheStats() {
        $this->cache_stats = get_option('money_quiz_cache_stats', [
            'hits' => [],
            'misses' => [],
            'patterns' => [],
            'predictions' => []
        ]);
    }
    
    /**
     * Smart get with prediction
     */
    public function get($key, $group = 'default') {
        $full_key = $this->buildKey($key, $group);
        
        // Record access pattern
        $this->recordAccess($full_key);
        
        // Check if we should prefetch related data
        $this->checkPrefetch($key, $group);
        
        // Try to get from cache
        $value = wp_cache_get($key, $group);
        
        if ($value === false) {
            $this->recordMiss($full_key);
            
            // Predict if this will be accessed again
            if ($this->predictFutureAccess($full_key)) {
                $this->scheduleWarmup($key, $group);
            }
        } else {
            $this->recordHit($full_key);
        }
        
        return $value;
    }
    
    /**
     * Smart set with TTL prediction
     */
    public function set($key, $value, $group = 'default', $expire = 0) {
        $full_key = $this->buildKey($key, $group);
        
        // Predict optimal TTL if not specified
        if ($expire === 0) {
            $expire = $this->predictOptimalTTL($full_key, $value);
        }
        
        // Analyze value for compression potential
        $value = $this->optimizeValue($value);
        
        // Set in cache
        $result = wp_cache_set($key, $value, $group, $expire);
        
        // Update statistics
        $this->updateCacheStats($full_key, [
            'size' => $this->getValueSize($value),
            'ttl' => $expire,
            'timestamp' => time()
        ]);
        
        return $result;
    }
    
    /**
     * Delete with cascade prediction
     */
    public function delete($key, $group = 'default') {
        $full_key = $this->buildKey($key, $group);
        
        // Predict related keys that might need deletion
        $related_keys = $this->predictRelatedKeys($full_key);
        
        // Delete main key
        $result = wp_cache_delete($key, $group);
        
        // Consider deleting related keys
        foreach ($related_keys as $related) {
            if ($this->shouldCascadeDelete($full_key, $related)) {
                wp_cache_delete($related['key'], $related['group']);
            }
        }
        
        return $result;
    }
    
    /**
     * Prefetch data based on access patterns
     */
    public function prefetch($keys, $group = 'default') {
        $to_fetch = [];
        
        foreach ($keys as $key) {
            $full_key = $this->buildKey($key, $group);
            
            if ($this->shouldPrefetch($full_key)) {
                $to_fetch[] = $key;
            }
        }
        
        if (!empty($to_fetch)) {
            $this->warmupCache($to_fetch, $group);
        }
    }
    
    /**
     * Record cache access
     */
    private function recordAccess($key) {
        $this->cache_stats['patterns'][$key][] = [
            'timestamp' => time(),
            'memory' => memory_get_usage(),
            'context' => $this->getCurrentContext()
        ];
        
        // Keep only recent patterns
        if (count($this->cache_stats['patterns'][$key]) > 100) {
            array_shift($this->cache_stats['patterns'][$key]);
        }
    }
    
    /**
     * Record cache hit
     */
    private function recordHit($key) {
        if (!isset($this->cache_stats['hits'][$key])) {
            $this->cache_stats['hits'][$key] = 0;
        }
        $this->cache_stats['hits'][$key]++;
        
        $this->updateStats();
    }
    
    /**
     * Record cache miss
     */
    private function recordMiss($key) {
        if (!isset($this->cache_stats['misses'][$key])) {
            $this->cache_stats['misses'][$key] = 0;
        }
        $this->cache_stats['misses'][$key]++;
        
        $this->updateStats();
    }
    
    /**
     * Predict optimal TTL
     */
    private function predictOptimalTTL($key, $value) {
        // Base TTL on access patterns
        $access_frequency = $this->getAccessFrequency($key);
        $value_volatility = $this->calculateVolatility($key);
        $size_factor = $this->getValueSize($value);
        
        // High frequency, low volatility = longer TTL
        if ($access_frequency > 10 && $value_volatility < 0.2) {
            return HOUR_IN_SECONDS;
        }
        
        // Medium frequency = medium TTL
        if ($access_frequency > 5) {
            return 15 * MINUTE_IN_SECONDS;
        }
        
        // Low frequency or high volatility = short TTL
        return 5 * MINUTE_IN_SECONDS;
    }
    
    /**
     * Get access frequency
     */
    private function getAccessFrequency($key) {
        if (!isset($this->cache_stats['patterns'][$key])) {
            return 0;
        }
        
        $patterns = $this->cache_stats['patterns'][$key];
        $recent = array_filter($patterns, function($p) {
            return $p['timestamp'] > (time() - HOUR_IN_SECONDS);
        });
        
        return count($recent);
    }
    
    /**
     * Calculate data volatility
     */
    private function calculateVolatility($key) {
        // Analyze how often the data changes
        if (!isset($this->cache_stats['patterns'][$key])) {
            return 0.5; // Default medium volatility
        }
        
        // Simplified: check cache invalidation frequency
        $invalidations = 0;
        $patterns = $this->cache_stats['patterns'][$key];
        
        for ($i = 1; $i < count($patterns); $i++) {
            if ($patterns[$i]['timestamp'] - $patterns[$i-1]['timestamp'] < 60) {
                $invalidations++;
            }
        }
        
        return $invalidations / max(1, count($patterns));
    }
    
    /**
     * Optimize value for storage
     */
    private function optimizeValue($value) {
        // Skip optimization for small values
        if ($this->getValueSize($value) < 1024) {
            return $value;
        }
        
        // Compress large serialized data
        if (is_array($value) || is_object($value)) {
            $serialized = serialize($value);
            if (strlen($serialized) > 10240) { // 10KB
                return [
                    '_compressed' => true,
                    'data' => gzcompress($serialized)
                ];
            }
        }
        
        return $value;
    }
    
    /**
     * Get value size
     */
    private function getValueSize($value) {
        if (is_string($value)) {
            return strlen($value);
        }
        
        return strlen(serialize($value));
    }
    
    /**
     * Predict future access
     */
    private function predictFutureAccess($key) {
        $patterns = $this->cache_stats['patterns'][$key] ?? [];
        
        if (count($patterns) < 3) {
            return false;
        }
        
        // Check for regular access pattern
        $intervals = [];
        for ($i = 1; $i < count($patterns); $i++) {
            $intervals[] = $patterns[$i]['timestamp'] - $patterns[$i-1]['timestamp'];
        }
        
        // If intervals are consistent, predict future access
        $avg_interval = array_sum($intervals) / count($intervals);
        $variance = $this->calculateVariance($intervals);
        
        return $variance < ($avg_interval * 0.3); // Low variance = predictable
    }
    
    /**
     * Check if prefetch needed
     */
    private function checkPrefetch($key, $group) {
        $associations = $this->getKeyAssociations($key, $group);
        
        foreach ($associations as $assoc) {
            if ($this->shouldPrefetch($assoc['key'])) {
                $this->scheduleWarmup($assoc['key'], $assoc['group']);
            }
        }
    }
    
    /**
     * Get key associations
     */
    private function getKeyAssociations($key, $group) {
        // Analyze access patterns to find keys often accessed together
        $associations = [];
        
        if ($group === 'quiz_data' && strpos($key, 'quiz_') === 0) {
            $quiz_id = str_replace('quiz_', '', $key);
            $associations[] = ['key' => 'questions_' . $quiz_id, 'group' => 'quiz_data'];
            $associations[] = ['key' => 'results_' . $quiz_id, 'group' => 'quiz_data'];
        }
        
        return $associations;
    }
    
    /**
     * Schedule cache warmup
     */
    private function scheduleWarmup($key, $group) {
        $warmup_queue = get_transient('money_quiz_cache_warmup_queue') ?: [];
        
        $warmup_queue[] = [
            'key' => $key,
            'group' => $group,
            'scheduled' => time()
        ];
        
        set_transient('money_quiz_cache_warmup_queue', $warmup_queue, MINUTE_IN_SECONDS);
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('money_quiz_cache_warmup')) {
            wp_schedule_single_event(time() + 10, 'money_quiz_cache_warmup');
        }
    }
    
    /**
     * Helper methods
     */
    private function buildKey($key, $group) {
        return $group . ':' . $key;
    }
    
    private function getCurrentContext() {
        return [
            'page' => $_SERVER['REQUEST_URI'] ?? '',
            'user' => get_current_user_id(),
            'ajax' => wp_doing_ajax()
        ];
    }
    
    private function calculateVariance($values) {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $variance / count($values);
    }
    
    private function updateStats() {
        // Periodically save stats
        if (rand(1, 100) === 1) {
            update_option('money_quiz_cache_stats', $this->cache_stats);
        }
    }
    
    private function shouldPrefetch($key) {
        $frequency = $this->getAccessFrequency($key);
        $hit_rate = $this->getHitRate($key);
        
        return $frequency > 5 && $hit_rate < 0.7;
    }
    
    private function getHitRate($key) {
        $hits = $this->cache_stats['hits'][$key] ?? 0;
        $misses = $this->cache_stats['misses'][$key] ?? 0;
        
        if ($hits + $misses == 0) return 0;
        
        return $hits / ($hits + $misses);
    }
    
    private function predictRelatedKeys($key) {
        // Analyze patterns to find related keys
        return []; // Placeholder
    }
    
    private function shouldCascadeDelete($parent, $related) {
        // Determine if related key should be deleted
        return false; // Placeholder
    }
    
    private function warmupCache($keys, $group) {
        // Implement cache warmup logic
        foreach ($keys as $key) {
            // Load data into cache
            do_action('money_quiz_warmup_cache', $key, $group);
        }
    }
}