<?php
/**
 * Main Security Module Loader
 * 
 * @package MoneyQuiz\Security
 * @version 1.0.0
 */

namespace MoneyQuiz\Security;

/**
 * Security Module Manager
 */
class SecurityManager {
    
    private static $instance = null;
    private $modules = [];
    private $initialized = false;
    
    private function __construct() {
        $this->registerModules();
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
     * Initialize security system
     */
    public static function init() {
        $instance = self::getInstance();
        
        if (!$instance->initialized) {
            $instance->loadModules();
            $instance->setupHooks();
            $instance->initialized = true;
            
            // Log initialization
            do_action('money_quiz_security_initialized');
        }
    }
    
    /**
     * Register security modules
     */
    private function registerModules() {
        $this->modules = [
            'csrf' => [
                'path' => __DIR__ . '/csrf-protection/csrf-5-loader.php',
                'class' => 'MoneyQuiz\Security\CSRF\CsrfProtection',
                'priority' => 10
            ],
            'xss' => [
                'path' => __DIR__ . '/xss-protection/xss-6-loader.php',
                'class' => 'MoneyQuiz\Security\XSS\XssProtection',
                'priority' => 20
            ],
            'sql' => [
                'path' => __DIR__ . '/sql-injection/sql-5-loader.php',
                'class' => 'MoneyQuiz\Security\SQL\SqlProtection',
                'priority' => 30
            ],
            'rate_limit' => [
                'path' => __DIR__ . '/rate-limiting/rate-5-loader.php',
                'class' => 'MoneyQuiz\Security\RateLimit\RateLimiter',
                'priority' => 40
            ],
            'headers' => [
                'path' => __DIR__ . '/security-headers/header-4-loader.php',
                'class' => 'MoneyQuiz\Security\Headers\SecurityHeaders',
                'priority' => 50
            ],
            'audit' => [
                'path' => __DIR__ . '/audit-logging/audit-5-loader.php',
                'class' => 'MoneyQuiz\Security\Audit\AuditLogger',
                'priority' => 60
            ],
            'scanner' => [
                'path' => __DIR__ . '/vulnerability-scanning/scan-loader.php',
                'class' => 'MoneyQuiz\Security\Scanner\VulnerabilityScanner',
                'priority' => 70
            ],
            'testing' => [
                'path' => __DIR__ . '/security-testing/test-5-loader.php',
                'class' => 'MoneyQuiz\Security\Testing\SecurityTestRunner',
                'priority' => 80
            ]
        ];
        
        // Allow modules to be filtered
        $this->modules = apply_filters('money_quiz_security_modules', $this->modules);
    }
    
    /**
     * Load security modules
     */
    private function loadModules() {
        // Sort by priority
        uasort($this->modules, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        foreach ($this->modules as $id => $module) {
            if (file_exists($module['path'])) {
                require_once $module['path'];
                
                // Initialize module
                if (class_exists($module['class']) && method_exists($module['class'], 'init')) {
                    call_user_func([$module['class'], 'init']);
                }
                
                // Log module load
                do_action('money_quiz_security_module_loaded', $id, $module);
            }
        }
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setupHooks() {
        // Core security hooks
        add_action('init', [$this, 'enforceSecurityPolicies'], 5);
        add_action('wp_loaded', [$this, 'validateEnvironment']);
        add_action('admin_init', [$this, 'adminSecurityChecks']);
        
        // Request filtering
        add_action('parse_request', [$this, 'filterRequest'], 1);
        
        // Admin notices
        add_action('admin_notices', [$this, 'displaySecurityNotices']);
        
        // Scheduled tasks
        $this->scheduleSecurityTasks();
    }
    
    /**
     * Enforce security policies
     */
    public function enforceSecurityPolicies() {
        // Force SSL if configured
        if (get_option('money_quiz_force_ssl', false) && !is_ssl()) {
            wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit;
        }
        
        // Apply security headers
        if (class_exists('MoneyQuiz\Security\Headers\SecurityHeaders')) {
            \MoneyQuiz\Security\Headers\SecurityHeaders::getInstance()->applyHeaders();
        }
    }
    
    /**
     * Validate environment
     */
    public function validateEnvironment() {
        $issues = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $issues[] = 'PHP version 7.4 or higher required';
        }
        
        // Check required extensions
        $required_extensions = ['openssl', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "Required PHP extension missing: {$ext}";
            }
        }
        
        // Store validation results
        if (!empty($issues)) {
            set_transient('money_quiz_security_issues', $issues, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Admin security checks
     */
    public function adminSecurityChecks() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check last security scan
        $last_scan = get_option('money_quiz_last_security_scan');
        if (!$last_scan || (time() - $last_scan > 30 * DAY_IN_SECONDS)) {
            set_transient('money_quiz_security_scan_needed', true, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Filter incoming requests
     */
    public function filterRequest($wp) {
        // Check if security filtering is enabled
        if (!apply_filters('money_quiz_security_filtering', true)) {
            return;
        }
        
        // Apply request filters
        do_action('money_quiz_filter_request', $wp);
    }
    
    /**
     * Display security notices
     */
    public function displaySecurityNotices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Environment issues
        $issues = get_transient('money_quiz_security_issues');
        if ($issues) {
            echo '<div class="notice notice-error"><p><strong>Security Issues:</strong> ';
            echo implode(', ', $issues);
            echo '</p></div>';
        }
        
        // Scan needed
        if (get_transient('money_quiz_security_scan_needed')) {
            echo '<div class="notice notice-warning"><p>';
            echo 'Security scan recommended. ';
            echo '<a href="' . admin_url('admin.php?page=money-quiz-scanner') . '">Run Scan</a>';
            echo '</p></div>';
        }
    }
    
    /**
     * Schedule security tasks
     */
    private function scheduleSecurityTasks() {
        // Weekly security scan
        if (!wp_next_scheduled('money_quiz_weekly_security_scan')) {
            wp_schedule_event(time(), 'weekly', 'money_quiz_weekly_security_scan');
        }
        
        // Daily log cleanup
        if (!wp_next_scheduled('money_quiz_daily_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_daily_log_cleanup');
        }
    }
    
    /**
     * Get security status
     */
    public function getSecurityStatus() {
        return [
            'modules' => array_keys($this->modules),
            'initialized' => $this->initialized,
            'last_scan' => get_option('money_quiz_last_security_scan'),
            'security_score' => get_option('money_quiz_security_score', 0)
        ];
    }
}

// Initialize security on plugins_loaded
add_action('plugins_loaded', ['MoneyQuiz\Security\SecurityManager', 'init'], 5);

// Helper function
if (!function_exists('money_quiz_security')) {
    function money_quiz_security() {
        return SecurityManager::getInstance();
    }
}