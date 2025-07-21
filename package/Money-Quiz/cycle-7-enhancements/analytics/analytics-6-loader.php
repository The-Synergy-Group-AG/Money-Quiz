<?php
/**
 * Analytics System Loader
 * 
 * @package MoneyQuiz\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\Analytics;

// Load analytics components
require_once __DIR__ . '/analytics-1-data-collector.php';
require_once __DIR__ . '/analytics-2-metric-processor.php';
require_once __DIR__ . '/analytics-3-report-generator.php';
require_once __DIR__ . '/analytics-4-dashboard-api.php';
require_once __DIR__ . '/analytics-5-export-engine.php';

/**
 * Analytics Manager
 */
class AnalyticsManager {
    
    private static $instance = null;
    private $collector;
    private $processor;
    private $generator;
    private $api;
    private $exporter;
    
    private function __construct() {
        $this->collector = DataCollector::getInstance();
        $this->processor = MetricProcessor::getInstance();
        $this->generator = ReportGenerator::getInstance();
        $this->api = DashboardAPI::getInstance();
        $this->exporter = ExportEngine::getInstance();
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
     * Initialize analytics system
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Initialize components
        DataCollector::init();
        DashboardAPI::init();
        
        // Add admin menu
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
        
        // Create database tables
        add_action('plugins_loaded', [$instance, 'createTables']);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$instance, 'addDashboardWidget']);
        
        // Schedule cleanup
        if (!wp_next_scheduled('money_quiz_analytics_cleanup')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_analytics_cleanup');
        }
        
        add_action('money_quiz_analytics_cleanup', [$instance, 'cleanupOldData']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'money-quiz',
            'Analytics',
            'Analytics',
            'edit_posts',
            'money-quiz-analytics',
            [$this, 'renderAnalyticsPage']
        );
        
        add_submenu_page(
            'money-quiz',
            'Reports',
            'Reports',
            'edit_posts',
            'money-quiz-reports',
            [$this, 'renderReportsPage']
        );
    }
    
    /**
     * Render analytics page
     */
    public function renderAnalyticsPage() {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        include __DIR__ . '/views/analytics-dashboard.php';
    }
    
    /**
     * Render reports page
     */
    public function renderReportsPage() {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        // Handle export
        if (isset($_GET['export'])) {
            $this->handleExport();
            return;
        }
        
        include __DIR__ . '/views/reports-page.php';
    }
    
    /**
     * Enqueue assets
     */
    public function enqueueAssets($hook) {
        if (!strpos($hook, 'money-quiz')) {
            return;
        }
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1'
        );
        
        // Analytics scripts
        wp_enqueue_script(
            'money-quiz-analytics',
            plugin_dir_url(__FILE__) . 'assets/analytics.js',
            ['jquery', 'chartjs'],
            '1.0.0',
            true
        );
        
        // Localize
        wp_localize_script('money-quiz-analytics', 'moneyQuizAnalytics', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => rest_url('money-quiz/v1/analytics'),
            'nonce' => wp_create_nonce('money_quiz_analytics')
        ]);
        
        // Styles
        wp_enqueue_style(
            'money-quiz-analytics',
            plugin_dir_url(__FILE__) . 'assets/analytics.css',
            [],
            '1.0.0'
        );
    }
    
    /**
     * Add dashboard widget
     */
    public function addDashboardWidget() {
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'money_quiz_analytics',
            'Money Quiz Analytics',
            [$this, 'renderDashboardWidget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget() {
        $overview = $this->processor->process('total_quizzes', ['period' => 'today']);
        $users = $this->processor->process('active_users', ['period' => 'today']);
        
        ?>
        <div class="money-quiz-widget">
            <div class="stat-row">
                <div class="stat">
                    <h4>Today's Quizzes</h4>
                    <p class="number"><?php echo $overview['total']; ?></p>
                </div>
                <div class="stat">
                    <h4>Active Users</h4>
                    <p class="number"><?php echo $users['total']; ?></p>
                </div>
            </div>
            <p><a href="<?php echo admin_url('admin.php?page=money-quiz-analytics'); ?>">View Full Analytics</a></p>
        </div>
        <?php
    }
    
    /**
     * Handle export
     */
    private function handleExport() {
        $type = $_GET['type'] ?? 'results';
        $format = $_GET['format'] ?? 'csv';
        
        $params = [];
        if (isset($_GET['quiz_id'])) {
            $params['quiz_id'] = intval($_GET['quiz_id']);
        }
        if (isset($_GET['date_from'])) {
            $params['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        if (isset($_GET['date_to'])) {
            $params['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        
        try {
            $this->exporter->export($type, $format, $params);
        } catch (\Exception $e) {
            wp_die('Export error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create database tables
     */
    public function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics events table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_analytics_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(128),
            quiz_id bigint(20),
            metadata longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Analytics pageviews table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_analytics_pageviews (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(128),
            metadata longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY url (url),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Analytics reports table
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_analytics_reports (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }
    
    /**
     * Cleanup old data
     */
    public function cleanupOldData() {
        global $wpdb;
        
        $retention_days = get_option('money_quiz_analytics_retention', 90);
        
        // Delete old events
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days));
        
        // Delete old pageviews
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}money_quiz_analytics_pageviews
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days));
        
        // Delete old reports
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}money_quiz_analytics_reports
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days * 2));
    }
    
    /**
     * Get analytics summary
     */
    public function getSummary($period = 'month') {
        return $this->processor->processAll(['period' => $period]);
    }
    
    /**
     * Track event
     */
    public function trackEvent($event, $data = []) {
        $this->collector->collect($event, $data);
    }
}

// Initialize analytics
add_action('plugins_loaded', [AnalyticsManager::class, 'init']);

// Helper functions
if (!function_exists('money_quiz_track_event')) {
    function money_quiz_track_event($event, $data = []) {
        AnalyticsManager::getInstance()->trackEvent($event, $data);
    }
}

if (!function_exists('money_quiz_get_analytics')) {
    function money_quiz_get_analytics($metric, $params = []) {
        return MetricProcessor::getInstance()->process($metric, $params);
    }
}