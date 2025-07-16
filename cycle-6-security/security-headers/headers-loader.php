<?php
/**
 * Security Headers Loader
 * 
 * @package MoneyQuiz\Security\Headers
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Headers;

// Load all components
require_once __DIR__ . '/headers-1-core-definitions.php';
require_once __DIR__ . '/headers-2-header-manager.php';
require_once __DIR__ . '/headers-3-https-enforcer.php';

/**
 * Security Headers Integration
 */
class SecurityHeaders {
    
    private static $instance = null;
    private $manager;
    private $enforcer;
    
    private function __construct() {
        $this->manager = new SecurityHeaderManager();
        $this->enforcer = new HttpsEnforcer(
            apply_filters('money_quiz_force_ssl', true)
        );
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
     * Initialize security headers
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Initialize HTTPS enforcement
        $instance->enforcer->init();
        
        // Add header hooks
        add_action('send_headers', [$instance, 'sendHeaders']);
        add_action('admin_init', [$instance, 'sendAdminHeaders']);
        add_action('rest_api_init', [$instance, 'sendApiHeaders']);
        
        // CSP violation reporting
        add_action('wp_ajax_money_quiz_csp_report', [$instance, 'handleCspReport']);
        add_action('wp_ajax_nopriv_money_quiz_csp_report', [$instance, 'handleCspReport']);
    }
    
    /**
     * Send security headers
     */
    public function sendHeaders() {
        // Skip if headers already sent
        if (headers_sent()) {
            return;
        }
        
        // Apply headers
        $this->manager->applyHeaders();
    }
    
    /**
     * Send admin security headers
     */
    public function sendAdminHeaders() {
        if (!is_admin()) {
            return;
        }
        
        // Adjust CSP for admin
        add_filter('money_quiz_csp_directives', function($directives) {
            // Allow inline scripts for admin
            $directives['script-src'][] = "'unsafe-inline'";
            $directives['style-src'][] = "'unsafe-inline'";
            
            return $directives;
        });
        
        $this->sendHeaders();
    }
    
    /**
     * Send API security headers
     */
    public function sendApiHeaders() {
        // Set CORS headers for API
        $allowed_origins = apply_filters('money_quiz_api_cors_origins', ['*']);
        $allowed_methods = apply_filters('money_quiz_api_cors_methods', 
            ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
        );
        
        $this->manager->setCORSHeaders($allowed_origins, $allowed_methods);
        
        // Remove X-Frame-Options for API
        $this->manager->removeHeader('X-Frame-Options');
        
        $this->sendHeaders();
    }
    
    /**
     * Handle CSP violation reports
     */
    public function handleCspReport() {
        // Verify request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die('Method not allowed', 405);
        }
        
        // Get report data
        $input = file_get_contents('php://input');
        $report = json_decode($input, true);
        
        if (!$report) {
            wp_die('Invalid report', 400);
        }
        
        // Log the violation
        error_log('CSP Violation: ' . print_r($report, true));
        
        // Store in database for analysis
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_csp_reports',
            [
                'report' => $input,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => current_time('mysql')
            ]
        );
        
        wp_die('', 204); // No content
    }
    
    /**
     * Get header manager
     */
    public function getManager() {
        return $this->manager;
    }
    
    /**
     * Get HTTPS enforcer
     */
    public function getEnforcer() {
        return $this->enforcer;
    }
}

// Helper functions
if (!function_exists('money_quiz_add_header')) {
    function money_quiz_add_header($name, $value, $replace = true) {
        $headers = SecurityHeaders::getInstance();
        $headers->getManager()->addHeader($name, $value, $replace);
    }
}

if (!function_exists('money_quiz_ssl_status')) {
    function money_quiz_ssl_status() {
        $headers = SecurityHeaders::getInstance();
        return $headers->getEnforcer()->getSslStatus();
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [SecurityHeaders::class, 'init']);

// Create CSP reports table on activation
register_activation_hook(__FILE__, function() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'money_quiz_csp_reports';
    $charset = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        report TEXT,
        ip VARCHAR(45),
        user_agent TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_timestamp (timestamp)
    ) {$charset};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});