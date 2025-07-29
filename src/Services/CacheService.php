<?php
/**
 * Cache Service
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

/**
 * Caching service for performance optimization
 * 
 * Provides a unified interface for object caching and transients
 */
class CacheService {
    
    /**
     * @var string Cache group
     */
    private string $cache_group = 'money_quiz';
    
    /**
     * @var int Default expiration time (1 hour)
     */
    private int $default_expiration = 3600;
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        // Try object cache first
        $value = wp_cache_get( $key, $this->cache_group );
        
        if ( false !== $value ) {
            return $value;
        }
        
        // Fall back to transient
        $value = get_transient( $this->get_transient_key( $key ) );
        
        return false !== $value ? $value : $default;
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed  $value Value to cache
     * @param int    $expiration Expiration time in seconds
     * @return bool
     */
    public function set( string $key, $value, int $expiration = null ): bool {
        $expiration = $expiration ?? $this->default_expiration;
        
        // Set in object cache
        wp_cache_set( $key, $value, $this->cache_group, $expiration );
        
        // Also set as transient for persistence
        return set_transient( $this->get_transient_key( $key ), $value, $expiration );
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function delete( string $key ): bool {
        wp_cache_delete( $key, $this->cache_group );
        return delete_transient( $this->get_transient_key( $key ) );
    }
    
    /**
     * Check if cache key exists
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function exists( string $key ): bool {
        return false !== $this->get( $key );
    }
    
    /**
     * Remember value with callback
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback to generate value
     * @param int      $expiration Expiration time
     * @return mixed
     */
    public function remember( string $key, callable $callback, int $expiration = null ) {
        $value = $this->get( $key );
        
        if ( false === $value ) {
            $value = $callback();
            $this->set( $key, $value, $expiration );
        }
        
        return $value;
    }
    
    /**
     * Remember value forever
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback to generate value
     * @return mixed
     */
    public function forever( string $key, callable $callback ) {
        return $this->remember( $key, $callback, 0 );
    }
    
    /**
     * Flush all plugin cache
     * 
     * @return bool
     */
    public function flush(): bool {
        // Flush object cache group
        wp_cache_flush_group( $this->cache_group );
        
        // Delete all plugin transients
        global $wpdb;
        
        $transient_prefix = $this->get_transient_key( '' );
        $like = $wpdb->esc_like( '_transient_' . $transient_prefix ) . '%';
        
        return false !== $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                $like,
                str_replace( '_transient_', '_transient_timeout_', $like )
            )
        );
    }
    
    /**
     * Flush cache by pattern
     * 
     * @param string $pattern Key pattern to match
     * @return void
     */
    public function flush_pattern( string $pattern ): void {
        global $wpdb;
        
        $transient_pattern = $this->get_transient_key( $pattern );
        $like = $wpdb->esc_like( '_transient_' . $transient_pattern ) . '%';
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                $like,
                str_replace( '_transient_', '_transient_timeout_', $like )
            )
        );
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function get_stats(): array {
        global $wpdb;
        
        $transient_prefix = $this->get_transient_key( '' );
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . $transient_prefix ) . '%'
            )
        );
        
        $size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . $transient_prefix ) . '%'
            )
        );
        
        return [
            'entries' => (int) $count,
            'size' => (int) $size,
            'size_formatted' => size_format( $size ),
        ];
    }
    
    /**
     * Get transient key with prefix
     * 
     * @param string $key Cache key
     * @return string
     */
    private function get_transient_key( string $key ): string {
        return 'mq_' . $key;
    }
    
    /**
     * Set cache group
     * 
     * @param string $group Cache group
     * @return void
     */
    public function set_group( string $group ): void {
        $this->cache_group = $group;
    }
    
    /**
     * Set default expiration
     * 
     * @param int $seconds Expiration in seconds
     * @return void
     */
    public function set_default_expiration( int $seconds ): void {
        $this->default_expiration = $seconds;
    }
}