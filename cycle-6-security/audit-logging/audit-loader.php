<?php
/**
 * Audit Logging Loader
 * 
 * @package MoneyQuiz\Security\Audit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Audit;

// Load all components
require_once __DIR__ . '/audit-1-core-logger.php';
require_once __DIR__ . '/audit-2-event-tracker.php';
require_once __DIR__ . '/audit-3-storage-backend.php';
require_once __DIR__ . '/audit-4-report-generator.php';

/**
 * Audit Manager
 */
class AuditManager {
    
    private static $instance = null;
    private $logger;
    private $tracker;
    private $reporter;
    
    private function __construct() {
        // Choose storage backend
        $storage = apply_filters('money_quiz_audit_storage', 'database');
        
        if ($storage === 'file') {
            $this->logger = new FileAuditLogger();
        } else {
            $this->logger = new DatabaseAuditLogger();
        }
        
        // Set minimum log level
        $min_level = apply_filters('money_quiz_audit_min_level', LogLevel::INFO);
        $this->logger->setMinLevel($min_level);
        
        // Initialize components
        $this->tracker = new AuditEventTracker($this->logger);
        $this->reporter = new AuditReportGenerator($this->logger);
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
     * Initialize audit logging
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Create database table if needed
        if (apply_filters('money_quiz_audit_storage', 'database') === 'database') {
            register_activation_hook(__FILE__, [DatabaseAuditLogger::class, 'createTable']);
        }
        
        // Schedule cleanup
        if (!wp_next_scheduled('money_quiz_audit_cleanup')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_audit_cleanup');
        }
        
        add_action('money_quiz_audit_cleanup', [$instance, 'cleanup']);
        
        // Add admin menu
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Register REST endpoints
        add_action('rest_api_init', [$instance, 'registerRestEndpoints']);
    }
    
    /**
     * Get logger
     */
    public function getLogger() {
        return $this->logger;
    }
    
    /**
     * Get tracker
     */
    public function getTracker() {
        return $this->tracker;
    }
    
    /**
     * Get reporter
     */
    public function getReporter() {
        return $this->reporter;
    }
    
    /**
     * Log event
     */
    public function log($event, $level = LogLevel::INFO, array $context = []) {
        return $this->logger->log($event, $level, $context);
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup() {
        if ($this->logger instanceof DatabaseAuditLogger) {
            $this->logger->cleanup();
        }
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'money-quiz',
            'Audit Logs',
            'Audit Logs',
            'manage_options',
            'money-quiz-audit',
            [$this, 'renderAdminPage']
        );
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get logs
        $args = [
            'limit' => 50,
            'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
        ];
        
        if (isset($_GET['level'])) {
            $args['level'] = sanitize_text_field($_GET['level']);
        }
        
        if (isset($_GET['event'])) {
            $args['event'] = sanitize_text_field($_GET['event']);
        }
        
        $logs = $this->logger->query($args);
        
        // Generate report if requested
        if (isset($_GET['report'])) {
            $date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $_GET['to'] ?? date('Y-m-d');
            
            $report = $this->reporter->generateSummary($date_from, $date_to);
        }
        
        include __DIR__ . '/views/audit-logs.php';
    }
    
    /**
     * Register REST endpoints
     */
    public function registerRestEndpoints() {
        register_rest_route('money-quiz/v1', '/audit/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'getLogsEndpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('money-quiz/v1', '/audit/report', [
            'methods' => 'GET',
            'callback' => [$this, 'getReportEndpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Get logs endpoint
     */
    public function getLogsEndpoint($request) {
        $args = [
            'level' => $request->get_param('level'),
            'event' => $request->get_param('event'),
            'user_id' => $request->get_param('user_id'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'limit' => $request->get_param('limit') ?: 100,
            'offset' => $request->get_param('offset') ?: 0
        ];
        
        $logs = $this->logger->query(array_filter($args));
        
        return rest_ensure_response($logs);
    }
    
    /**
     * Get report endpoint
     */
    public function getReportEndpoint($request) {
        $type = $request->get_param('type') ?: 'summary';
        $date_from = $request->get_param('from') ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $request->get_param('to') ?: date('Y-m-d');
        
        if ($type === 'compliance') {
            $report = $this->reporter->generateComplianceReport($date_from, $date_to);
        } else {
            $report = $this->reporter->generateSummary($date_from, $date_to);
        }
        
        return rest_ensure_response($report);
    }
}

// Helper functions
if (!function_exists('money_quiz_audit_log')) {
    function money_quiz_audit_log($event, $level = LogLevel::INFO, array $context = []) {
        return AuditManager::getInstance()->log($event, $level, $context);
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [AuditManager::class, 'init']);