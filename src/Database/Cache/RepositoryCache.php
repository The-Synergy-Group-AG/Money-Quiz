<?php
/**
 * Repository Cache
 *
 * Provides caching functionality for repository operations.
 *
 * @package MoneyQuiz\Database\Cache
 * @since   7.0.0
 */

namespace MoneyQuiz\Database\Cache;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Repository cache class.
 *
 * @since 7.0.0
 */
class RepositoryCache {
    
    /**
     * Cache group prefix.
     *
     * @var string
     */
    private const CACHE_GROUP = 'money_quiz_';
    
    /**
     * Default cache expiration (1 hour).
     *
     * @var int
     */
    private const DEFAULT_EXPIRATION = 3600;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Cache group name.
     *
     * @var string
     */
    private string $cache_group;
    
    /**
     * Cache expiration time.
     *
     * @var int
     */
    private int $expiration;
    
    /**
     * Constructor.
     *
     * @param string $repository_name Repository name for cache group.
     * @param Logger $logger          Logger instance.
     * @param int    $expiration      Cache expiration in seconds.
     */
    public function __construct(string $repository_name, Logger $logger, int $expiration = self::DEFAULT_EXPIRATION) {
        $this->cache_group = self::CACHE_GROUP . $repository_name;
        $this->logger = $logger;
        $this->expiration = $expiration;
    }
    
    /**
     * Get cached value.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached value or false.
     */
    public function get(string $key) {
        $cache_key = $this->build_cache_key($key);
        $value = wp_cache_get($cache_key, $this->cache_group);
        
        if ($value !== false) {
            $this->logger->debug('Cache hit', [
                'key' => $cache_key,
                'group' => $this->cache_group
            ]);
        }
        
        return $value;
    }
    
    /**
     * Set cache value.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @return bool True on success.
     */
    public function set(string $key, $value): bool {
        $cache_key = $this->build_cache_key($key);
        $result = wp_cache_set($cache_key, $value, $this->cache_group, $this->expiration);
        
        if ($result) {
            $this->logger->debug('Cache set', [
                'key' => $cache_key,
                'group' => $this->cache_group,
                'expiration' => $this->expiration
            ]);
        }
        
        return $result;
    }
    
    /**
     * Delete cached value.
     *
     * @param string $key Cache key.
     * @return bool True on success.
     */
    public function delete(string $key): bool {
        $cache_key = $this->build_cache_key($key);
        $result = wp_cache_delete($cache_key, $this->cache_group);
        
        if ($result) {
            $this->logger->debug('Cache deleted', [
                'key' => $cache_key,
                'group' => $this->cache_group
            ]);
        }
        
        return $result;
    }
    
    /**
     * Flush all cache for this group.
     *
     * @return bool True on success.
     */
    public function flush(): bool {
        $result = wp_cache_flush_group($this->cache_group);
        
        if ($result) {
            $this->logger->info('Cache group flushed', [
                'group' => $this->cache_group
            ]);
        }
        
        return $result;
    }
    
    /**
     * Build cache key.
     *
     * @param string $key Raw key.
     * @return string Cache key.
     */
    private function build_cache_key(string $key): string {
        return md5($key);
    }
}