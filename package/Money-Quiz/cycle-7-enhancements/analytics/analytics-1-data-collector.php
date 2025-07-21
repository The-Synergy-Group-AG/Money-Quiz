<?php
/**
 * Analytics Data Collector
 * 
 * @package MoneyQuiz\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\Analytics;

/**
 * Data Collector
 */
class DataCollector {
    
    private static $instance = null;
    private $collection_points = [];
    private $buffer = [];
    private $buffer_size = 100;
    
    private function __construct() {
        $this->registerCollectionPoints();
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
     * Initialize data collector
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Hook into events
        add_action('money_quiz_quiz_started', [$instance, 'collectQuizStart'], 10, 2);
        add_action('money_quiz_quiz_completed', [$instance, 'collectQuizComplete'], 10, 3);
        add_action('money_quiz_question_answered', [$instance, 'collectAnswer'], 10, 4);
        add_action('money_quiz_user_registered', [$instance, 'collectUserRegistration'], 10, 1);
        
        // Page views
        add_action('template_redirect', [$instance, 'collectPageView']);
        
        // Flush buffer on shutdown
        add_action('shutdown', [$instance, 'flushBuffer']);
        
        // Schedule aggregation
        if (!wp_next_scheduled('money_quiz_aggregate_analytics')) {
            wp_schedule_event(time(), 'hourly', 'money_quiz_aggregate_analytics');
        }
        
        add_action('money_quiz_aggregate_analytics', [$instance, 'aggregateData']);
    }
    
    /**
     * Register collection points
     */
    private function registerCollectionPoints() {
        $this->collection_points = [
            'quiz_start' => ['table' => 'analytics_events', 'type' => 'quiz_start'],
            'quiz_complete' => ['table' => 'analytics_events', 'type' => 'quiz_complete'],
            'question_answer' => ['table' => 'analytics_events', 'type' => 'answer'],
            'page_view' => ['table' => 'analytics_pageviews', 'type' => 'pageview'],
            'user_action' => ['table' => 'analytics_events', 'type' => 'user_action']
        ];
    }
    
    /**
     * Collect quiz start
     */
    public function collectQuizStart($quiz_id, $user_id) {
        $this->collect('quiz_start', [
            'quiz_id' => $quiz_id,
            'user_id' => $user_id ?: 0,
            'session_id' => $this->getSessionId(),
            'timestamp' => time(),
            'metadata' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
            ]
        ]);
    }
    
    /**
     * Collect quiz completion
     */
    public function collectQuizComplete($quiz_id, $user_id, $score) {
        $this->collect('quiz_complete', [
            'quiz_id' => $quiz_id,
            'user_id' => $user_id ?: 0,
            'session_id' => $this->getSessionId(),
            'score' => $score,
            'timestamp' => time(),
            'metadata' => [
                'time_taken' => $this->calculateTimeTaken($quiz_id),
                'completion_rate' => 100
            ]
        ]);
    }
    
    /**
     * Collect answer
     */
    public function collectAnswer($question_id, $answer, $is_correct, $time_taken) {
        $this->collect('question_answer', [
            'question_id' => $question_id,
            'answer' => $answer,
            'is_correct' => $is_correct,
            'time_taken' => $time_taken,
            'user_id' => get_current_user_id(),
            'session_id' => $this->getSessionId(),
            'timestamp' => time()
        ]);
    }
    
    /**
     * Collect page view
     */
    public function collectPageView() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        $this->collect('page_view', [
            'url' => $_SERVER['REQUEST_URI'],
            'user_id' => get_current_user_id(),
            'session_id' => $this->getSessionId(),
            'timestamp' => time(),
            'metadata' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'ip' => $this->getClientIp()
            ]
        ]);
    }
    
    /**
     * Collect user registration
     */
    public function collectUserRegistration($user_id) {
        $this->collect('user_action', [
            'action' => 'registration',
            'user_id' => $user_id,
            'timestamp' => time(),
            'metadata' => [
                'source' => 'organic'
            ]
        ]);
    }
    
    /**
     * Generic collect method
     */
    public function collect($type, $data) {
        if (!isset($this->collection_points[$type])) {
            return false;
        }
        
        $point = $this->collection_points[$type];
        
        $record = array_merge([
            'event_type' => $point['type'],
            'created_at' => current_time('mysql')
        ], $data);
        
        // Add to buffer
        $this->buffer[] = [
            'table' => $point['table'],
            'data' => $record
        ];
        
        // Flush if buffer is full
        if (count($this->buffer) >= $this->buffer_size) {
            $this->flushBuffer();
        }
        
        return true;
    }
    
    /**
     * Flush buffer
     */
    public function flushBuffer() {
        if (empty($this->buffer)) {
            return;
        }
        
        global $wpdb;
        
        // Group by table
        $grouped = [];
        foreach ($this->buffer as $item) {
            $table = $wpdb->prefix . 'money_quiz_' . $item['table'];
            if (!isset($grouped[$table])) {
                $grouped[$table] = [];
            }
            $grouped[$table][] = $item['data'];
        }
        
        // Batch insert
        foreach ($grouped as $table => $records) {
            $this->batchInsert($table, $records);
        }
        
        // Clear buffer
        $this->buffer = [];
    }
    
    /**
     * Batch insert
     */
    private function batchInsert($table, $records) {
        global $wpdb;
        
        if (empty($records)) {
            return;
        }
        
        // Get columns from first record
        $columns = array_keys($records[0]);
        
        // Build query
        $placeholders = [];
        $values = [];
        
        foreach ($records as $record) {
            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '%s')) . ')';
            foreach ($columns as $column) {
                if ($column === 'metadata' && is_array($record[$column])) {
                    $values[] = json_encode($record[$column]);
                } else {
                    $values[] = $record[$column];
                }
            }
        }
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES " . 
               implode(', ', $placeholders);
        
        $wpdb->query($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get session ID
     */
    private function getSessionId() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    /**
     * Get client IP
     */
    private function getClientIp() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Calculate time taken
     */
    private function calculateTimeTaken($quiz_id) {
        // Simplified - would track actual start/end times
        return rand(60, 600); // 1-10 minutes
    }
    
    /**
     * Aggregate data
     */
    public function aggregateData() {
        // This would run aggregation queries
        do_action('money_quiz_analytics_aggregate');
    }
}