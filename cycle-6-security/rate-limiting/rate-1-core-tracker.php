<?php
/**
 * Rate Limiting Core Tracker
 * 
 * @package MoneyQuiz\Security\RateLimit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\RateLimit;

/**
 * Rate Limit Interface
 */
interface RateLimitInterface {
    public function track($identifier, $action = 'default');
    public function isAllowed($identifier, $action = 'default');
    public function getRemainingAttempts($identifier, $action = 'default');
    public function reset($identifier, $action = 'default');
}

/**
 * Rate Limit Configuration
 */
class RateLimitConfig {
    
    /**
     * Default rate limits
     */
    private static $defaults = [
        'login' => [
            'attempts' => 5,
            'window' => 900, // 15 minutes
            'lockout' => 3600 // 1 hour
        ],
        'api' => [
            'attempts' => 60,
            'window' => 60, // 1 minute
            'lockout' => 300 // 5 minutes
        ],
        'form_submit' => [
            'attempts' => 10,
            'window' => 300, // 5 minutes
            'lockout' => 600 // 10 minutes
        ],
        'password_reset' => [
            'attempts' => 3,
            'window' => 3600, // 1 hour
            'lockout' => 86400 // 24 hours
        ],
        'default' => [
            'attempts' => 30,
            'window' => 60,
            'lockout' => 300
        ]
    ];
    
    /**
     * Get rate limit config
     */
    public static function get($action = 'default') {
        $config = self::$defaults[$action] ?? self::$defaults['default'];
        
        // Allow filtering
        return apply_filters('money_quiz_rate_limit_config', $config, $action);
    }
    
    /**
     * Get all configs
     */
    public static function getAll() {
        return self::$defaults;
    }
}

/**
 * Rate Tracker
 */
abstract class BaseRateTracker implements RateLimitInterface {
    
    protected $storage;
    
    /**
     * Generate storage key
     */
    protected function getKey($identifier, $action) {
        return 'rate_limit_' . md5($identifier . '_' . $action);
    }
    
    /**
     * Get current timestamp
     */
    protected function now() {
        return time();
    }
    
    /**
     * Track attempt
     */
    public function track($identifier, $action = 'default') {
        $key = $this->getKey($identifier, $action);
        $config = RateLimitConfig::get($action);
        $now = $this->now();
        
        // Get current data
        $data = $this->storage->get($key);
        
        if (!$data) {
            $data = [
                'attempts' => [],
                'locked_until' => 0
            ];
        }
        
        // Remove old attempts outside window
        $window_start = $now - $config['window'];
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        // Add new attempt
        $data['attempts'][] = $now;
        
        // Check if should lock
        if (count($data['attempts']) >= $config['attempts']) {
            $data['locked_until'] = $now + $config['lockout'];
        }
        
        // Store updated data
        $this->storage->set($key, $data, $config['window'] + $config['lockout']);
        
        return count($data['attempts']);
    }
    
    /**
     * Check if allowed
     */
    public function isAllowed($identifier, $action = 'default') {
        $key = $this->getKey($identifier, $action);
        $config = RateLimitConfig::get($action);
        $now = $this->now();
        
        $data = $this->storage->get($key);
        
        if (!$data) {
            return true;
        }
        
        // Check if locked
        if ($data['locked_until'] > $now) {
            return false;
        }
        
        // Count recent attempts
        $window_start = $now - $config['window'];
        $recent_attempts = array_filter($data['attempts'], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        return count($recent_attempts) < $config['attempts'];
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($identifier, $action = 'default') {
        $key = $this->getKey($identifier, $action);
        $config = RateLimitConfig::get($action);
        $now = $this->now();
        
        $data = $this->storage->get($key);
        
        if (!$data) {
            return $config['attempts'];
        }
        
        // Count recent attempts
        $window_start = $now - $config['window'];
        $recent_attempts = array_filter($data['attempts'], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        $remaining = $config['attempts'] - count($recent_attempts);
        
        return max(0, $remaining);
    }
}