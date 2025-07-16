<?php
/**
 * Analytics Dashboard API
 * 
 * @package MoneyQuiz\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\Analytics;

/**
 * Dashboard API
 */
class DashboardAPI {
    
    private static $instance = null;
    private $processor;
    private $generator;
    
    private function __construct() {
        $this->processor = MetricProcessor::getInstance();
        $this->generator = ReportGenerator::getInstance();
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
     * Initialize API
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Register REST routes
        add_action('rest_api_init', [$instance, 'registerRoutes']);
        
        // Add AJAX handlers
        add_action('wp_ajax_money_quiz_analytics', [$instance, 'handleAjax']);
    }
    
    /**
     * Register REST routes
     */
    public function registerRoutes() {
        $namespace = 'money-quiz/v1';
        
        // Dashboard overview
        register_rest_route($namespace, '/analytics/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'getDashboard'],
            'permission_callback' => [$this, 'checkPermission']
        ]);
        
        // Specific metrics
        register_rest_route($namespace, '/analytics/metrics/(?P<metric>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getMetric'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getMetricArgs()
        ]);
        
        // Reports
        register_rest_route($namespace, '/analytics/reports', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getReports'],
                'permission_callback' => [$this, 'checkPermission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'generateReport'],
                'permission_callback' => [$this, 'checkPermission']
            ]
        ]);
        
        // Real-time stats
        register_rest_route($namespace, '/analytics/realtime', [
            'methods' => 'GET',
            'callback' => [$this, 'getRealtime'],
            'permission_callback' => [$this, 'checkPermission']
        ]);
    }
    
    /**
     * Get dashboard data
     */
    public function getDashboard($request) {
        $period = $request->get_param('period') ?? 'month';
        
        try {
            $data = [
                'overview' => $this->getOverviewStats($period),
                'charts' => $this->getChartData($period),
                'recent_activity' => $this->getRecentActivity(),
                'top_performers' => $this->getTopPerformers($period)
            ];
            
            return rest_ensure_response([
                'success' => true,
                'data' => $data,
                'generated_at' => current_time('c')
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('analytics_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get specific metric
     */
    public function getMetric($request) {
        $metric = $request->get_param('metric');
        $params = $request->get_params();
        
        try {
            $data = $this->processor->process($metric, $params);
            
            return rest_ensure_response([
                'success' => true,
                'metric' => $metric,
                'data' => $data,
                'parameters' => $params
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('metric_error', $e->getMessage(), ['status' => 400]);
        }
    }
    
    /**
     * Get reports
     */
    public function getReports($request) {
        $limit = $request->get_param('limit') ?? 10;
        
        $reports = $this->generator->getSavedReports($limit);
        
        return rest_ensure_response([
            'success' => true,
            'reports' => $reports
        ]);
    }
    
    /**
     * Generate report
     */
    public function generateReport($request) {
        $template = $request->get_param('template');
        $params = $request->get_params();
        
        try {
            $report = $this->generator->generate($template, $params);
            
            return rest_ensure_response([
                'success' => true,
                'report' => $report
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('report_error', $e->getMessage(), ['status' => 400]);
        }
    }
    
    /**
     * Get realtime stats
     */
    public function getRealtime($request) {
        global $wpdb;
        
        // Active users (last 5 minutes)
        $active_users = $wpdb->get_var("
            SELECT COUNT(DISTINCT session_id)
            FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        
        // Current quizzes in progress
        $in_progress = $wpdb->get_var("
            SELECT COUNT(DISTINCT session_id)
            FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE event_type = 'quiz_start'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND session_id NOT IN (
                SELECT DISTINCT session_id
                FROM {$wpdb->prefix}money_quiz_analytics_events
                WHERE event_type = 'quiz_complete'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            )
        ");
        
        return rest_ensure_response([
            'active_users' => (int) $active_users,
            'quizzes_in_progress' => (int) $in_progress,
            'timestamp' => current_time('c')
        ]);
    }
    
    /**
     * Get overview stats
     */
    private function getOverviewStats($period) {
        return [
            'total_quizzes' => $this->processor->process('total_quizzes', ['period' => $period]),
            'active_users' => $this->processor->process('active_users', ['period' => $period]),
            'completion_rate' => $this->processor->process('completion_rate', ['period' => $period]),
            'average_score' => $this->processor->process('average_score', ['period' => $period])
        ];
    }
    
    /**
     * Get chart data
     */
    private function getChartData($period) {
        $engagement = $this->processor->process('user_engagement', ['period' => $period]);
        
        return [
            'daily_activity' => $this->formatDailyActivity($engagement['daily']),
            'quiz_popularity' => $this->processor->process('popular_quizzes', [
                'period' => $period,
                'limit' => 5
            ])
        ];
    }
    
    /**
     * Format daily activity
     */
    private function formatDailyActivity($daily) {
        $formatted = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Events',
                    'data' => []
                ],
                [
                    'label' => 'Users',
                    'data' => []
                ]
            ]
        ];
        
        foreach ($daily as $day) {
            $formatted['labels'][] = $day->date;
            $formatted['datasets'][0]['data'][] = $day->events;
            $formatted['datasets'][1]['data'][] = $day->users;
        }
        
        return $formatted;
    }
    
    /**
     * Get recent activity
     */
    private function getRecentActivity() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT 
                e.event_type,
                e.created_at,
                u.display_name as user_name,
                q.title as quiz_title
            FROM {$wpdb->prefix}money_quiz_analytics_events e
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON JSON_EXTRACT(e.metadata, '$.quiz_id') = q.id
            WHERE e.event_type IN ('quiz_complete', 'quiz_start')
            ORDER BY e.created_at DESC
            LIMIT 10
        ");
    }
    
    /**
     * Get top performers
     */
    private function getTopPerformers($period) {
        global $wpdb;
        
        $where = $this->getPeriodWhere($period, 'r.completed_at');
        
        return $wpdb->get_results("
            SELECT 
                u.ID as user_id,
                u.display_name,
                COUNT(r.id) as quizzes_completed,
                AVG(r.score) as avg_score
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}money_quiz_results r ON u.ID = r.user_id
            WHERE 1=1 {$where}
            GROUP BY u.ID
            ORDER BY avg_score DESC, quizzes_completed DESC
            LIMIT 10
        ");
    }
    
    /**
     * Get period WHERE clause
     */
    private function getPeriodWhere($period, $column) {
        switch ($period) {
            case 'today':
                return " AND DATE({$column}) = CURDATE()";
            case 'week':
                return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            default:
                return '';
        }
    }
    
    /**
     * Handle AJAX requests
     */
    public function handleAjax() {
        check_ajax_referer('money_quiz_analytics', 'nonce');
        
        $action = $_POST['analytics_action'] ?? '';
        
        switch ($action) {
            case 'get_metric':
                $metric = $_POST['metric'] ?? '';
                $params = $_POST['params'] ?? [];
                
                try {
                    $data = $this->processor->process($metric, $params);
                    wp_send_json_success($data);
                } catch (\Exception $e) {
                    wp_send_json_error($e->getMessage());
                }
                break;
                
            default:
                wp_send_json_error('Unknown action');
        }
    }
    
    /**
     * Check permission
     */
    public function checkPermission() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Get metric arguments
     */
    private function getMetricArgs() {
        return [
            'period' => [
                'default' => 'month',
                'enum' => ['today', 'week', 'month', 'year', 'all']
            ],
            'quiz_id' => [
                'sanitize_callback' => 'absint'
            ],
            'limit' => [
                'default' => 10,
                'sanitize_callback' => 'absint'
            ]
        ];
    }
}