<?php
/**
 * Webhook Event Manager
 * 
 * @package MoneyQuiz\Webhooks
 * @version 1.0.0
 */

namespace MoneyQuiz\Webhooks;

/**
 * Event Manager
 */
class EventManager {
    
    private static $instance = null;
    private $events = [];
    private $listeners = [];
    
    /**
     * Available webhook events
     */
    const EVENTS = [
        // Quiz events
        'quiz.created' => 'Quiz Created',
        'quiz.updated' => 'Quiz Updated',
        'quiz.deleted' => 'Quiz Deleted',
        'quiz.published' => 'Quiz Published',
        
        // Result events
        'result.submitted' => 'Result Submitted',
        'result.updated' => 'Result Updated',
        'result.deleted' => 'Result Deleted',
        
        // User events
        'user.registered' => 'User Registered',
        'user.updated' => 'User Updated',
        'user.achievement' => 'User Achievement',
        
        // System events
        'system.error' => 'System Error',
        'system.warning' => 'System Warning'
    ];
    
    private function __construct() {
        $this->registerCoreEvents();
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
     * Initialize event manager
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Hook into WordPress events
        add_action('money_quiz_quiz_created', [$instance, 'handleQuizCreated'], 10, 2);
        add_action('money_quiz_quiz_updated', [$instance, 'handleQuizUpdated'], 10, 2);
        add_action('money_quiz_result_submitted', [$instance, 'handleResultSubmitted'], 10, 2);
        
        // API events
        add_action('money_quiz_api_quiz_created', [$instance, 'handleQuizCreated'], 10, 1);
        add_action('money_quiz_api_quiz_updated', [$instance, 'handleQuizUpdated'], 10, 2);
        add_action('money_quiz_api_quiz_deleted', [$instance, 'handleQuizDeleted'], 10, 1);
    }
    
    /**
     * Register core events
     */
    private function registerCoreEvents() {
        foreach (self::EVENTS as $event => $description) {
            $this->registerEvent($event, $description);
        }
    }
    
    /**
     * Register event
     */
    public function registerEvent($name, $description = '') {
        $this->events[$name] = [
            'name' => $name,
            'description' => $description,
            'fired_count' => 0,
            'last_fired' => null
        ];
    }
    
    /**
     * Fire event
     */
    public function fire($event, $data = []) {
        if (!isset($this->events[$event])) {
            return false;
        }
        
        // Update event stats
        $this->events[$event]['fired_count']++;
        $this->events[$event]['last_fired'] = current_time('mysql');
        
        // Add metadata
        $data['_meta'] = [
            'event' => $event,
            'timestamp' => time(),
            'site_url' => site_url(),
            'plugin_version' => '1.0.0'
        ];
        
        // Notify listeners
        foreach ($this->getListeners($event) as $listener) {
            try {
                call_user_func($listener, $event, $data);
            } catch (\Exception $e) {
                error_log('Webhook event error: ' . $e->getMessage());
            }
        }
        
        // WordPress action
        do_action('money_quiz_webhook_event', $event, $data);
        
        return true;
    }
    
    /**
     * Add event listener
     */
    public function addListener($event, $callback) {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        $this->listeners[$event][] = $callback;
    }
    
    /**
     * Get listeners for event
     */
    private function getListeners($event) {
        $listeners = [];
        
        // Exact match
        if (isset($this->listeners[$event])) {
            $listeners = array_merge($listeners, $this->listeners[$event]);
        }
        
        // Wildcard match
        if (isset($this->listeners['*'])) {
            $listeners = array_merge($listeners, $this->listeners['*']);
        }
        
        // Pattern match (e.g., 'quiz.*')
        $parts = explode('.', $event);
        if (count($parts) > 1) {
            $pattern = $parts[0] . '.*';
            if (isset($this->listeners[$pattern])) {
                $listeners = array_merge($listeners, $this->listeners[$pattern]);
            }
        }
        
        return $listeners;
    }
    
    /**
     * Event handlers
     */
    public function handleQuizCreated($quiz) {
        $this->fire('quiz.created', [
            'quiz' => $this->prepareQuizData($quiz)
        ]);
    }
    
    public function handleQuizUpdated($quiz_id, $data = null) {
        $quiz = is_object($quiz_id) ? $quiz_id : $this->getQuiz($quiz_id);
        
        $this->fire('quiz.updated', [
            'quiz' => $this->prepareQuizData($quiz),
            'changes' => $data
        ]);
    }
    
    public function handleQuizDeleted($quiz_id) {
        $this->fire('quiz.deleted', [
            'quiz_id' => $quiz_id
        ]);
    }
    
    public function handleResultSubmitted($result_id, $score) {
        $this->fire('result.submitted', [
            'result_id' => $result_id,
            'score' => $score,
            'user_id' => get_current_user_id()
        ]);
    }
    
    /**
     * Prepare quiz data
     */
    private function prepareQuizData($quiz) {
        if (is_numeric($quiz)) {
            global $wpdb;
            $quiz = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
                $quiz
            ));
        }
        
        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'status' => $quiz->status,
            'created_at' => $quiz->created_at
        ];
    }
    
    /**
     * Get quiz
     */
    private function getQuiz($quiz_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $quiz_id
        ));
    }
    
    /**
     * Get registered events
     */
    public function getEvents() {
        return $this->events;
    }
    
    /**
     * Get event info
     */
    public function getEventInfo($event) {
        return isset($this->events[$event]) ? $this->events[$event] : null;
    }
}