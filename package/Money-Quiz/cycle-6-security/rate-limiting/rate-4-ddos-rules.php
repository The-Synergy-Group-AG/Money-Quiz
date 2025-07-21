<?php
/**
 * DDoS Protection Rules
 * 
 * @package MoneyQuiz\Security\RateLimit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\RateLimit;

/**
 * DDoS Protection Manager
 */
class DdosProtection {
    
    private $enforcer;
    private $config;
    
    /**
     * DDoS detection thresholds
     */
    private $thresholds = [
        'requests_per_second' => 10,
        'requests_per_minute' => 100,
        'unique_urls_per_minute' => 50,
        'error_rate_threshold' => 0.5, // 50% errors
        'user_agent_changes' => 5,
        'referrer_changes' => 10
    ];
    
    public function __construct(RateLimitEnforcer $enforcer = null) {
        $this->enforcer = $enforcer ?: new IpRateLimiter();
        $this->config = apply_filters('money_quiz_ddos_config', $this->thresholds);
    }
    
    /**
     * Check for DDoS patterns
     */
    public function checkRequest() {
        $ip = $this->getClientIp();
        
        // Check multiple rate limits
        $checks = [
            $this->checkRequestRate($ip),
            $this->checkUrlDiversity($ip),
            $this->checkErrorRate($ip),
            $this->checkUserAgentChanges($ip),
            $this->checkReferrerPatterns($ip)
        ];
        
        foreach ($checks as $check) {
            if (!$check['passed']) {
                $this->handleDdosDetected($ip, $check['reason']);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check request rate
     */
    private function checkRequestRate($ip) {
        try {
            // Per-second check
            $this->enforcer->enforce($ip, 'ddos_second');
            
            // Per-minute check
            $this->enforcer->enforce($ip, 'ddos_minute');
            
            return ['passed' => true];
        } catch (RateLimitException $e) {
            return [
                'passed' => false,
                'reason' => 'Excessive request rate'
            ];
        }
    }
    
    /**
     * Check URL diversity
     */
    private function checkUrlDiversity($ip) {
        $key = 'ddos_urls_' . md5($ip);
        $storage = $this->enforcer->storage;
        
        $data = $storage->get($key) ?: [];
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        
        // Track unique URLs
        if (!in_array($current_url, $data)) {
            $data[] = $current_url;
            $storage->set($key, $data, 60); // 1 minute TTL
        }
        
        if (count($data) > $this->config['unique_urls_per_minute']) {
            return [
                'passed' => false,
                'reason' => 'Too many unique URLs accessed'
            ];
        }
        
        return ['passed' => true];
    }
    
    /**
     * Check error rate
     */
    private function checkErrorRate($ip) {
        $key = 'ddos_errors_' . md5($ip);
        $storage = $this->enforcer->storage;
        
        $data = $storage->get($key) ?: [
            'total' => 0,
            'errors' => 0
        ];
        
        // Increment total
        $data['total']++;
        
        // Check if this is an error response
        if (http_response_code() >= 400) {
            $data['errors']++;
        }
        
        $storage->set($key, $data, 300); // 5 minute TTL
        
        // Calculate error rate
        if ($data['total'] > 10) {
            $error_rate = $data['errors'] / $data['total'];
            
            if ($error_rate > $this->config['error_rate_threshold']) {
                return [
                    'passed' => false,
                    'reason' => 'High error rate detected'
                ];
            }
        }
        
        return ['passed' => true];
    }
    
    /**
     * Check user agent changes
     */
    private function checkUserAgentChanges($ip) {
        $key = 'ddos_agents_' . md5($ip);
        $storage = $this->enforcer->storage;
        
        $agents = $storage->get($key) ?: [];
        $current_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        if (!in_array($current_agent, $agents)) {
            $agents[] = $current_agent;
            $storage->set($key, $agents, 3600); // 1 hour TTL
        }
        
        if (count($agents) > $this->config['user_agent_changes']) {
            return [
                'passed' => false,
                'reason' => 'Suspicious user agent changes'
            ];
        }
        
        return ['passed' => true];
    }
    
    /**
     * Check referrer patterns
     */
    private function checkReferrerPatterns($ip) {
        $key = 'ddos_referrers_' . md5($ip);
        $storage = $this->enforcer->storage;
        
        $referrers = $storage->get($key) ?: [];
        $current_referrer = $_SERVER['HTTP_REFERER'] ?? 'direct';
        
        if (!in_array($current_referrer, $referrers)) {
            $referrers[] = $current_referrer;
            $storage->set($key, $referrers, 3600); // 1 hour TTL
        }
        
        if (count($referrers) > $this->config['referrer_changes']) {
            return [
                'passed' => false,
                'reason' => 'Suspicious referrer pattern'
            ];
        }
        
        return ['passed' => true];
    }
    
    /**
     * Handle DDoS detection
     */
    private function handleDdosDetected($ip, $reason) {
        // Log the detection
        error_log("DDoS detected from IP: {$ip} - Reason: {$reason}");
        
        // Block IP temporarily
        $this->blockIp($ip, 3600); // 1 hour block
        
        // Send alert
        do_action('money_quiz_ddos_detected', $ip, $reason);
        
        // Return 503 response
        http_response_code(503);
        header('Retry-After: 3600');
        
        wp_die(
            'Service temporarily unavailable',
            'Service Unavailable',
            ['response' => 503]
        );
    }
    
    /**
     * Block IP address
     */
    private function blockIp($ip, $duration) {
        $key = 'blocked_ip_' . md5($ip);
        $this->enforcer->storage->set($key, true, $duration);
    }
    
    /**
     * Check if IP is blocked
     */
    public function isIpBlocked($ip = null) {
        if ($ip === null) {
            $ip = $this->getClientIp();
        }
        
        $key = 'blocked_ip_' . md5($ip);
        return (bool) $this->enforcer->storage->get($key);
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
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
}