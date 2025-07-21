<?php
/**
 * Rate Limiter for MoneyQuiz Plugin
 * 
 * Prevents brute force attacks and abuse of sensitive operations
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Rate_Limiter {
    
    /**
     * Rate limit settings
     */
    const RATE_LIMIT_WINDOW = 300; // 5 minutes
    const MAX_ATTEMPTS_PER_WINDOW = 10;
    const LOCKOUT_DURATION = 900; // 15 minutes
    
    /**
     * Check if action is rate limited
     * 
     * @param string $action The action being rate limited
     * @param string $identifier User identifier (IP, user ID, etc.)
     * @return bool True if rate limited, false otherwise
     */
    public static function is_rate_limited($action, $identifier = null) {
        if (empty($identifier)) {
            $identifier = self::get_user_identifier();
        }
        
        $key = "moneyquiz_rate_limit_{$action}_{$identifier}";
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, self::RATE_LIMIT_WINDOW);
            return false;
        }
        
        if ($attempts >= self::MAX_ATTEMPTS_PER_WINDOW) {
            // Check if lockout period has expired
            $lockout_key = "moneyquiz_lockout_{$action}_{$identifier}";
            $lockout_time = get_transient($lockout_key);
            
            if ($lockout_time === false) {
                // Set lockout
                set_transient($lockout_key, time(), self::LOCKOUT_DURATION);
                return true;
            }
            
            // Still in lockout period
            return true;
        }
        
        // Increment attempts
        set_transient($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
        return false;
    }
    
    /**
     * Get user identifier (IP address)
     * 
     * @return string
     */
    private static function get_user_identifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Handle proxy headers
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Clear rate limit for an action
     * 
     * @param string $action The action
     * @param string $identifier User identifier
     */
    public static function clear_rate_limit($action, $identifier = null) {
        if (empty($identifier)) {
            $identifier = self::get_user_identifier();
        }
        
        $key = "moneyquiz_rate_limit_{$action}_{$identifier}";
        $lockout_key = "moneyquiz_lockout_{$action}_{$identifier}";
        
        delete_transient($key);
        delete_transient($lockout_key);
    }
    
    /**
     * Get remaining attempts for an action
     * 
     * @param string $action The action
     * @param string $identifier User identifier
     * @return int Remaining attempts
     */
    public static function get_remaining_attempts($action, $identifier = null) {
        if (empty($identifier)) {
            $identifier = self::get_user_identifier();
        }
        
        $key = "moneyquiz_rate_limit_{$action}_{$identifier}";
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            return self::MAX_ATTEMPTS_PER_WINDOW;
        }
        
        return max(0, self::MAX_ATTEMPTS_PER_WINDOW - $attempts);
    }
    
    /**
     * Check if user is locked out
     * 
     * @param string $action The action
     * @param string $identifier User identifier
     * @return bool True if locked out
     */
    public static function is_locked_out($action, $identifier = null) {
        if (empty($identifier)) {
            $identifier = self::get_user_identifier();
        }
        
        $lockout_key = "moneyquiz_lockout_{$action}_{$identifier}";
        return get_transient($lockout_key) !== false;
    }
    
    /**
     * Get lockout time remaining
     * 
     * @param string $action The action
     * @param string $identifier User identifier
     * @return int Seconds remaining in lockout
     */
    public static function get_lockout_time_remaining($action, $identifier = null) {
        if (empty($identifier)) {
            $identifier = self::get_user_identifier();
        }
        
        $lockout_key = "moneyquiz_lockout_{$action}_{$identifier}";
        $lockout_time = get_transient($lockout_key);
        
        if ($lockout_time === false) {
            return 0;
        }
        
        $elapsed = time() - $lockout_time;
        return max(0, self::LOCKOUT_DURATION - $elapsed);
    }
} 