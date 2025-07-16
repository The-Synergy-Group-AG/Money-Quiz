<?php
/**
 * Analytics Metric Processor
 * 
 * @package MoneyQuiz\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\Analytics;

/**
 * Metric Processor
 */
class MetricProcessor {
    
    private static $instance = null;
    private $metrics = [];
    
    private function __construct() {
        $this->registerMetrics();
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
     * Register available metrics
     */
    private function registerMetrics() {
        $this->metrics = [
            'total_quizzes' => ['method' => 'calculateTotalQuizzes'],
            'active_users' => ['method' => 'calculateActiveUsers'],
            'completion_rate' => ['method' => 'calculateCompletionRate'],
            'average_score' => ['method' => 'calculateAverageScore'],
            'popular_quizzes' => ['method' => 'calculatePopularQuizzes'],
            'user_engagement' => ['method' => 'calculateEngagement'],
            'time_metrics' => ['method' => 'calculateTimeMetrics'],
            'question_performance' => ['method' => 'calculateQuestionPerformance']
        ];
    }
    
    /**
     * Process metric
     */
    public function process($metric, $params = []) {
        if (!isset($this->metrics[$metric])) {
            throw new \Exception("Unknown metric: {$metric}");
        }
        
        $method = $this->metrics[$metric]['method'];
        
        if (!method_exists($this, $method)) {
            throw new \Exception("Method not found: {$method}");
        }
        
        return $this->$method($params);
    }
    
    /**
     * Calculate total quizzes
     */
    private function calculateTotalQuizzes($params) {
        global $wpdb;
        
        $period = $params['period'] ?? 'all';
        $where = $this->getPeriodWhere($period);
        
        return [
            'total' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_quizzes
                WHERE 1=1 {$where}
            "),
            'published' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_quizzes
                WHERE status = 'published' {$where}
            "),
            'draft' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_quizzes
                WHERE status = 'draft' {$where}
            ")
        ];
    }
    
    /**
     * Calculate active users
     */
    private function calculateActiveUsers($params) {
        global $wpdb;
        
        $period = $params['period'] ?? 'month';
        $where = $this->getPeriodWhere($period, 'r.completed_at');
        
        return [
            'total' => $wpdb->get_var("
                SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->prefix}money_quiz_results r
                WHERE user_id > 0 {$where}
            "),
            'new' => $wpdb->get_var("
                SELECT COUNT(DISTINCT u.ID)
                FROM {$wpdb->users} u
                WHERE 1=1 " . $this->getPeriodWhere($period, 'u.user_registered')
            ),
            'returning' => $wpdb->get_var("
                SELECT COUNT(DISTINCT user_id)
                FROM {$wpdb->prefix}money_quiz_results
                WHERE user_id IN (
                    SELECT DISTINCT user_id 
                    FROM {$wpdb->prefix}money_quiz_results
                    WHERE completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) {$where}
            ")
        ];
    }
    
    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate($params) {
        global $wpdb;
        
        $quiz_id = $params['quiz_id'] ?? null;
        $period = $params['period'] ?? 'month';
        
        $where = $this->getPeriodWhere($period, 'created_at');
        if ($quiz_id) {
            $where .= $wpdb->prepare(' AND quiz_id = %d', $quiz_id);
        }
        
        $started = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE event_type = 'quiz_start' {$where}
        ");
        
        $completed = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE event_type = 'quiz_complete' {$where}
        ");
        
        return [
            'started' => (int) $started,
            'completed' => (int) $completed,
            'rate' => $started > 0 ? round(($completed / $started) * 100, 2) : 0
        ];
    }
    
    /**
     * Calculate average score
     */
    private function calculateAverageScore($params) {
        global $wpdb;
        
        $quiz_id = $params['quiz_id'] ?? null;
        $period = $params['period'] ?? 'all';
        
        $where = $this->getPeriodWhere($period, 'completed_at');
        if ($quiz_id) {
            $where .= $wpdb->prepare(' AND quiz_id = %d', $quiz_id);
        }
        
        $data = $wpdb->get_row("
            SELECT 
                AVG(score) as average,
                MIN(score) as minimum,
                MAX(score) as maximum,
                COUNT(*) as total
            FROM {$wpdb->prefix}money_quiz_results
            WHERE 1=1 {$where}
        ");
        
        return [
            'average' => round($data->average ?: 0, 2),
            'minimum' => (int) $data->minimum,
            'maximum' => (int) $data->maximum,
            'total_attempts' => (int) $data->total
        ];
    }
    
    /**
     * Calculate popular quizzes
     */
    private function calculatePopularQuizzes($params) {
        global $wpdb;
        
        $limit = $params['limit'] ?? 10;
        $period = $params['period'] ?? 'month';
        
        $where = $this->getPeriodWhere($period, 'r.completed_at');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                q.id,
                q.title,
                COUNT(r.id) as attempts,
                AVG(r.score) as avg_score,
                COUNT(DISTINCT r.user_id) as unique_users
            FROM {$wpdb->prefix}money_quiz_quizzes q
            LEFT JOIN {$wpdb->prefix}money_quiz_results r ON q.id = r.quiz_id
            WHERE q.status = 'published' {$where}
            GROUP BY q.id
            ORDER BY attempts DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Calculate engagement metrics
     */
    private function calculateEngagement($params) {
        global $wpdb;
        
        $period = $params['period'] ?? 'week';
        $where = $this->getPeriodWhere($period, 'created_at');
        
        // Get daily engagement
        $daily = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as events,
                COUNT(DISTINCT user_id) as users,
                COUNT(DISTINCT session_id) as sessions
            FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE 1=1 {$where}
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        
        return [
            'daily' => $daily,
            'summary' => [
                'total_events' => array_sum(array_column($daily, 'events')),
                'unique_users' => $wpdb->get_var("
                    SELECT COUNT(DISTINCT user_id) 
                    FROM {$wpdb->prefix}money_quiz_analytics_events
                    WHERE 1=1 {$where}
                "),
                'avg_events_per_user' => $this->calculateAverageEventsPerUser($period)
            ]
        ];
    }
    
    /**
     * Calculate time metrics
     */
    private function calculateTimeMetrics($params) {
        global $wpdb;
        
        $quiz_id = $params['quiz_id'] ?? null;
        
        $where = '';
        if ($quiz_id) {
            $where = $wpdb->prepare('WHERE quiz_id = %d', $quiz_id);
        }
        
        return $wpdb->get_row("
            SELECT 
                AVG(time_taken) as avg_time,
                MIN(time_taken) as min_time,
                MAX(time_taken) as max_time,
                STDDEV(time_taken) as std_dev
            FROM {$wpdb->prefix}money_quiz_results
            {$where}
        ");
    }
    
    /**
     * Calculate question performance
     */
    private function calculateQuestionPerformance($params) {
        global $wpdb;
        
        $quiz_id = $params['quiz_id'] ?? null;
        $limit = $params['limit'] ?? 20;
        
        $where = '';
        if ($quiz_id) {
            $where = $wpdb->prepare('AND q.quiz_id = %d', $quiz_id);
        }
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                q.id,
                q.text as question,
                COUNT(e.id) as total_answers,
                SUM(CASE WHEN e.metadata LIKE '%%\"is_correct\":true%%' THEN 1 ELSE 0 END) as correct_answers,
                AVG(JSON_EXTRACT(e.metadata, '$.time_taken')) as avg_time
            FROM {$wpdb->prefix}money_quiz_questions q
            LEFT JOIN {$wpdb->prefix}money_quiz_analytics_events e ON q.id = JSON_EXTRACT(e.metadata, '$.question_id')
            WHERE e.event_type = 'answer' {$where}
            GROUP BY q.id
            ORDER BY total_answers DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get period WHERE clause
     */
    private function getPeriodWhere($period, $column = 'created_at') {
        switch ($period) {
            case 'today':
                return " AND DATE({$column}) = CURDATE()";
            case 'week':
                return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'year':
                return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return '';
        }
    }
    
    /**
     * Calculate average events per user
     */
    private function calculateAverageEventsPerUser($period) {
        global $wpdb;
        
        $where = $this->getPeriodWhere($period);
        
        $result = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT user_id) as unique_users
            FROM {$wpdb->prefix}money_quiz_analytics_events
            WHERE user_id > 0 {$where}
        ");
        
        return $result->unique_users > 0 
            ? round($result->total_events / $result->unique_users, 2) 
            : 0;
    }
    
    /**
     * Process all metrics
     */
    public function processAll($params = []) {
        $results = [];
        
        foreach ($this->metrics as $metric => $config) {
            try {
                $results[$metric] = $this->process($metric, $params);
            } catch (\Exception $e) {
                $results[$metric] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
}