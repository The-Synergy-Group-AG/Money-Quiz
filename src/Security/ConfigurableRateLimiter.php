<?php
/**
 * Configurable Rate Limiter
 *
 * Rate limiting with admin-configurable thresholds.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Config\ConfigManager;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Configurable rate limiter class.
 *
 * @since 7.0.0
 */
class ConfigurableRateLimiter extends RateLimiter {
    
    /**
     * Config manager.
     *
     * @var ConfigManager
     */
    private ConfigManager $config;
    
    /**
     * Default rate limits.
     *
     * @var array
     */
    private array $default_limits = [
        'api_request' => [
            'requests' => 100,
            'window' => 3600 // 1 hour
        ],
        'quiz_submission' => [
            'requests' => 10,
            'window' => 3600
        ],
        'login_attempt' => [
            'requests' => 5,
            'window' => 900 // 15 minutes
        ],
        'export_data' => [
            'requests' => 5,
            'window' => 3600
        ],
        'email_send' => [
            'requests' => 20,
            'window' => 3600
        ]
    ];
    
    /**
     * Constructor.
     *
     * @param string        $table_name Table name.
     * @param Logger        $logger     Logger instance.
     * @param ConfigManager $config     Config manager.
     */
    public function __construct(string $table_name, Logger $logger, ConfigManager $config) {
        parent::__construct($table_name, $logger);
        $this->config = $config;
        
        // Load custom limits from config
        $this->load_custom_limits();
    }
    
    /**
     * Load custom rate limits from configuration.
     */
    private function load_custom_limits(): void {
        $custom_limits = $this->config->get('rate_limits', []);
        
        foreach ($custom_limits as $action => $limits) {
            if (isset($limits['requests']) && isset($limits['window'])) {
                $this->default_limits[$action] = [
                    'requests' => (int) $limits['requests'],
                    'window' => (int) $limits['window']
                ];
            }
        }
    }
    
    /**
     * Get rate limit for action.
     *
     * @param string $action Action name.
     * @return array Rate limit configuration.
     */
    public function get_limit_config(string $action): array {
        return $this->default_limits[$action] ?? [
            'requests' => 60,
            'window' => 3600
        ];
    }
    
    /**
     * Check rate limit with configurable thresholds.
     *
     * @param string      $identifier Unique identifier.
     * @param string      $action     Action being limited.
     * @param int|null    $max_requests Override max requests.
     * @param int|null    $window     Override time window.
     * @return bool True if within limits.
     */
    public function check(string $identifier, string $action = 'default', ?int $max_requests = null, ?int $window = null): bool {
        // Get configured limits if not overridden
        if ($max_requests === null || $window === null) {
            $config = $this->get_limit_config($action);
            $max_requests = $max_requests ?? $config['requests'];
            $window = $window ?? $config['window'];
        }
        
        return parent::check($identifier, $action, $max_requests, $window);
    }
    
    /**
     * Update rate limit configuration.
     *
     * @param string $action   Action name.
     * @param int    $requests Max requests.
     * @param int    $window   Time window in seconds.
     * @return bool Success status.
     */
    public function update_limit(string $action, int $requests, int $window): bool {
        // Validate inputs
        if ($requests < 1 || $window < 60) {
            $this->logger->error('Invalid rate limit configuration', [
                'action' => $action,
                'requests' => $requests,
                'window' => $window
            ]);
            return false;
        }
        
        // Update in memory
        $this->default_limits[$action] = [
            'requests' => $requests,
            'window' => $window
        ];
        
        // Save to config
        $all_limits = $this->config->get('rate_limits', []);
        $all_limits[$action] = [
            'requests' => $requests,
            'window' => $window
        ];
        
        return $this->config->set('rate_limits', $all_limits);
    }
    
    /**
     * Get all configured rate limits.
     *
     * @return array All rate limit configurations.
     */
    public function get_all_limits(): array {
        return $this->default_limits;
    }
    
    /**
     * Reset rate limit configuration to defaults.
     *
     * @param string|null $action Specific action or null for all.
     * @return bool Success status.
     */
    public function reset_to_defaults(?string $action = null): bool {
        if ($action !== null) {
            // Reset specific action
            $all_limits = $this->config->get('rate_limits', []);
            unset($all_limits[$action]);
            return $this->config->set('rate_limits', $all_limits);
        }
        
        // Reset all
        return $this->config->delete('rate_limits');
    }
}