<?php
/**
 * Money Quiz Plugin - Cache Utilities
 * 
 * Helper functions and utilities for caching operations
 * 
 * @package MoneyQuiz
 * @subpackage Performance
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance;

/**
 * Cache Utilities Class
 * 
 * Provides helper methods for cache operations
 */
class CacheUtilities {
    
    /**
     * Get or set cached value with automatic serialization
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param int      $expiration Expiration time in seconds
     * @param string   $group Cache group
     * @return mixed Cached or generated value
     */
    public static function remember( $key, callable $callback, $expiration = 3600, $group = 'default' ) {
        $cache = CacheManager::get_instance();
        
        $value = $cache->get( $key, $group );
        
        if ( false === $value ) {
            $value = $callback();
            $cache->set( $key, $value, $group, $expiration );
        }
        
        return $value;
    }
    
    /**
     * Cache array data with automatic chunking for large datasets
     * 
     * @param string $key Base cache key
     * @param array  $data Large array to cache
     * @param int    $chunk_size Items per chunk
     * @param int    $expiration Expiration time
     * @param string $group Cache group
     * @return bool Success
     */
    public static function cache_array_chunked( $key, array $data, $chunk_size = 1000, $expiration = 3600, $group = 'default' ) {
        $cache = CacheManager::get_instance();
        
        $chunks = array_chunk( $data, $chunk_size );
        $chunk_count = count( $chunks );
        
        // Store chunk count
        $cache->set( "{$key}_chunks", $chunk_count, $group, $expiration );
        
        // Store each chunk
        foreach ( $chunks as $index => $chunk ) {
            $cache->set( "{$key}_chunk_{$index}", $chunk, $group, $expiration );
        }
        
        return true;
    }
    
    /**
     * Retrieve chunked array from cache
     * 
     * @param string $key Base cache key
     * @param string $group Cache group
     * @return array|false Retrieved array or false
     */
    public static function get_cached_array_chunked( $key, $group = 'default' ) {
        $cache = CacheManager::get_instance();
        
        $chunk_count = $cache->get( "{$key}_chunks", $group );
        
        if ( false === $chunk_count ) {
            return false;
        }
        
        $data = array();
        
        for ( $i = 0; $i < $chunk_count; $i++ ) {
            $chunk = $cache->get( "{$key}_chunk_{$i}", $group );
            
            if ( false === $chunk ) {
                return false; // Incomplete cache
            }
            
            $data = array_merge( $data, $chunk );
        }
        
        return $data;
    }
    
    /**
     * Clear chunked array cache
     * 
     * @param string $key Base cache key
     * @param string $group Cache group
     */
    public static function clear_cached_array_chunked( $key, $group = 'default' ) {
        $cache = CacheManager::get_instance();
        
        $chunk_count = $cache->get( "{$key}_chunks", $group );
        
        if ( false !== $chunk_count ) {
            for ( $i = 0; $i < $chunk_count; $i++ ) {
                $cache->delete( "{$key}_chunk_{$i}", $group );
            }
        }
        
        $cache->delete( "{$key}_chunks", $group );
    }
    
    /**
     * Get cache size for a group
     * 
     * @param string $group Cache group
     * @return array Size information
     */
    public static function get_cache_size( $group = null ) {
        $cache = CacheManager::get_instance();
        $stats = $cache->get_stats();
        
        return array(
            'memory_usage' => $stats['memory_usage'],
            'memory_usage_formatted' => size_format( $stats['memory_usage'] ),
            'backend' => $stats['backend']
        );
    }
    
    /**
     * Invalidate cache by tags
     * 
     * @param array $tags Tags to invalidate
     */
    public static function invalidate_by_tags( array $tags ) {
        foreach ( $tags as $tag ) {
            do_action( "money_quiz_invalidate_cache_tag_{$tag}" );
        }
    }
    
    /**
     * Cache HTML output with minification
     * 
     * @param string $html HTML to cache
     * @param string $key Cache key
     * @param int    $expiration Expiration time
     * @return string Minified HTML
     */
    public static function cache_html( $html, $key, $expiration = 3600 ) {
        // Basic HTML minification
        $minified = preg_replace( '/\s+/', ' ', $html );
        $minified = preg_replace( '/> </', '><', $minified );
        
        $cache = CacheManager::get_instance();
        $cache->set( $key, $minified, 'html', $expiration );
        
        return $minified;
    }
    
    /**
     * Generate cache key from multiple parameters
     * 
     * @param array $params Parameters to include in key
     * @return string Cache key
     */
    public static function generate_cache_key( array $params ) {
        // Sort params for consistent keys
        ksort( $params );
        
        // Add context
        $params['site_id'] = get_current_blog_id();
        $params['language'] = get_locale();
        
        return md5( serialize( $params ) );
    }
    
    /**
     * Check if cache is stale
     * 
     * @param string $key Cache key
     * @param int    $max_age Maximum age in seconds
     * @param string $group Cache group
     * @return bool True if stale or missing
     */
    public static function is_cache_stale( $key, $max_age, $group = 'default' ) {
        $cache = CacheManager::get_instance();
        
        $meta_key = "{$key}_timestamp";
        $timestamp = $cache->get( $meta_key, $group );
        
        if ( false === $timestamp ) {
            return true;
        }
        
        return ( time() - $timestamp ) > $max_age;
    }
    
    /**
     * Set cache with timestamp
     * 
     * @param string $key Cache key
     * @param mixed  $value Value to cache
     * @param int    $expiration Expiration time
     * @param string $group Cache group
     */
    public static function set_with_timestamp( $key, $value, $expiration = 3600, $group = 'default' ) {
        $cache = CacheManager::get_instance();
        
        $cache->set( $key, $value, $group, $expiration );
        $cache->set( "{$key}_timestamp", time(), $group, $expiration );
    }
}

/**
 * Cache Tags Manager
 * 
 * Implements cache tagging for easier invalidation
 */
class CacheTagsManager {
    
    /**
     * Tag to keys mapping
     * 
     * @var array
     */
    protected static $tag_map = array();
    
    /**
     * Add tags to cache entry
     * 
     * @param string $key Cache key
     * @param array  $tags Tags to associate
     */
    public static function tag( $key, array $tags ) {
        foreach ( $tags as $tag ) {
            if ( ! isset( self::$tag_map[ $tag ] ) ) {
                self::$tag_map[ $tag ] = array();
            }
            
            self::$tag_map[ $tag ][] = $key;
        }
        
        // Store tag map in cache
        $cache = CacheManager::get_instance();
        $cache->set( 'cache_tags_map', self::$tag_map, 'system', 0 );
    }
    
    /**
     * Invalidate all cache entries with a specific tag
     * 
     * @param string $tag Tag to invalidate
     */
    public static function invalidate_tag( $tag ) {
        $cache = CacheManager::get_instance();
        
        // Load tag map
        self::$tag_map = $cache->get( 'cache_tags_map', 'system' ) ?: array();
        
        if ( isset( self::$tag_map[ $tag ] ) ) {
            foreach ( self::$tag_map[ $tag ] as $key ) {
                // Parse key to get group
                $parts = explode( ':', $key );
                $group = $parts[1] ?? 'default';
                
                $cache->delete( $key, $group );
            }
            
            // Remove tag from map
            unset( self::$tag_map[ $tag ] );
            $cache->set( 'cache_tags_map', self::$tag_map, 'system', 0 );
        }
    }
}

/**
 * Cache Preloader
 * 
 * Preloads cache based on usage patterns
 */
class CachePreloader {
    
    /**
     * Preload cache based on expected usage
     */
    public static function preload() {
        $cache = CacheManager::get_instance();
        
        // Preload common queries
        self::preload_common_queries();
        
        // Preload user-specific data
        if ( is_user_logged_in() ) {
            self::preload_user_data( get_current_user_id() );
        }
        
        // Preload based on referrer
        self::preload_by_referrer();
    }
    
    /**
     * Preload common queries
     */
    protected static function preload_common_queries() {
        global $wpdb;
        
        // Recent results
        CacheUtilities::remember( 'recent_results_10', function() use ( $wpdb ) {
            return $wpdb->get_results( 
                "SELECT * FROM {$wpdb->prefix}mq_results 
                 ORDER BY created_at DESC 
                 LIMIT 10",
                ARRAY_A
            );
        }, 300, 'queries' );
        
        // Active archetypes
        CacheUtilities::remember( 'active_archetypes', function() use ( $wpdb ) {
            return $wpdb->get_results( 
                "SELECT * FROM {$wpdb->prefix}mq_archetypes 
                 WHERE is_active = 1",
                ARRAY_A
            );
        }, 3600, 'static' );
    }
    
    /**
     * Preload user-specific data
     * 
     * @param int $user_id User ID
     */
    protected static function preload_user_data( $user_id ) {
        global $wpdb;
        
        // User's quiz results
        CacheUtilities::remember( "user_results_{$user_id}", function() use ( $wpdb, $user_id ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mq_results 
                 WHERE user_id = %d 
                 ORDER BY created_at DESC",
                $user_id
            ), ARRAY_A );
        }, 3600, 'user' );
    }
    
    /**
     * Preload based on referrer patterns
     */
    protected static function preload_by_referrer() {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // If coming from social media, preload sharing data
        if ( strpos( $referrer, 'facebook.com' ) !== false || 
             strpos( $referrer, 'twitter.com' ) !== false ) {
            
            CacheUtilities::remember( 'social_share_data', function() {
                return array(
                    'title' => get_option( 'money_quiz_share_title' ),
                    'description' => get_option( 'money_quiz_share_description' ),
                    'image' => get_option( 'money_quiz_share_image' )
                );
            }, 86400, 'static' );
        }
    }
}

/**
 * Object Cache Drop-in Integration
 * 
 * Integrates with WordPress object cache drop-in
 */
class ObjectCacheIntegration {
    
    /**
     * Check if object cache is available
     * 
     * @return bool
     */
    public static function is_available() {
        return wp_using_ext_object_cache();
    }
    
    /**
     * Get object cache stats
     * 
     * @return array
     */
    public static function get_stats() {
        if ( ! self::is_available() ) {
            return array( 'available' => false );
        }
        
        global $wp_object_cache;
        
        $stats = array(
            'available' => true,
            'hits' => 0,
            'misses' => 0,
            'hit_ratio' => 0
        );
        
        // Get stats based on cache implementation
        if ( method_exists( $wp_object_cache, 'stats' ) ) {
            $cache_stats = $wp_object_cache->stats();
            
            $stats['hits'] = $cache_stats['hits'] ?? 0;
            $stats['misses'] = $cache_stats['misses'] ?? 0;
            
            $total = $stats['hits'] + $stats['misses'];
            if ( $total > 0 ) {
                $stats['hit_ratio'] = round( ( $stats['hits'] / $total ) * 100, 2 );
            }
        }
        
        return $stats;
    }
    
    /**
     * Add cache groups for Money Quiz
     */
    public static function add_cache_groups() {
        if ( ! self::is_available() ) {
            return;
        }
        
        // Non-persistent groups (don't save between requests)
        wp_cache_add_non_persistent_groups( array(
            'money_quiz_counts',
            'money_quiz_found_posts'
        ));
        
        // Global groups (shared across sites in multisite)
        if ( is_multisite() ) {
            wp_cache_add_global_groups( array(
                'money_quiz_archetypes',
                'money_quiz_questions'
            ));
        }
    }
}

// Initialize object cache integration
add_action( 'init', array( 'MoneyQuiz\Performance\ObjectCacheIntegration', 'add_cache_groups' ), 1 );