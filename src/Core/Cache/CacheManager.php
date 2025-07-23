<?php
/**
 * Cache Manager
 *
 * Manages caching for the plugin.
 *
 * @package MoneyQuiz\Core\Cache
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Cache;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Cache manager class.
 *
 * @since 7.0.0
 */
class CacheManager {

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	private string $cache_group;

	/**
	 * Default expiration.
	 *
	 * @var int
	 */
	private int $default_expiration = 3600;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $cache_group Cache group name.
	 */
	public function __construct( string $cache_group ) {
		$this->cache_group = $cache_group;
	}

	/**
	 * Get cached value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false.
	 */
	public function get( string $key ) {
		return wp_cache_get( $key, $this->cache_group );
	}

	/**
	 * Set cache value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration in seconds.
	 * @return bool True on success.
	 */
	public function set( string $key, $value, int $expiration = 0 ): bool {
		if ( $expiration === 0 ) {
			$expiration = $this->default_expiration;
		}

		return wp_cache_set( $key, $value, $this->cache_group, $expiration );
	}

	/**
	 * Delete cached value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key Cache key.
	 * @return bool True on success.
	 */
	public function delete( string $key ): bool {
		return wp_cache_delete( $key, $this->cache_group );
	}

	/**
	 * Flush all cache in group.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True on success.
	 */
	public function flush(): bool {
		return wp_cache_flush_group( $this->cache_group );
	}

	/**
	 * Remember value with callback.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Callback to generate value.
	 * @param int      $expiration Expiration in seconds.
	 * @return mixed Cached or generated value.
	 */
	public function remember( string $key, callable $callback, int $expiration = 0 ) {
		$value = $this->get( $key );

		if ( false === $value ) {
			$value = $callback();
			$this->set( $key, $value, $expiration );
		}

		return $value;
	}

	/**
	 * Increment numeric value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key    Cache key.
	 * @param int    $offset Increment amount.
	 * @return int|false New value or false.
	 */
	public function increment( string $key, int $offset = 1 ) {
		return wp_cache_incr( $key, $offset, $this->cache_group );
	}

	/**
	 * Decrement numeric value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key    Cache key.
	 * @param int    $offset Decrement amount.
	 * @return int|false New value or false.
	 */
	public function decrement( string $key, int $offset = 1 ) {
		return wp_cache_decr( $key, $offset, $this->cache_group );
	}

	/**
	 * Get multiple values.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string> $keys Cache keys.
	 * @return array<string, mixed> Key-value pairs.
	 */
	public function get_multiple( array $keys ): array {
		$values = [];

		foreach ( $keys as $key ) {
			$values[ $key ] = $this->get( $key );
		}

		return $values;
	}

	/**
	 * Set default expiration.
	 *
	 * @since 7.0.0
	 *
	 * @param int $seconds Expiration in seconds.
	 * @return void
	 */
	public function set_default_expiration( int $seconds ): void {
		$this->default_expiration = $seconds;
	}
}