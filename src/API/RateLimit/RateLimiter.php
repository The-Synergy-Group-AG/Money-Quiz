<?php
/**
 * Rate Limiter
 *
 * Implements rate limiting for API endpoints.
 *
 * @package MoneyQuiz\API\RateLimit
 * @since   7.0.0
 */

namespace MoneyQuiz\API\RateLimit;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Rate limiter class.
 *
 * @since 7.0.0
 */
class RateLimiter {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Rate limit configuration.
     *
     * @var array
     */
    private array $config;
    
    /**
     * Rate limit profiles.
     *
     * @var array
     */
    private array $profiles;
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->load_config();
    }
    
    /**
     * Load configuration from file.
     *
     * @return void
     */
    private function load_config(): void {
        $config_file = MONEY_QUIZ_PLUGIN_DIR . 'config/rate-limits.php';
        
        if (file_exists($config_file)) {
            $this->config = require $config_file;
            $this->profiles = $this->config['profiles'] ?? [];
        } else {
            // Fallback to defaults if config file not found
            $this->config = [];
            $this->profiles = [
                'default' => ['requests' => 60, 'window' => 60],
                'strict' => ['requests' => 10, 'window' => 60],
                'relaxed' => ['requests' => 300, 'window' => 60]
            ];
        }
        
        // Allow filtering of configuration
        $this->config = apply_filters('money_quiz_rate_limit_config', $this->config);
        $this->profiles = apply_filters('money_quiz_rate_limit_profiles', $this->profiles);
    }
    
    /**
     * Check if request is allowed.
     *
     * @param string      $identifier Client identifier (IP, user ID, etc.).
     * @param string      $endpoint   Endpoint being accessed.
     * @param string      $limit_type Limit type to apply.
     * @param \WP_REST_Request|null $request WordPress REST request object.
     * @return bool|array True if allowed, array with error details if not.
     */
    public function check(string $identifier, string $endpoint, string $limit_type = 'default', ?\WP_REST_Request $request = null): bool|array {
        $limit_config = $this->profiles[$limit_type] ?? $this->profiles['default'];
        $cache_key = $this->get_cache_key($identifier, $endpoint);
        
        // Get current count and window start
        $data = get_transient($cache_key);
        
        if ($data === false) {
            // First request in this window
            $this->set_limit_data($cache_key, 1, $limit_config['window']);
            return true;
        }
        
        $count = $data['count'] ?? 0;
        $window_start = $data['window_start'] ?? time();
        
        // Check if we're still in the same window
        if (time() - $window_start >= $limit_config['window']) {
            // New window
            $this->set_limit_data($cache_key, 1, $limit_config['window']);
            return true;
        }
        
        // Check if limit exceeded
        if ($count >= $limit_config['requests']) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'endpoint' => $endpoint,
                'count' => $count,
                'limit' => $limit_config['requests']
            ]);
            
            $retry_after = $limit_config['window'] - (time() - $window_start);
            
            return [
                'code' => 'rate_limit_exceeded',
                'message' => __('Too many requests. Please try again later.', 'money-quiz'),
                'data' => [
                    'status' => 429,
                    'retry_after' => $retry_after,
                    'limit' => $limit_config['requests'],
                    'window' => $limit_config['window']
                ]
            ];
        }
        
        // Increment counter
        $this->set_limit_data($cache_key, $count + 1, $limit_config['window'] - (time() - $window_start), $window_start);
        
        return true;
    }
    
    /**
     * Get rate limit headers.
     *
     * @param string $identifier Client identifier.
     * @param string $endpoint   Endpoint being accessed.
     * @param string $limit_type Limit type.
     * @return array Headers array.
     */
    public function get_headers(string $identifier, string $endpoint, string $limit_type = 'default'): array {
        $limit_config = $this->profiles[$limit_type] ?? $this->profiles['default'];
        $cache_key = $this->get_cache_key($identifier, $endpoint);
        
        $data = get_transient($cache_key);
        $count = $data['count'] ?? 0;
        $window_start = $data['window_start'] ?? time();
        
        $remaining = max(0, $limit_config['requests'] - $count);
        $reset = $window_start + $limit_config['window'];
        
        return [
            'X-RateLimit-Limit' => $limit_config['requests'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset
        ];
    }
    
    /**
     * Reset rate limit for identifier.
     *
     * @param string $identifier Client identifier.
     * @param string $endpoint   Endpoint.
     * @return void
     */
    public function reset(string $identifier, string $endpoint): void {
        $cache_key = $this->get_cache_key($identifier, $endpoint);
        delete_transient($cache_key);
        
        $this->logger->info('Rate limit reset', [
            'identifier' => $identifier,
            'endpoint' => $endpoint
        ]);
    }
    
    /**
     * Set custom limit.
     *
     * @param string $type   Limit type name.
     * @param int    $requests Number of requests allowed.
     * @param int    $window   Time window in seconds.
     * @return void
     */
    public function set_limit(string $type, int $requests, int $window): void {
        $this->profiles[$type] = [
            'requests' => $requests,
            'window' => $window
        ];
    }
    
    /**
     * Get cache key.
     *
     * @param string $identifier Client identifier.
     * @param string $endpoint   Endpoint.
     * @return string Cache key.
     */
    private function get_cache_key(string $identifier, string $endpoint): string {
        return 'money_quiz_rate_limit_' . md5($identifier . '::' . $endpoint);
    }
    
    /**
     * Set limit data in cache.
     *
     * @param string $cache_key    Cache key.
     * @param int    $count        Request count.
     * @param int    $expiration   Expiration time.
     * @param int    $window_start Window start time.
     * @return void
     */
    private function set_limit_data(string $cache_key, int $count, int $expiration, ?int $window_start = null): void {
        set_transient($cache_key, [
            'count' => $count,
            'window_start' => $window_start ?? time()
        ], $expiration);
    }
}