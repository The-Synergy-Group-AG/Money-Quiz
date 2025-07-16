<?php
/**
 * HTTPS Enforcer
 * 
 * @package MoneyQuiz\Security\Headers
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Headers;

/**
 * HTTPS Enforcement Class
 */
class HttpsEnforcer {
    
    private $force_ssl = true;
    private $ssl_check_passed = null;
    
    public function __construct($force_ssl = true) {
        $this->force_ssl = $force_ssl;
    }
    
    /**
     * Initialize HTTPS enforcement
     */
    public function init() {
        if (!$this->force_ssl) {
            return;
        }
        
        // Force SSL redirect
        add_action('template_redirect', [$this, 'enforceHttps']);
        
        // Update URLs to HTTPS
        add_filter('home_url', [$this, 'forceHttpsUrl'], 999, 2);
        add_filter('site_url', [$this, 'forceHttpsUrl'], 999, 2);
        add_filter('admin_url', [$this, 'forceHttpsUrl'], 999, 2);
        add_filter('wp_redirect', [$this, 'forceHttpsUrl'], 999, 2);
        
        // Fix mixed content
        add_filter('the_content', [$this, 'fixMixedContent'], 999);
        add_filter('widget_text', [$this, 'fixMixedContent'], 999);
        add_filter('wp_get_attachment_url', [$this, 'forceHttpsUrl'], 999);
        
        // Add admin notice if SSL not available
        add_action('admin_notices', [$this, 'sslNotice']);
    }
    
    /**
     * Enforce HTTPS redirect
     */
    public function enforceHttps() {
        if (!is_ssl() && !is_admin()) {
            $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            
            wp_redirect($redirect_url, 301);
            exit();
        }
    }
    
    /**
     * Force HTTPS in URLs
     */
    public function forceHttpsUrl($url, $path = '') {
        if ($this->shouldForceHttps()) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        return $url;
    }
    
    /**
     * Fix mixed content issues
     */
    public function fixMixedContent($content) {
        if (!is_ssl()) {
            return $content;
        }
        
        // Replace HTTP URLs with HTTPS
        $patterns = [
            '/src="http:\/\//i',
            '/href="http:\/\//i',
            '/url\(\'http:\/\//i',
            '/url\("http:\/\//i'
        ];
        
        $replacements = [
            'src="https://',
            'href="https://',
            "url('https://",
            'url("https://'
        ];
        
        $content = preg_replace($patterns, $replacements, $content);
        
        // Handle protocol-relative URLs
        $content = str_replace('src="//', 'src="https://', $content);
        $content = str_replace('href="//', 'href="https://', $content);
        
        return $content;
    }
    
    /**
     * Check if should force HTTPS
     */
    private function shouldForceHttps() {
        // Don't force on localhost
        if ($this->isLocalhost()) {
            return false;
        }
        
        // Check if SSL is available
        if ($this->ssl_check_passed === null) {
            $this->ssl_check_passed = $this->checkSslAvailable();
        }
        
        return $this->ssl_check_passed;
    }
    
    /**
     * Check if SSL is available
     */
    private function checkSslAvailable() {
        // If already on SSL, it's available
        if (is_ssl()) {
            return true;
        }
        
        // Try to access home URL via HTTPS
        $https_url = str_replace('http://', 'https://', home_url());
        $response = wp_remote_head($https_url, [
            'timeout' => 5,
            'sslverify' => false,
            'redirection' => 0
        ]);
        
        return !is_wp_error($response);
    }
    
    /**
     * Check if localhost
     */
    private function isLocalhost() {
        $whitelist = ['127.0.0.1', '::1'];
        
        if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $whitelist)) {
            return true;
        }
        
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            return (
                strpos($host, 'localhost') !== false ||
                strpos($host, '.local') !== false ||
                strpos($host, '.test') !== false
            );
        }
        
        return false;
    }
    
    /**
     * Show SSL notice in admin
     */
    public function sslNotice() {
        if (!is_ssl() && !$this->isLocalhost() && current_user_can('manage_options')) {
            ?>
            <div class="notice notice-warning">
                <p><strong>Security Warning:</strong> 
                Your site is not using HTTPS. For better security, please enable SSL/TLS on your server.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get SSL status info
     */
    public function getSslStatus() {
        return [
            'is_ssl' => is_ssl(),
            'force_ssl' => $this->force_ssl,
            'ssl_available' => $this->checkSslAvailable(),
            'is_localhost' => $this->isLocalhost()
        ];
    }
}