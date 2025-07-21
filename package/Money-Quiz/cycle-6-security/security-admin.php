<?php
/**
 * Security Admin Interface
 * 
 * @package MoneyQuiz\Security
 * @version 1.0.0
 */

namespace MoneyQuiz\Security;

/**
 * Security Admin Panel
 */
class SecurityAdmin {
    
    private static $instance = null;
    
    private function __construct() {}
    
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
     * Initialize admin interface
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Add menu pages
        add_action('admin_menu', [$instance, 'addMenuPages']);
        
        // Register settings
        add_action('admin_init', [$instance, 'registerSettings']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
        
        // Handle admin actions
        add_action('admin_post_money_quiz_security_action', [$instance, 'handleAction']);
    }
    
    /**
     * Add menu pages
     */
    public function addMenuPages() {
        // Main security page
        add_submenu_page(
            'money-quiz',
            'Security Settings',
            'Security',
            'manage_options',
            'money-quiz-security',
            [$this, 'renderMainPage']
        );
        
        // Security dashboard
        add_submenu_page(
            'money-quiz',
            'Security Dashboard',
            'Security Dashboard',
            'manage_options',
            'money-quiz-security-dashboard',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings() {
        register_setting(
            'money_quiz_security',
            'money_quiz_security_settings',
            [
                'sanitize_callback' => [SecurityConfig::class, 'validate']
            ]
        );
        
        // General Security Section
        add_settings_section(
            'money_quiz_security_general',
            'General Security Settings',
            [$this, 'renderGeneralSection'],
            'money_quiz_security'
        );
        
        // Protection Modules Section
        add_settings_section(
            'money_quiz_security_modules',
            'Protection Modules',
            [$this, 'renderModulesSection'],
            'money_quiz_security'
        );
        
        // Add fields
        $this->addSettingsFields();
    }
    
    /**
     * Add settings fields
     */
    private function addSettingsFields() {
        // Force SSL
        add_settings_field(
            'force_ssl',
            'Force SSL/HTTPS',
            [$this, 'renderCheckbox'],
            'money_quiz_security',
            'money_quiz_security_general',
            ['field' => 'force_ssl', 'label' => 'Redirect all traffic to HTTPS']
        );
        
        // Hide version
        add_settings_field(
            'hide_version',
            'Hide WordPress Version',
            [$this, 'renderCheckbox'],
            'money_quiz_security',
            'money_quiz_security_general',
            ['field' => 'hide_version', 'label' => 'Remove version info from headers']
        );
        
        // CSRF Protection
        add_settings_field(
            'csrf_enabled',
            'CSRF Protection',
            [$this, 'renderCheckbox'],
            'money_quiz_security',
            'money_quiz_security_modules',
            ['field' => 'csrf_enabled', 'label' => 'Enable CSRF token validation']
        );
        
        // XSS Protection
        add_settings_field(
            'xss_filtering',
            'XSS Filtering',
            [$this, 'renderCheckbox'],
            'money_quiz_security',
            'money_quiz_security_modules',
            ['field' => 'xss_filtering', 'label' => 'Enable XSS input/output filtering']
        );
        
        // Rate Limiting
        add_settings_field(
            'rate_limiting_enabled',
            'Rate Limiting',
            [$this, 'renderCheckbox'],
            'money_quiz_security',
            'money_quiz_security_modules',
            ['field' => 'rate_limiting_enabled', 'label' => 'Enable rate limiting']
        );
    }
    
    /**
     * Render main security page
     */
    public function renderMainPage() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Check for messages
        $message = '';
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'settings_saved':
                    $message = 'Security settings saved successfully.';
                    break;
                case 'scan_complete':
                    $message = 'Security scan completed.';
                    break;
            }
        }
        
        include __DIR__ . '/views/security-settings.php';
    }
    
    /**
     * Render security dashboard
     */
    public function renderDashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get security status
        $security_manager = SecurityManager::getInstance();
        $status = $security_manager->getSecurityStatus();
        
        // Get recent events
        $recent_events = [];
        if (class_exists('MoneyQuiz\Security\Audit\AuditLogger')) {
            $logger = \MoneyQuiz\Security\Audit\AuditLogger::getInstance();
            $recent_events = $logger->getRecentLogs(10);
        }
        
        // Get scan results
        $scan_results = get_option('money_quiz_security_scan_results');
        
        include __DIR__ . '/views/security-dashboard.php';
    }
    
    /**
     * Render checkbox field
     */
    public function renderCheckbox($args) {
        $field = $args['field'];
        $value = SecurityConfig::get($field);
        
        printf(
            '<label><input type="checkbox" name="money_quiz_security_settings[%s]" value="1" %s> %s</label>',
            esc_attr($field),
            checked($value, true, false),
            esc_html($args['label'])
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Render general section
     */
    public function renderGeneralSection() {
        echo '<p>Configure general security settings for your Money Quiz plugin.</p>';
    }
    
    /**
     * Render modules section
     */
    public function renderModulesSection() {
        echo '<p>Enable or disable specific security protection modules.</p>';
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook) {
        if (!strpos($hook, 'money-quiz-security')) {
            return;
        }
        
        // Add custom styles
        wp_add_inline_style('wp-admin', '
            .security-status { padding: 10px; margin: 10px 0; border-radius: 3px; }
            .security-status.good { background: #d4edda; color: #155724; }
            .security-status.warning { background: #fff3cd; color: #856404; }
            .security-status.error { background: #f8d7da; color: #721c24; }
            .security-score { font-size: 48px; font-weight: bold; }
        ');
    }
    
    /**
     * Handle admin actions
     */
    public function handleAction() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_admin_referer('money_quiz_security_action');
        
        $action = $_POST['security_action'] ?? '';
        
        switch ($action) {
            case 'run_scan':
                if (function_exists('money_quiz_security_scan')) {
                    money_quiz_security_scan();
                    $redirect = add_query_arg('message', 'scan_complete', $_POST['_wp_http_referer']);
                }
                break;
                
            case 'reset_settings':
                SecurityConfig::resetToDefaults();
                $redirect = add_query_arg('message', 'settings_reset', $_POST['_wp_http_referer']);
                break;
        }
        
        wp_safe_redirect($redirect ?? $_POST['_wp_http_referer']);
        exit;
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [SecurityAdmin::class, 'init']);