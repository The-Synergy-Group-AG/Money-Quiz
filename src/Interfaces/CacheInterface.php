<?php
/**
 * Cache Interface
 *
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Interfaces;

/**
 * Interface for cache implementations
 */
interface CacheInterface {
    
    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @param mixed  $default Default value
     * @return mixed
     */
    public function get( string $key, $default = null );
    
    /**
     * Set value in cache
     * 
     * @param string $key Cache key
     * @param mixed  $value Value to cache
     * @param int    $ttl Time to live in seconds
     * @return bool
     */
    public function set( string $key, $value, int $ttl = null ): bool;
    
    /**
     * Delete value from cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function delete( string $key ): bool;
    
    /**
     * Check if key exists
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function exists( string $key ): bool;
    
    /**
     * Remember value with callback
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback to generate value
     * @param int      $ttl Time to live
     * @return mixed
     */
    public function remember( string $key, callable $callback, int $ttl = null );
}