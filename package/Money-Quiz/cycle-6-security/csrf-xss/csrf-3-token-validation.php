<?php
/**
 * CSRF Token Validation
 * 
 * @package MoneyQuiz\Security\CSRF
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\CSRF;

/**
 * Token Validator Class
 */
class CsrfTokenValidator implements CsrfConstants {
    
    private $storage;
    private $check_ip = true;
    private $check_agent = true;
    
    public function __construct(CsrfStorageInterface $storage) {
        $this->storage = $storage;
    }
    
    /**
     * Configure validation options
     */
    public function configure($check_ip = true, $check_agent = true) {
        $this->check_ip = $check_ip;
        $this->check_agent = $check_agent;
    }
    
    /**
     * Validate CSRF token
     */
    public function validate($token, $action = self::DEFAULT_ACTION) {
        if (empty($token)) {
            throw new CsrfException('Missing CSRF token', CsrfException::MISSING_TOKEN);
        }
        
        // Retrieve token data
        $data = $this->storage->retrieve($token);
        
        if (!$data) {
            throw new CsrfException('Invalid CSRF token', CsrfException::INVALID_TOKEN);
        }
        
        // Check expiration
        if ($data['expires'] < time()) {
            $this->storage->remove($token);
            throw new CsrfException('Expired CSRF token', CsrfException::EXPIRED_TOKEN);
        }
        
        // Verify action
        if ($data['action'] !== $action) {
            throw new CsrfException('CSRF action mismatch', CsrfException::ACTION_MISMATCH);
        }
        
        // Verify IP if enabled
        if ($this->check_ip && $data['ip'] !== $this->getClientIp()) {
            throw new CsrfException('IP address mismatch', CsrfException::IP_MISMATCH);
        }
        
        // Verify user agent if enabled
        if ($this->check_agent && $data['user_agent'] !== $this->getUserAgent()) {
            throw new CsrfException('User agent mismatch', CsrfException::AGENT_MISMATCH);
        }
        
        // Token is valid - remove it (one-time use)
        $this->storage->remove($token);
        
        return true;
    }
    
    /**
     * Validate from request
     */
    public function validateRequest($method = 'POST') {
        $token = '';
        $action = self::DEFAULT_ACTION;
        
        if ($method === 'POST') {
            $token = $_POST[self::FIELD_NAME] ?? '';
            $action = $_POST[self::ACTION_FIELD] ?? self::DEFAULT_ACTION;
        } else {
            // Check header for API requests
            $headers = getallheaders();
            $token = $headers[self::HEADER_NAME] ?? '';
        }
        
        return $this->validate($token, $action);
    }
    
    /**
     * Get client IP
     */
    private function getClientIp() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return filter_var($ip, FILTER_VALIDATE_IP) ?: '';
            }
        }
        
        return '';
    }
    
    /**
     * Get user agent
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}