<?php
/**
 * Rate Limiting Loader
 * 
 * @package MoneyQuiz\Security\RateLimit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\RateLimit;

// Load all components
require_once __DIR__ . '/rate-1-core-tracker.php';
require_once __DIR__ . '/rate-2-storage-backend.php';
require_once __DIR__ . '/rate-3-limit-enforcer.php';
require_once __DIR__ . '/rate-4-ddos-rules.php';

/**
 * Rate Limit Manager
 */
class RateLimitManager {
    
    private static $instance = null;
    private $ip_limiter;
    private $user_limiter;
    private $ddos_protection;
    
    private function __construct() {
        // Choose storage backend
        $storage_type = apply_filters('money_quiz_rate_limit_storage', 'transient');
        
        switch ($storage_type) {
            case 'database':
                $storage = new DatabaseStorage();
                break;
            case 'memory':
                $storage = new MemoryStorage();
                break;
            default:
                $storage = new TransientStorage();
        }
        
        // Initialize components
        $this->ip_limiter = new IpRateLimiter($storage);
        $this->user_limiter = new UserRateLimiter($storage);
        $this->ddos_protection = new DdosProtection($this->ip_limiter);
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize rate limiting
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Add hooks
        add_action('init', [$instance, 'setupHooks']);
        
        // Create database table if needed
        if (apply_filters('money_quiz_rate_limit_storage', 'transient') === 'database') {
            register_activation_hook(__FILE__, [DatabaseStorage::class, 'createTable']);
        }
        
        // Add custom rate limit configs
        self::addCustomConfigs();
    }
    
    /**
     * Setup WordPress hooks
     */
    public function setupHooks() {
        // Check DDoS on every request
        if (apply_filters('money_quiz_ddos_protection', true)) {
            add_action('init', [$this->ddos_protection, 'checkRequest'], 1);
        }
        
        // Login rate limiting
        add_filter('authenticate', [$this, 'checkLoginRateLimit'], 30, 3);
        
        // REST API rate limiting
        add_filter('rest_authentication_errors', [$this, 'checkApiRateLimit']);
        
        // Form submission rate limiting
        add_action('money_quiz_before_form_process', [$this, 'checkFormRateLimit']);
        
        // Add rate limit headers
        add_filter('wp_headers', [$this, 'addRateLimitHeaders']);
    }
    
    /**
     * Check login rate limit
     */
    public function checkLoginRateLimit($user, $username, $password) {
        if (empty($username)) {
            return $user;
        }
        
        try {
            $this->ip_limiter->checkIp('login');
        } catch (RateLimitException $e) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    'Too many login attempts. Please try again in %d seconds.',
                    $e->getRetryAfter()
                )
            );
        }
        
        return $user;
    }
    
    /**
     * Check API rate limit
     */
    public function checkApiRateLimit($result) {
        if (!empty($result)) {
            return $result;
        }
        
        try {
            if (is_user_logged_in()) {
                $this->user_limiter->checkUser(null, 'api');
            } else {
                $this->ip_limiter->checkIp('api');
            }
        } catch (RateLimitException $e) {
            return new \WP_Error(
                'rate_limit_exceeded',
                'API rate limit exceeded',
                [
                    'status' => 429,
                    'retry_after' => $e->getRetryAfter()
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Check form rate limit
     */
    public function checkFormRateLimit() {
        try {
            $this->ip_limiter->checkIp('form_submit');
        } catch (RateLimitException $e) {
            wp_die(
                'Too many form submissions. Please try again later.',
                'Rate Limit Exceeded',
                ['response' => 429]
            );
        }
    }
    
    /**
     * Add rate limit headers
     */
    public function addRateLimitHeaders($headers) {
        $action = $this->getCurrentAction();
        
        if (is_user_logged_in()) {
            $limit_headers = $this->user_limiter->getUserHeaders(null, $action);
        } else {
            $limit_headers = $this->ip_limiter->getIpHeaders($action);
        }
        
        return array_merge($headers, $limit_headers);
    }
    
    /**
     * Get current action context
     */
    private function getCurrentAction() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'api';
        }
        
        if (is_admin()) {
            return 'admin';
        }
        
        return 'default';
    }
    
    /**
     * Add custom rate limit configs
     */
    private static function addCustomConfigs() {
        // Add DDoS protection configs
        add_filter('money_quiz_rate_limit_config', function($config, $action) {
            if ($action === 'ddos_second') {
                return [
                    'attempts' => 10,
                    'window' => 1,
                    'lockout' => 60
                ];
            }
            
            if ($action === 'ddos_minute') {
                return [
                    'attempts' => 100,
                    'window' => 60,
                    'lockout' => 300
                ];
            }
            
            return $config;
        }, 10, 2);
    }
}

// Helper functions
if (!function_exists('money_quiz_rate_limit')) {
    function money_quiz_rate_limit() {
        return RateLimitManager::getInstance();
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [RateLimitManager::class, 'init']);