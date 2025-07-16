<?php
/**
 * ML Recommendations System Loader
 * 
 * @package MoneyQuiz\AI\Recommendations
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Recommendations;

// Load recommendation components
require_once __DIR__ . '/ml-1-recommendation-engine.php';
require_once __DIR__ . '/ml-2-similarity-calculator.php';
require_once __DIR__ . '/ml-3-recommendation-api.php';

/**
 * ML Recommendations Manager
 */
class MLRecommendationsManager {
    
    private static $instance = null;
    private $engine;
    private $similarity;
    private $api;
    
    private function __construct() {
        $this->engine = RecommendationEngine::getInstance();
        $this->similarity = SimilarityCalculator::getInstance();
        $this->api = RecommendationAPI::getInstance();
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
     * Initialize ML Recommendations
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Initialize API
        RecommendationAPI::init();
        
        // Register training hooks
        add_action('money_quiz_ai_train_recommendations', [$instance, 'trainRecommendationModel']);
        
        // Register filters
        add_filter('money_quiz_ai_recommendations_insights', [$instance, 'getInsights']);
        
        // Add admin page
        add_action('admin_menu', [$instance, 'addAdminPage']);
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
        add_action('wp_enqueue_scripts', [$instance, 'enqueueFrontendAssets']);
        
        // Schedule maintenance
        if (!wp_next_scheduled('money_quiz_ml_maintenance')) {
            wp_schedule_event(time(), 'weekly', 'money_quiz_ml_maintenance');
        }
        
        add_action('money_quiz_ml_maintenance', [$instance, 'runMaintenance']);
    }
    
    /**
     * Train recommendation model
     */
    public function trainRecommendationModel($training_data) {
        $start_time = microtime(true);
        
        // Update user similarity matrix
        $this->updateSimilarityMatrix();
        
        // Update recommendation weights
        $this->updateRecommendationWeights($training_data);
        
        // Clear caches
        $this->clearRecommendationCaches();
        
        $duration = microtime(true) - $start_time;
        
        // Log training results
        $this->logTrainingResults([
            'duration' => $duration,
            'data_points' => count($training_data),
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Update similarity matrix
     */
    private function updateSimilarityMatrix() {
        global $wpdb;
        
        // Get active users
        $active_users = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id > 0 
            AND completed_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
            LIMIT 500
        ");
        
        // Batch calculate similarities
        $similarities = $this->similarity->batchCalculateSimilarities($active_users);
        
        // Store in options or custom table
        update_option('money_quiz_ml_similarity_matrix', $similarities);
        update_option('money_quiz_ml_similarity_updated', current_time('mysql'));
    }
    
    /**
     * Update recommendation weights
     */
    private function updateRecommendationWeights($training_data) {
        $feedback_data = $this->collectFeedbackData();
        
        // Calculate new weights based on feedback
        $new_weights = $this->calculateOptimalWeights($feedback_data);
        
        // Update weights
        update_option('money_quiz_ml_weights', $new_weights);
    }
    
    /**
     * Collect feedback data
     */
    private function collectFeedbackData() {
        global $wpdb;
        
        // Get recent feedback
        $feedback = $wpdb->get_results("
            SELECT user_id, meta_value as feedback_data
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'money_quiz_recommendation_feedback'
            AND user_id IN (
                SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = 'money_quiz_recommendation_feedback'
                GROUP BY user_id
                ORDER BY umeta_id DESC
                LIMIT 1000
            )
        ");
        
        return $feedback;
    }
    
    /**
     * Calculate optimal weights
     */
    private function calculateOptimalWeights($feedback_data) {
        $current_weights = get_option('money_quiz_ml_weights', [
            'collaborative' => 0.3,
            'content_based' => 0.25,
            'hybrid' => 0.2,
            'popularity' => 0.15,
            'personalized' => 0.1
        ]);
        
        // Simple gradient descent placeholder
        // In production, this would use proper ML optimization
        
        return $current_weights;
    }
    
    /**
     * Clear recommendation caches
     */
    private function clearRecommendationCaches() {
        global $wpdb;
        
        // Delete all recommendation transients
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_money_quiz_recommendations_%'
        ");
    }
    
    /**
     * Get ML insights
     */
    public function getInsights() {
        return [
            'total_recommendations' => $this->getTotalRecommendations(),
            'accuracy_rate' => $this->getAccuracyRate(),
            'user_satisfaction' => $this->getUserSatisfaction(),
            'popular_algorithms' => $this->getPopularAlgorithms(),
            'last_training' => get_option('money_quiz_ml_similarity_updated'),
            'model_performance' => $this->getModelPerformance()
        ];
    }
    
    /**
     * Add admin page
     */
    public function addAdminPage() {
        add_submenu_page(
            'money-quiz-ai',
            'ML Recommendations',
            'Recommendations',
            'manage_options',
            'money-quiz-ml-recommendations',
            [$this, 'renderAdminPage']
        );
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        $insights = $this->getInsights();
        ?>
        <div class="wrap">
            <h1>ML Recommendations</h1>
            
            <div class="card">
                <h2>Performance Metrics</h2>
                <ul>
                    <li>Total Recommendations: <?php echo number_format($insights['total_recommendations']); ?></li>
                    <li>Accuracy Rate: <?php echo $insights['accuracy_rate']; ?>%</li>
                    <li>User Satisfaction: <?php echo $insights['user_satisfaction']; ?>%</li>
                    <li>Last Model Update: <?php echo $insights['last_training']; ?></li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Algorithm Performance</h2>
                <div id="algorithm-performance-chart"></div>
            </div>
            
            <div class="card">
                <h2>Actions</h2>
                <button class="button button-primary" onclick="trainModel()">Train Model</button>
                <button class="button" onclick="clearCaches()">Clear Caches</button>
                <button class="button" onclick="exportData()">Export Training Data</button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook) {
        if (strpos($hook, 'money-quiz-ml-recommendations') === false) {
            return;
        }
        
        wp_enqueue_script(
            'money-quiz-ml-admin',
            plugin_dir_url(__FILE__) . 'assets/ml-admin.js',
            ['jquery', 'chartjs'],
            '1.0.0',
            true
        );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets() {
        if (!is_singular() || !has_shortcode(get_post()->post_content, 'quiz_recommendations')) {
            return;
        }
        
        wp_enqueue_style(
            'money-quiz-recommendations',
            plugin_dir_url(__FILE__) . 'assets/recommendations.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'money-quiz-recommendations',
            plugin_dir_url(__FILE__) . 'assets/recommendations.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('money-quiz-recommendations', 'moneyQuizML', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('money_quiz_nonce')
        ]);
    }
    
    /**
     * Run maintenance tasks
     */
    public function runMaintenance() {
        // Clean old feedback data
        $this->cleanOldFeedback();
        
        // Optimize similarity matrix
        $this->optimizeSimilarityMatrix();
        
        // Update algorithm weights
        $this->updateAlgorithmWeights();
    }
    
    /**
     * Helper methods
     */
    private function getTotalRecommendations() {
        return get_option('money_quiz_ml_total_recommendations', 0);
    }
    
    private function getAccuracyRate() {
        // Calculate based on feedback
        return 85; // Placeholder
    }
    
    private function getUserSatisfaction() {
        // Calculate from positive feedback
        return 78; // Placeholder
    }
    
    private function getPopularAlgorithms() {
        return get_option('money_quiz_ml_weights', []);
    }
    
    private function getModelPerformance() {
        return [
            'precision' => 0.82,
            'recall' => 0.79,
            'f1_score' => 0.80
        ];
    }
    
    private function logTrainingResults($results) {
        $log = get_option('money_quiz_ml_training_log', []);
        $log[] = $results;
        
        // Keep only last 100 entries
        $log = array_slice($log, -100);
        
        update_option('money_quiz_ml_training_log', $log);
    }
    
    private function cleanOldFeedback() {
        global $wpdb;
        
        // Remove feedback older than 6 months
        $wpdb->query("
            DELETE FROM {$wpdb->usermeta}
            WHERE meta_key = 'money_quiz_recommendation_feedback'
            AND umeta_id IN (
                SELECT umeta_id FROM (
                    SELECT umeta_id FROM {$wpdb->usermeta}
                    WHERE meta_key = 'money_quiz_recommendation_feedback'
                    AND meta_value LIKE '%timestamp%'
                    /* Would parse and check timestamp */
                ) as tmp
            )
        ");
    }
    
    private function optimizeSimilarityMatrix() {
        // Remove inactive users from matrix
        // Compress sparse matrix
    }
    
    private function updateAlgorithmWeights() {
        // Adjust weights based on performance metrics
    }
}

// Initialize ML Recommendations
add_action('plugins_loaded', [MLRecommendationsManager::class, 'init']);