<?php
/**
 * Money Quiz Plugin - Object Cache Integration
 * Worker 3: Advanced Object Caching Implementation
 * 
 * Provides WordPress object cache drop-in replacement with advanced features
 * including cache tagging, partial invalidation, and multi-tier caching.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\Caching
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\Caching;

use MoneyQuiz\Performance\CacheManager;

/**
 * Object Cache Integration Class
 * 
 * Advanced object cache implementation with WordPress integration
 */
class ObjectCacheIntegration {
    
    /**
     * Cache manager instance
     * 
     * @var CacheManager
     */
    protected $cache_manager;
    
    /**
     * Object cache groups
     * 
     * @var array
     */
    protected $cache_groups = array(
        'default' => 3600,
        'options' => 86400,
        'transient' => 0,
        'posts' => 3600,
        'terms' => 7200,
        'post_meta' => 3600,
        'user_meta' => 7200,
        'comment' => 1800,
        'money_quiz' => 3600,
        'money_quiz_results' => 7200,
        'money_quiz_analytics' => 900
    );
    
    /**
     * Non-persistent groups
     * 
     * @var array
     */
    protected $non_persistent_groups = array(
        'counts',
        'plugins',
        'themes',
        'comment',
        'notoptions'
    );
    
    /**
     * Global groups
     * 
     * @var array
     */
    protected $global_groups = array(
        'users',
        'userlogins',
        'usermeta',
        'user_meta',
        'useremail',
        'userslugs',
        'site-transient',
        'site-options',
        'blog-lookup',
        'blog-details',
        'site-details',
        'rss',
        'global-posts',
        'blog-id-cache',
        'networks',
        'sites'
    );
    
    /**
     * Cache statistics
     * 
     * @var array
     */
    protected $stats = array(
        'hits' => 0,
        'misses' => 0,
        'adds' => 0,
        'deletes' => 0,
        'flushes' => 0
    );
    
    /**
     * Cache tags mapping
     * 
     * @var array
     */
    protected $cache_tags = array();
    
    /**
     * Local memory cache
     * 
     * @var array
     */
    protected $local_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = CacheManager::get_instance();
        $this->init();
    }
    
    /**
     * Initialize object cache
     */
    protected function init() {
        // Register WordPress cache handlers
        $this->register_wordpress_handlers();
        
        // Set up cache groups
        $this->setup_cache_groups();
        
        // Initialize cache warming
        $this->schedule_cache_warming();
        
        // Set up cache debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $this->enable_debug_mode();
        }
    }
    
    /**
     * Get cached object
     * 
     * @param int|string $key    Cache key
     * @param string     $group  Cache group
     * @param bool       $force  Force refresh
     * @param bool       $found  Whether key was found
     * @return mixed Cached data or false
     */
    public function get( $key, $group = 'default', $force = false, &$found = null ) {
        $cache_key = $this->build_key( $key, $group );
        
        // Check local cache first
        if ( ! $force && isset( $this->local_cache[ $cache_key ] ) ) {
            $found = true;
            $this->stats['hits']++;
            return $this->local_cache[ $cache_key ];
        }
        
        // Skip non-persistent groups if not in local cache
        if ( $this->is_non_persistent_group( $group ) ) {
            $found = false;
            $this->stats['misses']++;
            return false;
        }
        
        // Get from cache manager
        $value = $this->cache_manager->get( $cache_key, $group );
        
        if ( false !== $value ) {
            $found = true;
            $this->stats['hits']++;
            $this->local_cache[ $cache_key ] = $value;
        } else {
            $found = false;
            $this->stats['misses']++;
        }
        
        return $value;
    }
    
    /**
     * Set object in cache
     * 
     * @param int|string $key    Cache key
     * @param mixed      $data   Data to cache
     * @param string     $group  Cache group
     * @param int        $expire Expiration time
     * @return bool Success
     */
    public function set( $key, $data, $group = 'default', $expire = 0 ) {
        $cache_key = $this->build_key( $key, $group );
        
        // Store in local cache
        $this->local_cache[ $cache_key ] = $data;
        
        // Skip persistent storage for non-persistent groups
        if ( $this->is_non_persistent_group( $group ) ) {
            return true;
        }
        
        // Determine expiration
        if ( 0 === $expire ) {
            $expire = $this->cache_groups[ $group ] ?? 3600;
        }
        
        // Set in cache manager
        $result = $this->cache_manager->set( $cache_key, $data, $group, $expire );
        
        if ( $result ) {
            $this->stats['adds']++;
            
            // Update cache tags
            $this->update_cache_tags( $cache_key, $group );
        }
        
        return $result;
    }
    
    /**
     * Add object to cache (only if not exists)
     * 
     * @param int|string $key    Cache key
     * @param mixed      $data   Data to cache
     * @param string     $group  Cache group
     * @param int        $expire Expiration time
     * @return bool Success
     */
    public function add( $key, $data, $group = 'default', $expire = 0 ) {
        $cache_key = $this->build_key( $key, $group );
        
        // Check if already exists
        if ( isset( $this->local_cache[ $cache_key ] ) ) {
            return false;
        }
        
        $found = false;
        $this->get( $key, $group, false, $found );
        
        if ( $found ) {
            return false;
        }
        
        return $this->set( $key, $data, $group, $expire );
    }
    
    /**
     * Replace object in cache (only if exists)
     * 
     * @param int|string $key    Cache key
     * @param mixed      $data   Data to cache
     * @param string     $group  Cache group
     * @param int        $expire Expiration time
     * @return bool Success
     */
    public function replace( $key, $data, $group = 'default', $expire = 0 ) {
        $found = false;
        $this->get( $key, $group, false, $found );
        
        if ( ! $found ) {
            return false;
        }
        
        return $this->set( $key, $data, $group, $expire );
    }
    
    /**
     * Delete object from cache
     * 
     * @param int|string $key   Cache key
     * @param string     $group Cache group
     * @return bool Success
     */
    public function delete( $key, $group = 'default' ) {
        $cache_key = $this->build_key( $key, $group );
        
        // Remove from local cache
        unset( $this->local_cache[ $cache_key ] );
        
        // Skip persistent storage for non-persistent groups
        if ( $this->is_non_persistent_group( $group ) ) {
            $this->stats['deletes']++;
            return true;
        }
        
        // Delete from cache manager
        $result = $this->cache_manager->delete( $cache_key, $group );
        
        if ( $result ) {
            $this->stats['deletes']++;
            
            // Remove from cache tags
            $this->remove_from_cache_tags( $cache_key );
        }
        
        return $result;
    }
    
    /**
     * Increment numeric cache value
     * 
     * @param int|string $key    Cache key
     * @param int        $offset Amount to increment
     * @param string     $group  Cache group
     * @return int|bool New value or false
     */
    public function incr( $key, $offset = 1, $group = 'default' ) {
        $value = $this->get( $key, $group );
        
        if ( false === $value || ! is_numeric( $value ) ) {
            return false;
        }
        
        $value = (int) $value + (int) $offset;
        
        if ( $this->set( $key, $value, $group ) ) {
            return $value;
        }
        
        return false;
    }
    
    /**
     * Decrement numeric cache value
     * 
     * @param int|string $key    Cache key
     * @param int        $offset Amount to decrement
     * @param string     $group  Cache group
     * @return int|bool New value or false
     */
    public function decr( $key, $offset = 1, $group = 'default' ) {
        return $this->incr( $key, -$offset, $group );
    }
    
    /**
     * Flush cache group
     * 
     * @param string $group Cache group
     * @return bool Success
     */
    public function flush_group( $group ) {
        // Clear local cache for group
        foreach ( $this->local_cache as $key => $value ) {
            if ( false !== strpos( $key, ":{$group}:" ) ) {
                unset( $this->local_cache[ $key ] );
            }
        }
        
        // Flush in cache manager
        $result = $this->cache_manager->flush_group( $group );
        
        if ( $result ) {
            $this->stats['flushes']++;
            
            // Clear cache tags for group
            $this->clear_group_tags( $group );
        }
        
        return $result;
    }
    
    /**
     * Flush all cache
     * 
     * @return bool Success
     */
    public function flush() {
        // Clear local cache
        $this->local_cache = array();
        
        // Clear all cache tags
        $this->cache_tags = array();
        
        // Flush in cache manager
        $result = $this->cache_manager->flush_all();
        
        if ( $result ) {
            $this->stats['flushes']++;
        }
        
        return $result;
    }
    
    /**
     * Add global cache group
     * 
     * @param string|array $groups Group(s) to add
     */
    public function add_global_groups( $groups ) {
        if ( ! is_array( $groups ) ) {
            $groups = array( $groups );
        }
        
        $this->global_groups = array_unique( array_merge( $this->global_groups, $groups ) );
    }
    
    /**
     * Add non-persistent cache group
     * 
     * @param string|array $groups Group(s) to add
     */
    public function add_non_persistent_groups( $groups ) {
        if ( ! is_array( $groups ) ) {
            $groups = array( $groups );
        }
        
        $this->non_persistent_groups = array_unique( array_merge( $this->non_persistent_groups, $groups ) );
    }
    
    /**
     * Switch blog context
     * 
     * @param int $blog_id Blog ID
     */
    public function switch_to_blog( $blog_id ) {
        global $blog_id;
        $prev_blog_id = $blog_id;
        $blog_id = $blog_id;
        
        // Clear non-global groups from local cache
        foreach ( $this->local_cache as $key => $value ) {
            list( $prefix, $group, $id ) = explode( ':', $key, 3 );
            
            if ( ! in_array( $group, $this->global_groups ) ) {
                unset( $this->local_cache[ $key ] );
            }
        }
    }
    
    /**
     * Build cache key
     * 
     * @param int|string $key   Cache key
     * @param string     $group Cache group
     * @return string Full cache key
     */
    protected function build_key( $key, $group ) {
        global $blog_id;
        
        $prefix = is_multisite() && ! in_array( $group, $this->global_groups ) 
            ? $blog_id . ':' 
            : '';
            
        return $prefix . $group . ':' . $key;
    }
    
    /**
     * Check if group is non-persistent
     * 
     * @param string $group Group name
     * @return bool
     */
    protected function is_non_persistent_group( $group ) {
        return in_array( $group, $this->non_persistent_groups );
    }
    
    /**
     * Update cache tags
     * 
     * @param string $cache_key Cache key
     * @param string $group     Cache group
     */
    protected function update_cache_tags( $cache_key, $group ) {
        // Extract tags from group and key
        $tags = $this->extract_tags( $cache_key, $group );
        
        foreach ( $tags as $tag ) {
            if ( ! isset( $this->cache_tags[ $tag ] ) ) {
                $this->cache_tags[ $tag ] = array();
            }
            
            $this->cache_tags[ $tag ][] = $cache_key;
        }
    }
    
    /**
     * Remove from cache tags
     * 
     * @param string $cache_key Cache key
     */
    protected function remove_from_cache_tags( $cache_key ) {
        foreach ( $this->cache_tags as $tag => &$keys ) {
            $keys = array_diff( $keys, array( $cache_key ) );
            
            if ( empty( $keys ) ) {
                unset( $this->cache_tags[ $tag ] );
            }
        }
    }
    
    /**
     * Clear cache by tag
     * 
     * @param string $tag Tag to clear
     * @return int Number of items cleared
     */
    public function clear_by_tag( $tag ) {
        if ( ! isset( $this->cache_tags[ $tag ] ) ) {
            return 0;
        }
        
        $cleared = 0;
        $keys = $this->cache_tags[ $tag ];
        
        foreach ( $keys as $cache_key ) {
            // Parse key to get group
            $parts = explode( ':', $cache_key );
            $group = $parts[1] ?? 'default';
            $key = $parts[2] ?? $parts[0];
            
            if ( $this->delete( $key, $group ) ) {
                $cleared++;
            }
        }
        
        unset( $this->cache_tags[ $tag ] );
        
        return $cleared;
    }
    
    /**
     * Extract tags from cache key and group
     * 
     * @param string $cache_key Cache key
     * @param string $group     Cache group
     * @return array Tags
     */
    protected function extract_tags( $cache_key, $group ) {
        $tags = array( $group );
        
        // Extract post ID tags
        if ( preg_match( '/post_(\d+)/', $cache_key, $matches ) ) {
            $tags[] = 'post_' . $matches[1];
        }
        
        // Extract user ID tags
        if ( preg_match( '/user_(\d+)/', $cache_key, $matches ) ) {
            $tags[] = 'user_' . $matches[1];
        }
        
        // Extract term ID tags
        if ( preg_match( '/term_(\d+)/', $cache_key, $matches ) ) {
            $tags[] = 'term_' . $matches[1];
        }
        
        // Money Quiz specific tags
        if ( strpos( $group, 'money_quiz' ) !== false ) {
            $tags[] = 'money_quiz';
            
            if ( preg_match( '/archetype_(\d+)/', $cache_key, $matches ) ) {
                $tags[] = 'archetype_' . $matches[1];
            }
            
            if ( preg_match( '/prospect_(\d+)/', $cache_key, $matches ) ) {
                $tags[] = 'prospect_' . $matches[1];
            }
            
            if ( preg_match( '/result_(\d+)/', $cache_key, $matches ) ) {
                $tags[] = 'result_' . $matches[1];
            }
        }
        
        return array_unique( $tags );
    }
    
    /**
     * Clear group tags
     * 
     * @param string $group Group name
     */
    protected function clear_group_tags( $group ) {
        foreach ( $this->cache_tags as $tag => &$keys ) {
            $keys = array_filter( $keys, function( $key ) use ( $group ) {
                return false === strpos( $key, ":{$group}:" );
            });
            
            if ( empty( $keys ) ) {
                unset( $this->cache_tags[ $tag ] );
            }
        }
    }
    
    /**
     * Register WordPress cache handlers
     */
    protected function register_wordpress_handlers() {
        // Post cache invalidation
        add_action( 'clean_post_cache', array( $this, 'clean_post_cache' ) );
        
        // Term cache invalidation
        add_action( 'clean_term_cache', array( $this, 'clean_term_cache' ) );
        
        // User cache invalidation
        add_action( 'clean_user_cache', array( $this, 'clean_user_cache' ) );
        
        // Comment cache invalidation
        add_action( 'clean_comment_cache', array( $this, 'clean_comment_cache' ) );
        
        // Option cache invalidation
        add_action( 'updated_option', array( $this, 'clean_option_cache' ) );
        add_action( 'added_option', array( $this, 'clean_option_cache' ) );
        add_action( 'deleted_option', array( $this, 'clean_option_cache' ) );
    }
    
    /**
     * Clean post cache
     * 
     * @param int $post_id Post ID
     */
    public function clean_post_cache( $post_id ) {
        $this->clear_by_tag( 'post_' . $post_id );
        $this->delete( $post_id, 'posts' );
        $this->delete( $post_id, 'post_meta' );
    }
    
    /**
     * Clean term cache
     * 
     * @param array $ids Term IDs
     */
    public function clean_term_cache( $ids ) {
        foreach ( $ids as $id ) {
            $this->clear_by_tag( 'term_' . $id );
        }
    }
    
    /**
     * Clean user cache
     * 
     * @param int $user_id User ID
     */
    public function clean_user_cache( $user_id ) {
        $this->clear_by_tag( 'user_' . $user_id );
        $this->delete( $user_id, 'users' );
        $this->delete( $user_id, 'user_meta' );
    }
    
    /**
     * Clean comment cache
     * 
     * @param int $comment_id Comment ID
     */
    public function clean_comment_cache( $comment_id ) {
        $this->delete( $comment_id, 'comment' );
    }
    
    /**
     * Clean option cache
     * 
     * @param string $option Option name
     */
    public function clean_option_cache( $option ) {
        $this->delete( $option, 'options' );
        
        // Clear alloptions cache
        $this->delete( 'alloptions', 'options' );
    }
    
    /**
     * Setup cache groups
     */
    protected function setup_cache_groups() {
        // Add Money Quiz specific groups
        $this->add_global_groups( array(
            'money_quiz',
            'money_quiz_analytics',
            'money_quiz_results'
        ) );
        
        // Add non-persistent groups
        $this->add_non_persistent_groups( array(
            'money_quiz_temp',
            'money_quiz_session'
        ) );
    }
    
    /**
     * Schedule cache warming
     */
    protected function schedule_cache_warming() {
        if ( ! wp_next_scheduled( 'money_quiz_warm_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'money_quiz_warm_cache' );
        }
        
        add_action( 'money_quiz_warm_cache', array( $this, 'warm_cache' ) );
    }
    
    /**
     * Warm cache
     */
    public function warm_cache() {
        // Warm common options
        $this->warm_options();
        
        // Warm Money Quiz data
        $this->warm_money_quiz_data();
        
        // Warm recent posts
        $this->warm_recent_posts();
    }
    
    /**
     * Warm options cache
     */
    protected function warm_options() {
        $options = array(
            'siteurl',
            'home',
            'blogname',
            'blogdescription',
            'users_can_register',
            'default_role',
            'active_plugins',
            'template',
            'stylesheet',
            'money_quiz_settings'
        );
        
        foreach ( $options as $option ) {
            get_option( $option );
        }
    }
    
    /**
     * Warm Money Quiz data
     */
    protected function warm_money_quiz_data() {
        // Delegate to cache manager
        $this->cache_manager->warmup();
    }
    
    /**
     * Warm recent posts
     */
    protected function warm_recent_posts() {
        $recent_posts = get_posts( array(
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ) );
        
        foreach ( $recent_posts as $post ) {
            // Cache post object
            $this->set( $post->ID, $post, 'posts' );
            
            // Cache post meta
            $meta = get_post_meta( $post->ID );
            $this->set( $post->ID, $meta, 'post_meta' );
        }
    }
    
    /**
     * Enable debug mode
     */
    protected function enable_debug_mode() {
        add_action( 'shutdown', array( $this, 'output_debug_info' ) );
    }
    
    /**
     * Output debug information
     */
    public function output_debug_info() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $stats = $this->get_stats();
        
        echo "\n<!-- Money Quiz Object Cache Debug -->\n";
        echo "<!-- Hits: {$stats['hits']} -->\n";
        echo "<!-- Misses: {$stats['misses']} -->\n";
        echo "<!-- Hit Rate: {$stats['hit_rate']}% -->\n";
        echo "<!-- Memory: " . size_format( $stats['memory_usage'] ) . " -->\n";
        echo "<!-- /Money Quiz Object Cache Debug -->\n";
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total > 0 ? ( $this->stats['hits'] / $total ) * 100 : 0;
        
        // Get memory usage from cache manager
        $manager_stats = $this->cache_manager->get_stats();
        
        return array(
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'adds' => $this->stats['adds'],
            'deletes' => $this->stats['deletes'],
            'flushes' => $this->stats['flushes'],
            'hit_rate' => round( $hit_rate, 2 ),
            'memory_usage' => $manager_stats['memory_usage'],
            'local_cache_size' => count( $this->local_cache ),
            'cache_tags_count' => count( $this->cache_tags )
        );
    }
}

// WordPress object cache API functions
if ( ! function_exists( 'wp_cache_init' ) ) {
    function wp_cache_init() {
        $GLOBALS['wp_object_cache'] = new ObjectCacheIntegration();
    }
}

if ( ! function_exists( 'wp_cache_get' ) ) {
    function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
        global $wp_object_cache;
        return $wp_object_cache->get( $key, $group, $force, $found );
    }
}

if ( ! function_exists( 'wp_cache_set' ) ) {
    function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->set( $key, $data, $group, $expire );
    }
}

if ( ! function_exists( 'wp_cache_add' ) ) {
    function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->add( $key, $data, $group, $expire );
    }
}

if ( ! function_exists( 'wp_cache_replace' ) ) {
    function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->replace( $key, $data, $group, $expire );
    }
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
    function wp_cache_delete( $key, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->delete( $key, $group );
    }
}

if ( ! function_exists( 'wp_cache_incr' ) ) {
    function wp_cache_incr( $key, $offset = 1, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->incr( $key, $offset, $group );
    }
}

if ( ! function_exists( 'wp_cache_decr' ) ) {
    function wp_cache_decr( $key, $offset = 1, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->decr( $key, $offset, $group );
    }
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
    function wp_cache_flush() {
        global $wp_object_cache;
        return $wp_object_cache->flush();
    }
}

if ( ! function_exists( 'wp_cache_add_global_groups' ) ) {
    function wp_cache_add_global_groups( $groups ) {
        global $wp_object_cache;
        $wp_object_cache->add_global_groups( $groups );
    }
}

if ( ! function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
    function wp_cache_add_non_persistent_groups( $groups ) {
        global $wp_object_cache;
        $wp_object_cache->add_non_persistent_groups( $groups );
    }
}

if ( ! function_exists( 'wp_cache_switch_to_blog' ) ) {
    function wp_cache_switch_to_blog( $blog_id ) {
        global $wp_object_cache;
        $wp_object_cache->switch_to_blog( $blog_id );
    }
}