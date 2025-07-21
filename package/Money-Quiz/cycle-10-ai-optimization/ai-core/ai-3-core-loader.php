<?php
/**
 * AI Core System Loader
 * 
 * @package MoneyQuiz\AI\Core
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Core;

// Load AI core components
require_once __DIR__ . '/ai-1-pattern-base.php';
require_once __DIR__ . '/ai-2-data-processor.php';

/**
 * AI Core Manager
 */
class AICoreManager {
    
    private static $instance = null;
    private $pattern_engine;
    private $data_processor;
    private $config;
    
    private function __construct() {
        $this->pattern_engine = PatternRecognition::getInstance();
        $this->data_processor = AIDataProcessor::getInstance();
        $this->loadConfig();
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
     * Initialize AI Core
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Register hooks
        add_action('init', [$instance, 'setupAICore']);
        add_action('money_quiz_result_saved', [$instance, 'processNewResult'], 10, 2);
        
        // Register cron jobs
        if (!wp_next_scheduled('money_quiz_ai_training')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_ai_training');
        }
        
        add_action('money_quiz_ai_training', [$instance, 'runTrainingJob']);
        
        // Add admin capabilities
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Register REST endpoints
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $this->config = [
            'enabled' => get_option('money_quiz_ai_enabled', true),
            'training_threshold' => get_option('money_quiz_ai_training_threshold', 100),
            'confidence_threshold' => get_option('money_quiz_ai_confidence_threshold', 0.75),
            'cache_duration' => get_option('money_quiz_ai_cache_duration', 3600),
            'features' => [
                'pattern_recognition' => true,
                'recommendations' => true,
                'predictive_analytics' => true,
                'nlp_processing' => true,
                'smart_caching' => true
            ]
        ];
    }
    
    /**
     * Setup AI Core
     */
    public function setupAICore() {
        if (!$this->config['enabled']) {
            return;
        }
        
        // Initialize subsystems based on enabled features
        foreach ($this->config['features'] as $feature => $enabled) {
            if ($enabled) {
                $this->initializeFeature($feature);
            }
        }
    }
    
    /**
     * Initialize specific feature
     */
    private function initializeFeature($feature) {
        $feature_file = dirname(__DIR__) . '/' . str_replace('_', '-', $feature) . '/loader.php';
        
        if (file_exists($feature_file)) {
            require_once $feature_file;
        }
    }
    
    /**
     * Process new quiz result
     */
    public function processNewResult($result_id, $result_data) {
        if (!$this->config['enabled']) {
            return;
        }
        
        // Extract patterns from new result
        $patterns = $this->pattern_engine->detectPattern('quiz_completion', [
            'time_taken' => $result_data['time_taken'],
            'score' => $result_data['score'],
            'attempts' => $this->getUserAttempts($result_data['user_id'], $result_data['quiz_id']),
            'device' => $this->detectDevice()
        ]);
        
        // Store patterns for training
        $this->storePatterns($result_id, $patterns);
        
        // Trigger real-time analysis if confidence is high
        if ($patterns['confidence'] === 'high' || $patterns['confidence'] === 'very_high') {
            do_action('money_quiz_ai_pattern_detected', $patterns, $result_data);
        }
    }
    
    /**
     * Get user attempts count
     */
    private function getUserAttempts($user_id, $quiz_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d AND quiz_id = %d
        ", $user_id, $quiz_id));
    }
    
    /**
     * Detect device type
     */
    private function detectDevice() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (wp_is_mobile()) {
            return strpos($user_agent, 'Tablet') !== false ? 'tablet' : 'mobile';
        }
        
        return 'desktop';
    }
    
    /**
     * Store patterns for training
     */
    private function storePatterns($result_id, $patterns) {
        $transient_key = 'money_quiz_ai_patterns_' . date('Y-m-d');
        $stored_patterns = get_transient($transient_key) ?: [];
        
        $stored_patterns[] = [
            'result_id' => $result_id,
            'patterns' => $patterns,
            'timestamp' => current_time('mysql')
        ];
        
        set_transient($transient_key, $stored_patterns, DAY_IN_SECONDS);
    }
    
    /**
     * Run training job
     */
    public function runTrainingJob() {
        if (!$this->config['enabled']) {
            return;
        }
        
        // Collect training data
        $training_data = $this->collectTrainingData();
        
        if (count($training_data) < $this->config['training_threshold']) {
            return;
        }
        
        // Run training for each feature
        foreach ($this->config['features'] as $feature => $enabled) {
            if ($enabled) {
                do_action("money_quiz_ai_train_{$feature}", $training_data);
            }
        }
        
        // Update last training timestamp
        update_option('money_quiz_ai_last_training', current_time('mysql'));
    }
    
    /**
     * Collect training data
     */
    private function collectTrainingData() {
        $data = [];
        
        // Collect patterns from last 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $patterns = get_transient("money_quiz_ai_patterns_$date");
            
            if ($patterns) {
                $data = array_merge($data, $patterns);
            }
        }
        
        return $data;
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'money-quiz',
            'AI Insights',
            'AI Insights',
            'manage_options',
            'money-quiz-ai',
            [$this, 'renderAIPage']
        );
    }
    
    /**
     * Render AI page
     */
    public function renderAIPage() {
        echo '<div class="wrap">';
        echo '<h1>Money Quiz AI Insights</h1>';
        echo '<div id="money-quiz-ai-dashboard"></div>';
        echo '</div>';
    }
    
    /**
     * Register REST endpoints
     */
    public function registerEndpoints() {
        register_rest_route('money-quiz/v1', '/ai/insights', [
            'methods' => 'GET',
            'callback' => [$this, 'getAIInsights'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Get AI insights
     */
    public function getAIInsights($request) {
        return [
            'status' => $this->config['enabled'] ? 'active' : 'inactive',
            'last_training' => get_option('money_quiz_ai_last_training'),
            'features' => $this->config['features'],
            'insights' => $this->gatherInsights()
        ];
    }
    
    /**
     * Gather insights from all AI features
     */
    private function gatherInsights() {
        $insights = [];
        
        foreach ($this->config['features'] as $feature => $enabled) {
            if ($enabled) {
                $insights[$feature] = apply_filters("money_quiz_ai_{$feature}_insights", []);
            }
        }
        
        return $insights;
    }
}

// Initialize AI Core
add_action('plugins_loaded', [AICoreManager::class, 'init']);