<?php
/**
 * Rate Limit Enforcer
 * 
 * @package MoneyQuiz\Security\RateLimit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\RateLimit;

/**
 * Rate Limit Enforcer
 */
class RateLimitEnforcer extends BaseRateTracker {
    
    public function __construct(StorageInterface $storage = null) {
        $this->storage = $storage ?: new TransientStorage();
    }
    
    /**
     * Reset rate limit
     */
    public function reset($identifier, $action = 'default') {
        $key = $this->getKey($identifier, $action);
        return $this->storage->delete($key);
    }
    
    /**
     * Get lockout time remaining
     */
    public function getLockoutTime($identifier, $action = 'default') {
        $key = $this->getKey($identifier, $action);
        $data = $this->storage->get($key);
        
        if (!$data || !isset($data['locked_until'])) {
            return 0;
        }
        
        $remaining = $data['locked_until'] - $this->now();
        return max(0, $remaining);
    }
    
    /**
     * Check and enforce rate limit
     */
    public function enforce($identifier, $action = 'default') {
        if (!$this->isAllowed($identifier, $action)) {
            $lockout_time = $this->getLockoutTime($identifier, $action);
            
            throw new RateLimitException(
                'Rate limit exceeded',
                429,
                $lockout_time
            );
        }
        
        $this->track($identifier, $action);
        
        return true;
    }
    
    /**
     * Get rate limit headers
     */
    public function getHeaders($identifier, $action = 'default') {
        $config = RateLimitConfig::get($action);
        $remaining = $this->getRemainingAttempts($identifier, $action);
        $lockout_time = $this->getLockoutTime($identifier, $action);
        
        $headers = [
            'X-RateLimit-Limit' => $config['attempts'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Window' => $config['window']
        ];
        
        if ($lockout_time > 0) {
            $headers['X-RateLimit-Reset'] = $this->now() + $lockout_time;
            $headers['Retry-After'] = $lockout_time;
        }
        
        return $headers;
    }
}

/**
 * Rate Limit Exception
 */
class RateLimitException extends \Exception {
    
    private $retryAfter;
    
    public function __construct($message, $code = 429, $retryAfter = 0) {
        parent::__construct($message, $code);
        $this->retryAfter = $retryAfter;
    }
    
    public function getRetryAfter() {
        return $this->retryAfter;
    }
}

/**
 * IP-based Rate Limiter
 */
class IpRateLimiter extends RateLimitEnforcer {
    
    /**
     * Get client IP
     */
    private function getClientIp() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Check IP rate limit
     */
    public function checkIp($action = 'default') {
        $ip = $this->getClientIp();
        return $this->enforce($ip, $action);
    }
    
    /**
     * Get IP rate limit headers
     */
    public function getIpHeaders($action = 'default') {
        $ip = $this->getClientIp();
        return $this->getHeaders($ip, $action);
    }
}

/**
 * User-based Rate Limiter
 */
class UserRateLimiter extends RateLimitEnforcer {
    
    /**
     * Check user rate limit
     */
    public function checkUser($user_id = null, $action = 'default') {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            throw new \InvalidArgumentException('No user ID provided');
        }
        
        $identifier = 'user_' . $user_id;
        return $this->enforce($identifier, $action);
    }
    
    /**
     * Get user rate limit headers
     */
    public function getUserHeaders($user_id = null, $action = 'default') {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $identifier = 'user_' . $user_id;
        return $this->getHeaders($identifier, $action);
    }
}