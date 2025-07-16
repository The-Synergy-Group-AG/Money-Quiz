<?php
/**
 * ML Recommendation API
 * 
 * @package MoneyQuiz\AI\Recommendations
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Recommendations;

/**
 * API for quiz recommendations
 */
class RecommendationAPI {
    
    private static $instance = null;
    private $engine;
    private $cache_duration = 3600;
    
    private function __construct() {
        $this->engine = RecommendationEngine::getInstance();
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
        
        // Register AJAX handlers
        add_action('wp_ajax_get_quiz_recommendations', [$instance, 'ajaxGetRecommendations']);
        add_action('wp_ajax_nopriv_get_quiz_recommendations', [$instance, 'ajaxGetRecommendations']);
        
        // Register shortcode
        add_shortcode('quiz_recommendations', [$instance, 'recommendationsShortcode']);
        
        // Add recommendation widget
        add_action('money_quiz_after_results', [$instance, 'displayRecommendations'], 10, 2);
    }
    
    /**
     * Register REST routes
     */
    public function registerRoutes() {
        register_rest_route('money-quiz/v1', '/recommendations', [
            'methods' => 'GET',
            'callback' => [$this, 'getRecommendations'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'user_id' => [
                    'default' => get_current_user_id(),
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'limit' => [
                    'default' => 5,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 20;
                    }
                ]
            ]
        ]);
        
        register_rest_route('money-quiz/v1', '/recommendations/feedback', [
            'methods' => 'POST',
            'callback' => [$this, 'submitFeedback'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }
    
    /**
     * Get recommendations endpoint
     */
    public function getRecommendations($request) {
        $user_id = $request->get_param('user_id');
        $limit = $request->get_param('limit');
        
        // Check cache
        $cache_key = "money_quiz_recommendations_{$user_id}_{$limit}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return [
                'success' => true,
                'data' => $cached,
                'cached' => true
            ];
        }
        
        // Get fresh recommendations
        $recommendations = $this->engine->getRecommendations($user_id, $limit);
        
        // Enrich with quiz data
        $enriched = $this->enrichRecommendations($recommendations);
        
        // Cache results
        set_transient($cache_key, $enriched, $this->cache_duration);
        
        return [
            'success' => true,
            'data' => $enriched,
            'cached' => false
        ];
    }
    
    /**
     * Submit feedback on recommendations
     */
    public function submitFeedback($request) {
        $user_id = get_current_user_id();
        $quiz_id = $request->get_param('quiz_id');
        $feedback = $request->get_param('feedback'); // 'helpful', 'not_helpful', 'already_taken'
        
        // Store feedback for ML training
        $this->storeFeedback($user_id, $quiz_id, $feedback);
        
        // Update recommendation weights if needed
        if ($feedback === 'not_helpful') {
            $this->adjustRecommendationWeights($user_id, $quiz_id);
        }
        
        return [
            'success' => true,
            'message' => 'Feedback recorded'
        ];
    }
    
    /**
     * AJAX handler for recommendations
     */
    public function ajaxGetRecommendations() {
        check_ajax_referer('money_quiz_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        $limit = intval($_POST['limit'] ?? 5);
        
        $request = new \WP_REST_Request('GET');
        $request->set_param('user_id', $user_id);
        $request->set_param('limit', $limit);
        
        $response = $this->getRecommendations($request);
        
        wp_send_json($response);
    }
    
    /**
     * Recommendations shortcode
     */
    public function recommendationsShortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 5,
            'title' => 'Recommended Quizzes',
            'class' => 'quiz-recommendations'
        ], $atts);
        
        if (!is_user_logged_in()) {
            return '<p>Please log in to see quiz recommendations.</p>';
        }
        
        $user_id = get_current_user_id();
        $recommendations = $this->engine->getRecommendations($user_id, $atts['limit']);
        $enriched = $this->enrichRecommendations($recommendations);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <?php if (empty($enriched)): ?>
                <p>No recommendations available. Take more quizzes to get personalized suggestions!</p>
            <?php else: ?>
                <ul class="recommendation-list">
                    <?php foreach ($enriched as $quiz): ?>
                        <li class="recommendation-item">
                            <h4>
                                <a href="<?php echo esc_url($quiz['url']); ?>">
                                    <?php echo esc_html($quiz['title']); ?>
                                </a>
                            </h4>
                            <p><?php echo esc_html($quiz['description']); ?></p>
                            <div class="recommendation-meta">
                                <span class="match-score">
                                    <?php echo round($quiz['score'] * 100); ?>% match
                                </span>
                                <?php if ($quiz['reason']): ?>
                                    <span class="recommendation-reason">
                                        <?php echo esc_html($quiz['reason']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display recommendations after quiz results
     */
    public function displayRecommendations($result_id, $quiz_id) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $recommendations = $this->engine->getRecommendations($user_id, 3);
        
        // Filter out current quiz
        unset($recommendations[$quiz_id]);
        
        if (empty($recommendations)) {
            return;
        }
        
        $enriched = $this->enrichRecommendations($recommendations);
        
        ?>
        <div class="quiz-recommendations-widget">
            <h3>Try These Quizzes Next</h3>
            <div class="recommendation-cards">
                <?php foreach ($enriched as $quiz): ?>
                    <div class="recommendation-card">
                        <h4><?php echo esc_html($quiz['title']); ?></h4>
                        <a href="<?php echo esc_url($quiz['url']); ?>" class="button">
                            Take Quiz
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enrich recommendations with quiz data
     */
    private function enrichRecommendations($recommendations) {
        global $wpdb;
        
        if (empty($recommendations)) {
            return [];
        }
        
        $quiz_ids = array_keys($recommendations);
        $placeholders = array_fill(0, count($quiz_ids), '%d');
        
        $quizzes = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}money_quiz_quizzes
            WHERE id IN (" . implode(',', $placeholders) . ")
        ", $quiz_ids), OBJECT_K);
        
        $enriched = [];
        
        foreach ($recommendations as $quiz_id => $score) {
            if (!isset($quizzes[$quiz_id])) {
                continue;
            }
            
            $quiz = $quizzes[$quiz_id];
            
            $enriched[] = [
                'id' => $quiz_id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'url' => get_permalink($quiz->post_id ?? 0),
                'score' => $score,
                'reason' => $this->getRecommendationReason($score),
                'questions' => $this->getQuestionCount($quiz_id),
                'difficulty' => $this->getQuizDifficulty($quiz_id)
            ];
        }
        
        return $enriched;
    }
    
    /**
     * Get recommendation reason
     */
    private function getRecommendationReason($score) {
        if ($score > 0.9) return 'Perfect match for you';
        if ($score > 0.8) return 'Highly recommended';
        if ($score > 0.7) return 'Good match';
        if ($score > 0.6) return 'Worth trying';
        return 'Might interest you';
    }
    
    /**
     * Get question count
     */
    private function getQuestionCount($quiz_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_questions WHERE quiz_id = %d",
            $quiz_id
        ));
    }
    
    /**
     * Get quiz difficulty
     */
    private function getQuizDifficulty($quiz_id) {
        // This would analyze historical data
        return 'medium'; // Placeholder
    }
    
    /**
     * Store recommendation feedback
     */
    private function storeFeedback($user_id, $quiz_id, $feedback) {
        add_user_meta($user_id, 'money_quiz_recommendation_feedback', [
            'quiz_id' => $quiz_id,
            'feedback' => $feedback,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Adjust recommendation weights
     */
    private function adjustRecommendationWeights($user_id, $quiz_id) {
        // This would update ML model weights based on negative feedback
        do_action('money_quiz_ml_negative_feedback', $user_id, $quiz_id);
    }
}

// Initialize API
add_action('init', [RecommendationAPI::class, 'init']);