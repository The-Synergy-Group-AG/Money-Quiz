<?php
/**
 * Hybrid Integration - Integrates routing system with Money Quiz plugin
 * 
 * @package MoneyQuiz
 * @subpackage Integration
 * @since 1.5.0
 */

namespace MoneyQuiz;

use MoneyQuiz\Routing\HybridRouter;
use MoneyQuiz\Routing\FeatureFlagManager;
use MoneyQuiz\Routing\Monitoring\RouteMonitor;
use MoneyQuiz\Routing\Rollback\RollbackManager;
use MoneyQuiz\Admin\HybridRoutingAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class HybridIntegration {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Router instance
     */
    private $router;
    
    /**
     * Admin instance
     */
    private $admin;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize autoloader for routing namespace
        $this->register_autoloader();
        
        // Initialize components
        $this->router = new HybridRouter();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin = new HybridRoutingAdmin();
        }
        
        // Load isolated environment helpers if enabled
        if (defined('MONEY_QUIZ_ISOLATED_ENV') && MONEY_QUIZ_ISOLATED_ENV) {
            $this->load_isolated_helpers();
        }
    }
    
    /**
     * Initialize hybrid routing system
     */
    public function init() {
        // Check if hybrid routing is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Initialize router
        $this->router->init();
        
        // Hook into plugin actions
        $this->setup_hooks();
        
        // Set migration start date if not set
        if (!get_option('mq_migration_start_date')) {
            update_option('mq_migration_start_date', current_time('mysql'));
            update_option('mq_hybrid_week', 1);
        }
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Fire initialization complete action
        do_action('mq_hybrid_routing_initialized');
    }
    
    /**
     * Register autoloader for routing classes
     */
    private function register_autoloader() {
        spl_autoload_register(function ($class) {
            // Check if it's our namespace
            if (strpos($class, 'MoneyQuiz\\') !== 0) {
                return;
            }
            
            // Convert namespace to file path
            $relative_class = substr($class, strlen('MoneyQuiz\\'));
            $file = MONEY_QUIZ_PLUGIN_DIR . 'includes/' . 
                    str_replace('\\', '/', strtolower($relative_class)) . '.php';
            
            // Alternative file naming (class-name.php format)
            if (!file_exists($file)) {
                $parts = explode('\\', $relative_class);
                $class_name = array_pop($parts);
                $path = implode('/', array_map('strtolower', $parts));
                
                // Convert CamelCase to hyphenated
                $class_file = 'class-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name)) . '.php';
                $file = MONEY_QUIZ_PLUGIN_DIR . 'includes/' . 
                        ($path ? $path . '/' : '') . $class_file;
            }
            
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
    
    /**
     * Check if hybrid routing is enabled
     */
    private function is_enabled() {
        // Check for emergency disable
        if (defined('MONEY_QUIZ_DISABLE_HYBRID') && MONEY_QUIZ_DISABLE_HYBRID) {
            return false;
        }
        
        // Check option
        return get_option('mq_hybrid_routing_enabled', true);
    }
    
    /**
     * Setup hooks for integration
     */
    private function setup_hooks() {
        // Hook into existing Money Quiz actions
        add_filter('mq_handle_request', [$this, 'route_request'], 10, 2);
        add_filter('mq_safe_wrapper_action', [$this, 'check_routing'], 10, 2);
        
        // Add routing info to admin bar
        add_action('admin_bar_menu', [$this, 'add_admin_bar_info'], 100);
        
        // Integration with safe mode
        add_filter('mq_use_modern_system', [$this, 'determine_system'], 10, 3);
    }
    
    /**
     * Route request through hybrid system
     * 
     * @param mixed $result Current result
     * @param array $request Request data
     * @return mixed
     */
    public function route_request($result, $request) {
        // Extract action and data
        $action = $request['action'] ?? '';
        $data = $request['data'] ?? [];
        
        // Check if this action should be routed
        if (!$this->should_route($action)) {
            return $result;
        }
        
        // Route through hybrid router
        try {
            $routed_result = $this->router->route($action, $data);
            
            // Merge with original result if needed
            if (is_array($result) && is_array($routed_result)) {
                return array_merge($result, $routed_result);
            }
            
            return $routed_result;
            
        } catch (\Exception $e) {
            // Log error and return original result
            error_log('Hybrid routing error: ' . $e->getMessage());
            return $result;
        }
    }
    
    /**
     * Check if action should be routed
     * 
     * @param string $action
     * @return bool
     */
    private function should_route($action) {
        $routable_actions = [
            'quiz_display',
            'quiz_list',
            'quiz_submit',
            'quiz_results',
            'archetype_fetch',
            'archetype_list',
            'statistics_view',
            'statistics_summary'
        ];
        
        return in_array($action, $routable_actions);
    }
    
    /**
     * Check routing before safe wrapper processes
     * 
     * @param string $action
     * @param array $data
     * @return string|null Modified action or null
     */
    public function check_routing($action, $data) {
        // This allows us to intercept before safe wrapper
        if ($this->should_route($action)) {
            // Add routing metadata
            add_filter('mq_request_metadata', function($metadata) {
                $metadata['routed'] = true;
                $metadata['router_version'] = '1.5.0';
                return $metadata;
            });
        }
        
        return $action;
    }
    
    /**
     * Determine which system to use
     * 
     * @param bool|null $use_modern Current decision
     * @param string $action
     * @param array $data
     * @return bool|null
     */
    public function determine_system($use_modern, $action, $data) {
        // Let feature flags decide
        $feature_flags = new FeatureFlagManager();
        
        // Map actions to feature flags
        $action_map = [
            'quiz_display' => 'modern_quiz_display',
            'quiz_submit' => 'modern_quiz_submit',
            'quiz_results' => 'modern_quiz_results',
            'archetype_fetch' => 'modern_archetype_fetch',
            'statistics_view' => 'modern_statistics'
        ];
        
        if (isset($action_map[$action])) {
            $flag = $action_map[$action];
            return $feature_flags->is_enabled($flag);
        }
        
        return $use_modern;
    }
    
    /**
     * Add admin bar info
     * 
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function add_admin_bar_info($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $monitor = new RouteMonitor();
        $health = $monitor->get_system_health();
        
        $title = sprintf(
            'MQ Routing: %s',
            ucfirst($health['status'])
        );
        
        $wp_admin_bar->add_node([
            'id' => 'mq-routing-status',
            'title' => $title,
            'href' => admin_url('admin.php?page=mq-routing-control'),
            'meta' => [
                'class' => 'mq-routing-status-' . $health['status']
            ]
        ]);
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Add custom cron schedule
        add_filter('cron_schedules', function($schedules) {
            $schedules['mq_five_minutes'] = [
                'interval' => 300,
                'display' => 'Every 5 minutes'
            ];
            return $schedules;
        });
        
        // Schedule health checks
        if (!wp_next_scheduled('mq_check_routing_health')) {
            wp_schedule_event(time(), 'mq_five_minutes', 'mq_check_routing_health');
        }
        
        // Schedule metric aggregation
        if (!wp_next_scheduled('mq_aggregate_metrics')) {
            wp_schedule_event(time(), 'hourly', 'mq_aggregate_metrics');
        }
        
        // Schedule cleanup
        if (!wp_next_scheduled('mq_cleanup_old_metrics')) {
            wp_schedule_event(time(), 'daily', 'mq_cleanup_old_metrics');
        }
    }
    
    /**
     * Activation hook
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        $instance = self::instance();
        $instance->schedule_cron_jobs();
    }
    
    /**
     * Deactivation hook
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('mq_check_routing_health');
        wp_clear_scheduled_hook('mq_aggregate_metrics');
        wp_clear_scheduled_hook('mq_cleanup_old_metrics');
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Routing metrics table
        $table_metrics = $wpdb->prefix . 'mq_routing_metrics';
        $sql_metrics = "CREATE TABLE IF NOT EXISTS $table_metrics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            system varchar(20) NOT NULL,
            action varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            duration float DEFAULT NULL,
            memory int(11) DEFAULT NULL,
            error_type varchar(100) DEFAULT NULL,
            error_message text DEFAULT NULL,
            error_file varchar(255) DEFAULT NULL,
            error_line int(11) DEFAULT NULL,
            context text DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_system_status (system, status),
            KEY idx_action (action)
        ) $charset_collate;";
        
        // Rollback events table
        $table_rollback = $wpdb->prefix . 'mq_rollback_events';
        $sql_rollback = "CREATE TABLE IF NOT EXISTS $table_rollback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            rollback_type varchar(20) NOT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_details text,
            metrics_snapshot text,
            user_id bigint(20) DEFAULT 0,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_type (rollback_type)
        ) $charset_collate;";
        
        // Feature assignments table
        $table_assignments = $wpdb->prefix . 'mq_feature_assignments';
        $sql_assignments = "CREATE TABLE IF NOT EXISTS $table_assignments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feature varchar(50) NOT NULL,
            user_identifier varchar(100) NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 0,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_feature_user (feature, user_identifier),
            KEY idx_timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_metrics);
        dbDelta($sql_rollback);
        dbDelta($sql_assignments);
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        // Hybrid routing enabled by default
        add_option('mq_hybrid_routing_enabled', true);
        
        // Feature flags start at 100% for isolated environment
        add_option('mq_feature_flags', [
            'modern_quiz_display' => 1.0,
            'modern_quiz_list' => 1.0,
            'modern_archetype_fetch' => 1.0,
            'modern_statistics' => 1.0,
            'modern_quiz_submit' => 1.0,
            'modern_prospect_save' => 1.0,
            'modern_email_send' => 1.0
        ]);
        
        // Rollback configuration
        add_option('mq_rollback_config', [
            'auto_rollback' => true,
            'manual_override' => true,
            'notification_emails' => [get_option('admin_email')],
            'cooldown_minutes' => 60,
            'error_threshold' => 0.05,
            'response_threshold' => 5.0,
            'memory_threshold' => 256
        ]);
    }
    
    /**
     * Get instance (for external access)
     */
    public function get_router() {
        return $this->router;
    }
    
    /**
     * Load isolated environment helpers
     */
    private function load_isolated_helpers() {
        // Load isolated helper class
        if (file_exists(MONEY_QUIZ_PLUGIN_DIR . 'includes/isolated/class-isolated-environment-helper.php')) {
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/isolated/class-isolated-environment-helper.php';
        }
        
        // Load isolated menu config
        if (file_exists(MONEY_QUIZ_PLUGIN_DIR . 'includes/admin/menu-redesign/isolated-menu-config.php')) {
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/admin/menu-redesign/isolated-menu-config.php';
        }
    }
}