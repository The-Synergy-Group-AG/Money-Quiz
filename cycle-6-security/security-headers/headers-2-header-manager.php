<?php
/**
 * Security Header Manager
 * 
 * @package MoneyQuiz\Security\Headers
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Headers;

/**
 * Header Manager Class
 */
class SecurityHeaderManager implements SecurityHeaderConstants {
    
    private $headers = [];
    private $csp_directives = [];
    
    /**
     * Add security header
     */
    public function addHeader($name, $value, $replace = true) {
        if ($replace || !isset($this->headers[$name])) {
            $this->headers[$name] = $value;
        }
    }
    
    /**
     * Remove header
     */
    public function removeHeader($name) {
        unset($this->headers[$name]);
    }
    
    /**
     * Get all headers
     */
    public function getHeaders() {
        return $this->headers;
    }
    
    /**
     * Apply headers
     */
    public function applyHeaders() {
        // Load default headers
        $this->loadDefaultHeaders();
        
        // Apply each header
        foreach ($this->headers as $name => $value) {
            if (!headers_sent()) {
                header("{$name}: {$value}");
            }
        }
        
        // Log headers applied
        do_action('money_quiz_headers_applied', $this->headers);
    }
    
    /**
     * Load default headers
     */
    private function loadDefaultHeaders() {
        $defaults = HeaderConfig::getDefaults();
        
        foreach ($defaults as $header => $config) {
            if (HeaderConfig::shouldApplyHeader($header)) {
                $this->addHeader($header, $config['value']);
            }
        }
    }
    
    /**
     * Build Content Security Policy
     */
    public function buildCSP() {
        $directives = apply_filters('money_quiz_csp_directives', [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'media-src' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'upgrade-insecure-requests' => []
        ]);
        
        $csp_parts = [];
        
        foreach ($directives as $directive => $values) {
            if (empty($values) && $directive === 'upgrade-insecure-requests') {
                $csp_parts[] = $directive;
            } elseif (!empty($values)) {
                $csp_parts[] = $directive . ' ' . implode(' ', $values);
            }
        }
        
        return implode('; ', $csp_parts);
    }
    
    /**
     * Set CORS headers
     */
    public function setCORSHeaders($allowed_origins = ['*'], $allowed_methods = ['GET', 'POST']) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (in_array('*', $allowed_origins) || in_array($origin, $allowed_origins)) {
            $this->addHeader(self::ACCESS_CONTROL_ALLOW_ORIGIN, $origin ?: '*');
        }
        
        // Set allowed methods
        $this->addHeader(
            self::ACCESS_CONTROL_ALLOW_METHODS, 
            implode(', ', $allowed_methods)
        );
        
        // Set allowed headers
        $this->addHeader(
            self::ACCESS_CONTROL_ALLOW_HEADERS,
            'Content-Type, Authorization, X-Requested-With'
        );
        
        // Set max age
        $this->addHeader(self::ACCESS_CONTROL_MAX_AGE, '86400');
    }
    
    /**
     * Set HSTS header
     */
    public function setHSTS($max_age = 31536000, $include_subdomains = true, $preload = false) {
        if (!is_ssl()) {
            return;
        }
        
        $value = "max-age={$max_age}";
        
        if ($include_subdomains) {
            $value .= '; includeSubDomains';
        }
        
        if ($preload) {
            $value .= '; preload';
        }
        
        $this->addHeader(self::STRICT_TRANSPORT_SECURITY, $value);
    }
    
    /**
     * Set frame options
     */
    public function setFrameOptions($option = 'SAMEORIGIN') {
        $valid_options = ['DENY', 'SAMEORIGIN'];
        
        if (in_array($option, $valid_options)) {
            $this->addHeader(self::X_FRAME_OPTIONS, $option);
        } elseif (strpos($option, 'ALLOW-FROM') === 0) {
            $this->addHeader(self::X_FRAME_OPTIONS, $option);
        }
    }
    
    /**
     * Report CSP violations
     */
    public function setCSPReporting($report_uri) {
        $csp = $this->buildCSP();
        $csp .= "; report-uri {$report_uri}";
        $this->addHeader(self::CONTENT_SECURITY_POLICY, $csp);
    }
}