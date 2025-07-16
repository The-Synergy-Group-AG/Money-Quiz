<?php
/**
 * Smart Caching Loader
 * 
 * @package MoneyQuiz\AI\Cache
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Cache;

require_once __DIR__ . '/cache-1-intelligent-cache.php';
require_once __DIR__ . '/cache-2-predictor.php';

/**
 * Smart Cache Manager
 */
class SmartCacheManager {
    
    private static $instance = null;
    private $cache;
    private $predictor;
    
    private function __construct() {
        $this->cache = IntelligentCache::getInstance();
        $this->predictor = CachePredictor::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function init() {
        $instance = self::getInstance();
        
        // Replace WP cache functions
        add_filter('pre_wp_cache_get', [$instance, 'smartGet'], 10, 3);
        add_filter('pre_wp_cache_set', [$instance, 'smartSet'], 10, 4);
        
        // Warmup cron
        add_action('money_quiz_cache_warmup', [$instance, 'runWarmup']);
        
        // Admin page
        add_action('admin_menu', [$instance, 'addAdminPage']);
        
        // REST endpoint
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
        
        // Insights filter
        add_filter('money_quiz_ai_smart_caching_insights', [$instance, 'getInsights']);
    }
    
    public function smartGet($pre, $key, $group) {
        if ($pre !== false) return $pre;
        
        // Update predictor
        $this->predictor->updatePatterns($group . ':' . $key);
        
        // Get from smart cache
        return $this->cache->get($key, $group);
    }
    
    public function smartSet($pre, $key, $data, $group, $expire) {
        if ($pre !== false) return $pre;
        
        // Set with smart TTL
        return $this->cache->set($key, $data, $group, $expire);
    }
    
    public function runWarmup() {
        $queue = get_transient('money_quiz_cache_warmup_queue') ?: [];
        
        foreach ($queue as $item) {
            // Warmup cache for predicted keys
            do_action('money_quiz_warmup_cache_key', $item['key'], $item['group']);
        }
        
        delete_transient('money_quiz_cache_warmup_queue');
    }
    
    public function addAdminPage() {
        add_submenu_page(
            'money-quiz-ai',
            'Smart Cache',
            'Smart Cache',
            'manage_options',
            'money-quiz-smart-cache',
            [$this, 'renderAdminPage']
        );
    }
    
    public function renderAdminPage() {
        $insights = $this->getInsights();
        ?>
        <div class="wrap">
            <h1>Smart Cache Analytics</h1>
            
            <div class="card">
                <h2>Cache Performance</h2>
                <p>Hit Rate: <?php echo $insights['hit_rate']; ?>%</p>
                <p>Prediction Accuracy: <?php echo $insights['prediction_accuracy']; ?>%</p>
            </div>
            
            <div class="card">
                <h2>Popular Sequences</h2>
                <ul>
                <?php foreach ($insights['popular_sequences'] as $seq => $count): ?>
                    <li><?php echo esc_html($seq); ?> (<?php echo $count; ?>)</li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function registerEndpoints() {
        register_rest_route('money-quiz/v1', '/cache/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getCacheStats'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    public function getCacheStats() {
        return [
            'success' => true,
            'data' => $this->getInsights()
        ];
    }
    
    public function getInsights() {
        $stats = get_option('money_quiz_cache_stats', []);
        $predictor_insights = $this->predictor->getInsights();
        
        $total_hits = array_sum($stats['hits'] ?? []);
        $total_misses = array_sum($stats['misses'] ?? []);
        
        return [
            'hit_rate' => $total_hits + $total_misses > 0 ? 
                round(($total_hits / ($total_hits + $total_misses)) * 100, 1) : 0,
            'total_cached' => count($stats['hits'] ?? []),
            'prediction_accuracy' => $predictor_insights['prediction_accuracy'],
            'popular_sequences' => $predictor_insights['popular_sequences']
        ];
    }
}

// Initialize
add_action('plugins_loaded', [SmartCacheManager::class, 'init']);

// Helper function
if (!function_exists('money_quiz_cache_get')) {
    function money_quiz_cache_get($key, $group = 'default') {
        return SmartCacheManager::getInstance()->cache->get($key, $group);
    }
}