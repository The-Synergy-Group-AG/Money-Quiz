<?php
/**
 * Security Headers for MoneyQuiz Plugin
 * 
 * Manages security headers and Content Security Policy
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Security_Headers {
    
    /**
     * Initialize security headers
     */
    public static function init() {
        add_action('send_headers', [__CLASS__, 'set_security_headers']);
        add_action('wp_head', [__CLASS__, 'add_csp_meta_tag']);
    }
    
    /**
     * Set security headers
     */
    public static function set_security_headers() {
        // Content Security Policy
        $csp = self::get_csp_policy();
        header("Content-Security-Policy: $csp");
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // Strict-Transport-Security (HTTPS only)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
    
    /**
     * Get Content Security Policy
     * 
     * @return string CSP policy string
     */
    private static function get_csp_policy() {
        $csp_directives = [
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'",
                "'unsafe-inline'", // Required for WordPress
                "'unsafe-eval'",   // Required for some WordPress features
                'https://www.google.com',
                'https://www.gstatic.com'
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'", // Required for WordPress
                'https://fonts.googleapis.com'
            ],
            'font-src' => [
                "'self'",
                'https://fonts.gstatic.com',
                'data:'
            ],
            'img-src' => [
                "'self'",
                'data:',
                'https:',
                'blob:'
            ],
            'connect-src' => [
                "'self'",
                'https://api.x.ai',
                'https://www.googleapis.com'
            ],
            'frame-src' => [
                "'self'",
                'https://www.google.com',
                'https://www.youtube.com'
            ],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'upgrade-insecure-requests' => []
        ];
        
        $csp_parts = [];
        
        foreach ($csp_directives as $directive => $sources) {
            if (empty($sources)) {
                $csp_parts[] = $directive;
            } else {
                $csp_parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }
        
        return implode('; ', $csp_parts);
    }
    
    /**
     * Add CSP meta tag for older browsers
     */
    public static function add_csp_meta_tag() {
        $csp = self::get_csp_policy();
        echo '<meta http-equiv="Content-Security-Policy" content="' . esc_attr($csp) . '">' . "\n";
    }
    
    /**
     * Add custom CSP directive
     * 
     * @param string $directive CSP directive
     * @param array $sources Allowed sources
     */
    public static function add_csp_directive($directive, $sources) {
        $csp = self::get_csp_policy();
        $new_directive = $directive . ' ' . implode(' ', $sources);
        
        // Add to existing CSP
        $csp .= '; ' . $new_directive;
        
        // Set header
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Remove CSP directive
     * 
     * @param string $directive CSP directive to remove
     */
    public static function remove_csp_directive($directive) {
        $csp = self::get_csp_policy();
        
        // Remove directive from CSP
        $csp = preg_replace("/$directive[^;]*;?\s*/", '', $csp);
        
        // Set header
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Add nonce to CSP for inline scripts
     * 
     * @param string $nonce Nonce value
     */
    public static function add_nonce_to_csp($nonce) {
        $csp = self::get_csp_policy();
        
        // Add nonce to script-src
        $csp = str_replace(
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' 'nonce-$nonce'",
            $csp
        );
        
        // Set header
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Add hash to CSP for inline scripts
     * 
     * @param string $hash SHA hash of script content
     */
    public static function add_hash_to_csp($hash) {
        $csp = self::get_csp_policy();
        
        // Add hash to script-src
        $csp = str_replace(
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' 'sha256-$hash'",
            $csp
        );
        
        // Set header
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Generate nonce for CSP
     * 
     * @return string Nonce value
     */
    public static function generate_nonce() {
        return base64_encode(wp_create_nonce('moneyquiz_csp_nonce'));
    }
    
    /**
     * Verify nonce for CSP
     * 
     * @param string $nonce Nonce to verify
     * @return bool True if valid
     */
    public static function verify_nonce($nonce) {
        return wp_verify_nonce($nonce, 'moneyquiz_csp_nonce');
    }
    
    /**
     * Add security headers for AJAX requests
     */
    public static function add_ajax_security_headers() {
        // Prevent caching of AJAX responses
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Add CSRF protection header
        header('X-CSRF-Token: ' . wp_create_nonce('moneyquiz_ajax_nonce'));
    }
    
    /**
     * Add security headers for admin pages
     */
    public static function add_admin_security_headers() {
        // Additional security for admin pages
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Download-Options: noopen');
        header('X-Permitted-Cross-Domain-Policies: none');
    }
    
    /**
     * Add security headers for API endpoints
     */
    public static function add_api_security_headers() {
        // Security headers for API responses
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // CORS headers if needed
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $allowed_origins = [
                home_url(),
                admin_url(),
                site_url()
            ];
            
            if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
            }
        }
    }
} 