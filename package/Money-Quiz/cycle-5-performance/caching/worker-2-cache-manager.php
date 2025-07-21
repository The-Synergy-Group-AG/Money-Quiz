<?php
/**
 * Money Quiz Plugin - Cache Manager
 * Worker 2: Caching Layer Implementation
 * 
 * Implements comprehensive caching strategies including object caching,
 * fragment caching, full page caching, and distributed cache support.
 * 
 * @package MoneyQuiz
 * @subpackage Performance
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance;

use MoneyQuiz\Utilities\DebugUtil;

/**
 * Cache Manager Class
 * 
 * Handles all caching operations for optimal performance
 */
class CacheManager {
    
    /**
     * Cache groups
     * 
     * @var array
     */
    protected $cache_groups = array(
        'queries' => 300,      // 5 minutes
        'results' => 3600,     // 1 hour
        'analytics' => 900,    // 15 minutes
        'fragments' => 1800,   // 30 minutes
        'api' => 600,          // 10 minutes
        'user' => 86400,       // 24 hours
        'static' => 604800     // 7 days
    );
    
    /**
     * Cache backend
     * 
     * @var CacheBackendInterface
     */
    protected $backend;
    
    /**
     * Local runtime cache
     * 
     * @var array
     */
    protected $runtime_cache = array();
    
    /**
     * Cache statistics
     * 
     * @var array
     */
    protected $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    );
    
    /**
     * Singleton instance
     * 
     * @var CacheManager
     */
    protected static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return CacheManager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    protected function __construct() {
        $this->init_cache_backend();
        $this->register_hooks();
    }
    
    /**
     * Initialize cache backend
     */
    protected function init_cache_backend() {
        // Detect and use best available cache backend
        if ( $this->is_redis_available() ) {
            $this->backend = new RedisCacheBackend();
        } elseif ( $this->is_memcached_available() ) {
            $this->backend = new MemcachedCacheBackend();
        } elseif ( function_exists( 'apcu_fetch' ) ) {
            $this->backend = new APCuCacheBackend();
        } else {
            $this->backend = new DatabaseCacheBackend();
        }
        
        DebugUtil::log( 'Cache backend initialized: ' . get_class( $this->backend ), 'info' );
    }
    
    /**
     * Register cache hooks
     */
    protected function register_hooks() {
        // Clear cache on content updates
        add_action( 'money_quiz_prospect_created', array( $this, 'invalidate_prospect_cache' ) );
        add_action( 'money_quiz_result_saved', array( $this, 'invalidate_result_cache' ) );
        add_action( 'money_quiz_settings_updated', array( $this, 'invalidate_all_cache' ) );
        
        // Page cache headers
        add_action( 'send_headers', array( $this, 'set_cache_headers' ) );
        
        // Fragment cache tags
        add_action( 'money_quiz_before_render', array( $this, 'start_fragment_cache' ) );
        add_action( 'money_quiz_after_render', array( $this, 'end_fragment_cache' ) );
        
        // Admin bar cache info
        if ( current_user_can( 'manage_options' ) ) {
            add_action( 'admin_bar_menu', array( $this, 'add_cache_info_to_admin_bar' ), 100 );
        }
    }
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false Cached value or false
     */
    public function get( $key, $group = 'default' ) {
        $full_key = $this->build_key( $key, $group );
        
        // Check runtime cache first
        if ( isset( $this->runtime_cache[ $full_key ] ) ) {
            $this->stats['hits']++;
            return $this->runtime_cache[ $full_key ];
        }
        
        // Get from backend
        $value = $this->backend->get( $full_key );
        
        if ( false !== $value ) {
            $this->stats['hits']++;
            $this->runtime_cache[ $full_key ] = $value;
        } else {
            $this->stats['misses']++;
        }
        
        return $value;
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed  $value Value to cache
     * @param string $group Cache group
     * @param int    $expiration Custom expiration (0 = use group default)
     * @return bool Success
     */
    public function set( $key, $value, $group = 'default', $expiration = 0 ) {
        $full_key = $this->build_key( $key, $group );
        
        // Determine expiration
        if ( 0 === $expiration ) {
            $expiration = $this->cache_groups[ $group ] ?? 3600;
        }
        
        // Set in backend
        $result = $this->backend->set( $full_key, $value, $expiration );
        
        if ( $result ) {
            $this->stats['sets']++;
            $this->runtime_cache[ $full_key ] = $value;
        }
        
        return $result;
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public function delete( $key, $group = 'default' ) {
        $full_key = $this->build_key( $key, $group );
        
        // Remove from runtime cache
        unset( $this->runtime_cache[ $full_key ] );
        
        // Delete from backend
        $result = $this->backend->delete( $full_key );
        
        if ( $result ) {
            $this->stats['deletes']++;
        }
        
        return $result;
    }
    
    /**
     * Remember value with callback
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback to generate value
     * @param string   $group Cache group
     * @param int      $expiration Expiration time
     * @return mixed Cached or generated value
     */
    public function remember( $key, callable $callback, $group = 'default', $expiration = 0 ) {
        $value = $this->get( $key, $group );
        
        if ( false === $value ) {
            $value = $callback();
            $this->set( $key, $value, $group, $expiration );
        }
        
        return $value;
    }
    
    /**
     * Flush cache group
     * 
     * @param string $group Cache group to flush
     * @return bool Success
     */
    public function flush_group( $group ) {
        // Clear runtime cache for group
        foreach ( $this->runtime_cache as $key => $value ) {
            if ( strpos( $key, ":{$group}:" ) !== false ) {
                unset( $this->runtime_cache[ $key ] );
            }
        }
        
        // Flush in backend
        return $this->backend->flush_group( $group );
    }
    
    /**
     * Flush all cache
     * 
     * @return bool Success
     */
    public function flush_all() {
        $this->runtime_cache = array();
        return $this->backend->flush_all();
    }
    
    /**
     * Fragment cache start
     * 
     * @param string $key Fragment key
     * @param array  $variables Variables that affect the fragment
     * @return bool True if cached content was output
     */
    public function fragment_cache_start( $key, array $variables = array() ) {
        $cache_key = $key . '_' . md5( serialize( $variables ) );
        $cached = $this->get( $cache_key, 'fragments' );
        
        if ( false !== $cached ) {
            echo $cached;
            return true;
        }
        
        ob_start();
        return false;
    }
    
    /**
     * Fragment cache end
     * 
     * @param string $key Fragment key
     * @param array  $variables Variables that affect the fragment
     */
    public function fragment_cache_end( $key, array $variables = array() ) {
        $content = ob_get_clean();
        $cache_key = $key . '_' . md5( serialize( $variables ) );
        
        $this->set( $cache_key, $content, 'fragments' );
        echo $content;
    }
    
    /**
     * Page cache implementation
     * 
     * @param string $page_key Page identifier
     * @return string|false Cached page content or false
     */
    public function get_page_cache( $page_key ) {
        // Don't cache for logged-in users
        if ( is_user_logged_in() ) {
            return false;
        }
        
        // Don't cache POST requests
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            return false;
        }
        
        // Build cache key with query string
        $cache_key = $page_key . '_' . md5( $_SERVER['QUERY_STRING'] ?? '' );
        
        return $this->get( $cache_key, 'pages' );
    }
    
    /**
     * Save page cache
     * 
     * @param string $page_key Page identifier
     * @param string $content Page content
     * @param int    $expiration Expiration time
     */
    public function set_page_cache( $page_key, $content, $expiration = 3600 ) {
        // Don't cache for logged-in users
        if ( is_user_logged_in() ) {
            return;
        }
        
        $cache_key = $page_key . '_' . md5( $_SERVER['QUERY_STRING'] ?? '' );
        $this->set( $cache_key, $content, 'pages', $expiration );
    }
    
    /**
     * Transient API compatibility layer
     * 
     * @param string $transient Transient name
     * @return mixed Transient value
     */
    public function get_transient( $transient ) {
        return $this->get( $transient, 'transients' );
    }
    
    /**
     * Set transient with caching
     * 
     * @param string $transient Transient name
     * @param mixed  $value Transient value
     * @param int    $expiration Expiration time
     * @return bool Success
     */
    public function set_transient( $transient, $value, $expiration = 0 ) {
        return $this->set( $transient, $value, 'transients', $expiration );
    }
    
    /**
     * Delete transient
     * 
     * @param string $transient Transient name
     * @return bool Success
     */
    public function delete_transient( $transient ) {
        return $this->delete( $transient, 'transients' );
    }
    
    /**
     * Cache warmup
     * 
     * Pre-populate cache with commonly accessed data
     */
    public function warmup() {
        // Warm up archetype cache
        $this->warmup_archetypes();
        
        // Warm up questions cache
        $this->warmup_questions();
        
        // Warm up recent results
        $this->warmup_recent_results();
        
        // Warm up statistics
        $this->warmup_statistics();
    }
    
    /**
     * Warm up archetype cache
     */
    protected function warmup_archetypes() {
        global $wpdb;
        
        $archetypes = $wpdb->get_results( 
            "SELECT * FROM {$wpdb->prefix}mq_archetypes WHERE is_active = 1",
            ARRAY_A
        );
        
        foreach ( $archetypes as $archetype ) {
            $this->set( 'archetype_' . $archetype['id'], $archetype, 'static' );
        }
        
        $this->set( 'all_archetypes', $archetypes, 'static' );
    }
    
    /**
     * Warm up questions cache
     */
    protected function warmup_questions() {
        global $wpdb;
        
        $questions = $wpdb->get_results( 
            "SELECT * FROM {$wpdb->prefix}mq_questions WHERE is_active = 1 ORDER BY order_num",
            ARRAY_A
        );
        
        $this->set( 'all_questions', $questions, 'static' );
    }
    
    /**
     * Warm up recent results
     */
    protected function warmup_recent_results() {
        global $wpdb;
        
        $recent_results = $wpdb->get_results( 
            "SELECT r.*, p.Email, p.Name, a.name as archetype_name 
             FROM {$wpdb->prefix}mq_results r
             JOIN {$wpdb->prefix}mq_prospects p ON r.prospect_id = p.id
             JOIN {$wpdb->prefix}mq_archetypes a ON r.archetype_id = a.id
             ORDER BY r.created_at DESC
             LIMIT 100",
            ARRAY_A
        );
        
        $this->set( 'recent_results_100', $recent_results, 'results' );
    }
    
    /**
     * Warm up statistics
     */
    protected function warmup_statistics() {
        global $wpdb;
        
        // Total completions
        $total_completions = $wpdb->get_var( 
            "SELECT COUNT(*) FROM {$wpdb->prefix}mq_results"
        );
        $this->set( 'stat_total_completions', $total_completions, 'analytics' );
        
        // Completions by archetype
        $archetype_stats = $wpdb->get_results( 
            "SELECT archetype_id, COUNT(*) as count 
             FROM {$wpdb->prefix}mq_results 
             GROUP BY archetype_id",
            ARRAY_A
        );
        $this->set( 'stat_by_archetype', $archetype_stats, 'analytics' );
    }
    
    /**
     * Build cache key
     * 
     * @param string $key Key
     * @param string $group Group
     * @return string Full cache key
     */
    protected function build_key( $key, $group ) {
        $prefix = defined( 'MONEY_QUIZ_CACHE_PREFIX' ) ? MONEY_QUIZ_CACHE_PREFIX : 'mq';
        return "{$prefix}:{$group}:{$key}";
    }
    
    /**
     * Check if Redis is available
     * 
     * @return bool
     */
    protected function is_redis_available() {
        return class_exists( 'Redis' ) && defined( 'WP_REDIS_HOST' );
    }
    
    /**
     * Check if Memcached is available
     * 
     * @return bool
     */
    protected function is_memcached_available() {
        return class_exists( 'Memcached' ) && defined( 'MEMCACHED_SERVERS' );
    }
    
    /**
     * Set cache headers
     */
    public function set_cache_headers() {
        if ( is_user_logged_in() || is_admin() ) {
            header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
            return;
        }
        
        // Set cache headers for public pages
        if ( $this->is_money_quiz_page() ) {
            header( 'Cache-Control: public, max-age=3600' );
            header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 3600 ) . ' GMT' );
            header( 'X-Money-Quiz-Cache: enabled' );
        }
    }
    
    /**
     * Check if current page is Money Quiz page
     * 
     * @return bool
     */
    protected function is_money_quiz_page() {
        global $post;
        
        if ( ! $post ) {
            return false;
        }
        
        return has_shortcode( $post->post_content, 'money_quiz' ) ||
               has_shortcode( $post->post_content, 'money_quiz_results' );
    }
    
    /**
     * Invalidate prospect cache
     */
    public function invalidate_prospect_cache() {
        $this->flush_group( 'prospects' );
        $this->delete( 'recent_prospects', 'queries' );
        $this->delete( 'stat_total_prospects', 'analytics' );
    }
    
    /**
     * Invalidate result cache
     */
    public function invalidate_result_cache() {
        $this->flush_group( 'results' );
        $this->delete( 'recent_results_100', 'results' );
        $this->delete( 'stat_total_completions', 'analytics' );
        $this->delete( 'stat_by_archetype', 'analytics' );
    }
    
    /**
     * Invalidate all cache
     */
    public function invalidate_all_cache() {
        $this->flush_all();
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total > 0 ? ( $this->stats['hits'] / $total ) * 100 : 0;
        
        return array(
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'hit_rate' => round( $hit_rate, 2 ),
            'backend' => get_class( $this->backend ),
            'memory_usage' => $this->backend->get_memory_usage()
        );
    }
    
    /**
     * Add cache info to admin bar
     * 
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function add_cache_info_to_admin_bar( $wp_admin_bar ) {
        $stats = $this->get_stats();
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-cache',
            'title' => sprintf( 
                'Cache: %s%% (%d/%d)', 
                $stats['hit_rate'],
                $stats['hits'],
                $stats['hits'] + $stats['misses']
            ),
            'meta' => array(
                'title' => 'Money Quiz Cache Statistics'
            )
        ));
        
        $wp_admin_bar->add_node( array(
            'parent' => 'money-quiz-cache',
            'id' => 'money-quiz-cache-backend',
            'title' => 'Backend: ' . $stats['backend']
        ));
        
        $wp_admin_bar->add_node( array(
            'parent' => 'money-quiz-cache',
            'id' => 'money-quiz-cache-memory',
            'title' => 'Memory: ' . size_format( $stats['memory_usage'] )
        ));
        
        $wp_admin_bar->add_node( array(
            'parent' => 'money-quiz-cache',
            'id' => 'money-quiz-cache-flush',
            'title' => 'Flush Cache',
            'href' => wp_nonce_url( admin_url( 'admin-post.php?action=money_quiz_flush_cache' ), 'flush_cache' )
        ));
    }
}

/**
 * Cache Backend Interface
 */
interface CacheBackendInterface {
    public function get( $key );
    public function set( $key, $value, $expiration );
    public function delete( $key );
    public function flush_group( $group );
    public function flush_all();
    public function get_memory_usage();
}

/**
 * Redis Cache Backend
 */
class RedisCacheBackend implements CacheBackendInterface {
    
    /**
     * Redis instance
     * 
     * @var \Redis
     */
    protected $redis;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->redis = new \Redis();
        
        $host = defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1';
        $port = defined( 'WP_REDIS_PORT' ) ? WP_REDIS_PORT : 6379;
        
        $this->redis->connect( $host, $port );
        
        if ( defined( 'WP_REDIS_PASSWORD' ) ) {
            $this->redis->auth( WP_REDIS_PASSWORD );
        }
        
        if ( defined( 'WP_REDIS_DATABASE' ) ) {
            $this->redis->select( WP_REDIS_DATABASE );
        }
    }
    
    public function get( $key ) {
        $value = $this->redis->get( $key );
        return $value !== false ? maybe_unserialize( $value ) : false;
    }
    
    public function set( $key, $value, $expiration ) {
        $value = maybe_serialize( $value );
        
        if ( $expiration > 0 ) {
            return $this->redis->setex( $key, $expiration, $value );
        } else {
            return $this->redis->set( $key, $value );
        }
    }
    
    public function delete( $key ) {
        return $this->redis->del( $key ) > 0;
    }
    
    public function flush_group( $group ) {
        $prefix = defined( 'MONEY_QUIZ_CACHE_PREFIX' ) ? MONEY_QUIZ_CACHE_PREFIX : 'mq';
        $pattern = "{$prefix}:{$group}:*";
        
        $keys = $this->redis->keys( $pattern );
        
        if ( ! empty( $keys ) ) {
            return $this->redis->del( $keys ) > 0;
        }
        
        return true;
    }
    
    public function flush_all() {
        return $this->redis->flushDB();
    }
    
    public function get_memory_usage() {
        $info = $this->redis->info( 'memory' );
        return $info['used_memory'] ?? 0;
    }
}

/**
 * Memcached Cache Backend
 */
class MemcachedCacheBackend implements CacheBackendInterface {
    
    /**
     * Memcached instance
     * 
     * @var \Memcached
     */
    protected $memcached;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->memcached = new \Memcached( 'money_quiz' );
        
        if ( defined( 'MEMCACHED_SERVERS' ) ) {
            $servers = MEMCACHED_SERVERS;
        } else {
            $servers = array( array( '127.0.0.1', 11211 ) );
        }
        
        $this->memcached->addServers( $servers );
    }
    
    public function get( $key ) {
        return $this->memcached->get( $key );
    }
    
    public function set( $key, $value, $expiration ) {
        return $this->memcached->set( $key, $value, $expiration );
    }
    
    public function delete( $key ) {
        return $this->memcached->delete( $key );
    }
    
    public function flush_group( $group ) {
        // Memcached doesn't support group flushing natively
        // Would need to track keys per group
        return true;
    }
    
    public function flush_all() {
        return $this->memcached->flush();
    }
    
    public function get_memory_usage() {
        $stats = $this->memcached->getStats();
        $total = 0;
        
        foreach ( $stats as $server => $data ) {
            $total += $data['bytes'] ?? 0;
        }
        
        return $total;
    }
}

/**
 * APCu Cache Backend
 */
class APCuCacheBackend implements CacheBackendInterface {
    
    public function get( $key ) {
        return apcu_fetch( $key );
    }
    
    public function set( $key, $value, $expiration ) {
        return apcu_store( $key, $value, $expiration );
    }
    
    public function delete( $key ) {
        return apcu_delete( $key );
    }
    
    public function flush_group( $group ) {
        $prefix = defined( 'MONEY_QUIZ_CACHE_PREFIX' ) ? MONEY_QUIZ_CACHE_PREFIX : 'mq';
        $pattern = "/^{$prefix}:{$group}:/";
        
        $cache_info = apcu_cache_info();
        
        foreach ( $cache_info['cache_list'] as $entry ) {
            if ( preg_match( $pattern, $entry['info'] ) ) {
                apcu_delete( $entry['info'] );
            }
        }
        
        return true;
    }
    
    public function flush_all() {
        return apcu_clear_cache();
    }
    
    public function get_memory_usage() {
        $info = apcu_cache_info();
        return $info['mem_size'] ?? 0;
    }
}

/**
 * Database Cache Backend (Fallback)
 */
class DatabaseCacheBackend implements CacheBackendInterface {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mq_cache';
        $this->maybe_create_table();
    }
    
    public function get( $key ) {
        global $wpdb;
        
        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT cache_value FROM {$this->table} 
             WHERE cache_key = %s 
             AND (expires_at > %s OR expires_at IS NULL)",
            $key,
            current_time( 'mysql' )
        ));
        
        return $value !== null ? maybe_unserialize( $value ) : false;
    }
    
    public function set( $key, $value, $expiration ) {
        global $wpdb;
        
        $value = maybe_serialize( $value );
        $expires_at = $expiration > 0 ? date( 'Y-m-d H:i:s', time() + $expiration ) : null;
        
        return false !== $wpdb->replace( $this->table, array(
            'cache_key' => $key,
            'cache_value' => $value,
            'expires_at' => $expires_at,
            'created_at' => current_time( 'mysql' )
        ));
    }
    
    public function delete( $key ) {
        global $wpdb;
        
        return false !== $wpdb->delete( $this->table, array( 'cache_key' => $key ) );
    }
    
    public function flush_group( $group ) {
        global $wpdb;
        
        $prefix = defined( 'MONEY_QUIZ_CACHE_PREFIX' ) ? MONEY_QUIZ_CACHE_PREFIX : 'mq';
        
        return false !== $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE cache_key LIKE %s",
            $wpdb->esc_like( "{$prefix}:{$group}:" ) . '%'
        ));
    }
    
    public function flush_all() {
        global $wpdb;
        
        return false !== $wpdb->query( "TRUNCATE TABLE {$this->table}" );
    }
    
    public function get_memory_usage() {
        global $wpdb;
        
        $size = $wpdb->get_var( 
            "SELECT SUM(LENGTH(cache_value)) FROM {$this->table}"
        );
        
        return $size ?? 0;
    }
    
    /**
     * Create cache table if not exists
     */
    protected function maybe_create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}