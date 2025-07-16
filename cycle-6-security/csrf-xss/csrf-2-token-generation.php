<?php
/**
 * CSRF Token Generation
 * 
 * @package MoneyQuiz\Security\CSRF
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\CSRF;

/**
 * Token Generator Class
 */
class CsrfTokenGenerator implements CsrfConstants {
    
    private $storage;
    
    public function __construct(CsrfStorageInterface $storage) {
        $this->storage = $storage;
    }
    
    /**
     * Generate new CSRF token
     */
    public function generate($action = self::DEFAULT_ACTION) {
        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        // Prepare token data
        $data = [
            'action' => $action,
            'expires' => time() + self::TOKEN_LIFETIME,
            'ip' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'created' => time()
        ];
        
        // Store token
        $this->storage->store($token, $data);
        
        // Cleanup old tokens
        $this->storage->cleanup();
        
        return $token;
    }
    
    /**
     * Get client IP address
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
    
    /**
     * Generate token field HTML
     */
    public function getField($action = self::DEFAULT_ACTION, $echo = true) {
        $token = $this->generate($action);
        
        $html = sprintf(
            '<input type="hidden" name="%s" value="%s" />' . PHP_EOL .
            '<input type="hidden" name="%s" value="%s" />',
            self::FIELD_NAME,
            esc_attr($token),
            self::ACTION_FIELD,
            esc_attr($action)
        );
        
        if ($echo) {
            echo $html;
        }
        
        return $html;
    }
    
    /**
     * Generate meta tag
     */
    public function getMetaTag($action = self::DEFAULT_ACTION) {
        $token = $this->generate($action);
        
        return sprintf(
            '<meta name="%s" content="%s" />',
            self::META_NAME,
            esc_attr($token)
        );
    }
}