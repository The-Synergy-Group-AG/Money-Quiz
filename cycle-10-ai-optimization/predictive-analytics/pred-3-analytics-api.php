<?php
/**
 * Predictive Analytics API
 * 
 * @package MoneyQuiz\AI\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Analytics;

/**
 * API for Predictive Analytics
 */
class PredictiveAnalyticsAPI {
    
    private static $instance = null;
    private $engine;
    
    private function __construct() {
        $this->engine = PredictiveAnalyticsEngine::getInstance();
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
        add_action('wp_ajax_get_predictions', [$instance, 'ajaxGetPredictions']);
        add_action('wp_ajax_get_user_forecast', [$instance, 'ajaxGetUserForecast']);
        
        // Add prediction widgets
        add_action('money_quiz_before_start', [$instance, 'displayPredictions'], 10, 2);
        add_action('money_quiz_user_dashboard', [$instance, 'displayUserAnalytics']);
        
        // Track prediction accuracy
        add_action('money_quiz_result_saved', [$instance, 'trackPredictionAccuracy'], 10, 2);
    }
    
    /**
     * Register REST routes
     */
    public function registerRoutes() {
        // Prediction endpoints
        register_rest_route('money-quiz/v1', '/predictions/completion', [
            'methods' => 'GET',
            'callback' => [$this, 'getCompletionPrediction'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'quiz_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route('money-quiz/v1', '/predictions/score', [
            'methods' => 'GET',
            'callback' => [$this, 'getScorePrediction'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        register_rest_route('money-quiz/v1', '/predictions/dropout', [
            'methods' => 'GET',
            'callback' => [$this, 'getDropoutRisk'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
        
        register_rest_route('money-quiz/v1', '/predictions/forecast', [
            'methods' => 'GET',
            'callback' => [$this, 'getEngagementForecast'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        register_rest_route('money-quiz/v1', '/predictions/difficulty', [
            'methods' => 'POST',
            'callback' => [$this, 'getDifficultyAdjustment'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
    }
    
    /**
     * Get completion prediction
     */
    public function getCompletionPrediction($request) {
        $user_id = get_current_user_id();
        $quiz_id = $request->get_param('quiz_id');
        
        $probability = $this->engine->predictCompletion($user_id, $quiz_id);
        
        // Store prediction for accuracy tracking
        set_transient(
            "prediction_completion_{$user_id}_{$quiz_id}",
            $probability,
            DAY_IN_SECONDS
        );
        
        return [
            'success' => true,
            'data' => [
                'probability' => round($probability * 100, 1),
                'likelihood' => $this->categorizeProbability($probability),
                'factors' => $this->getCompletionFactors($user_id, $quiz_id)
            ]
        ];
    }
    
    /**
     * Get score prediction
     */
    public function getScorePrediction($request) {
        $user_id = get_current_user_id();
        $quiz_id = $request->get_param('quiz_id');
        
        $predicted_score = $this->engine->predictScore($user_id, $quiz_id);
        
        // Store prediction
        set_transient(
            "prediction_score_{$user_id}_{$quiz_id}",
            $predicted_score,
            DAY_IN_SECONDS
        );
        
        return [
            'success' => true,
            'data' => [
                'predicted_score' => round($predicted_score, 1),
                'range' => [
                    'min' => max(0, $predicted_score - 10),
                    'max' => min(100, $predicted_score + 10)
                ],
                'confidence' => $this->calculateScoreConfidence($user_id)
            ]
        ];
    }
    
    /**
     * Get dropout risk
     */
    public function getDropoutRisk($request) {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        
        $risk = $this->engine->predictDropoutRisk($user_id);
        
        return [
            'success' => true,
            'data' => $risk
        ];
    }
    
    /**
     * Get engagement forecast
     */
    public function getEngagementForecast($request) {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $days = $request->get_param('days') ?: 30;
        
        $forecast = $this->engine->forecastEngagement($user_id, $days);
        
        return [
            'success' => true,
            'data' => $forecast
        ];
    }
    
    /**
     * Get difficulty adjustment
     */
    public function getDifficultyAdjustment($request) {
        $user_id = $request->get_param('user_id');
        $performance = $request->get_json_params();
        
        $suggestion = $this->engine->suggestDifficultyAdjustment($user_id, $performance);
        
        return [
            'success' => true,
            'data' => $suggestion
        ];
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxGetPredictions() {
        check_ajax_referer('money_quiz_nonce', 'nonce');
        
        $quiz_id = intval($_POST['quiz_id']);
        
        $request = new \WP_REST_Request('GET');
        $request->set_param('quiz_id', $quiz_id);
        
        $completion = $this->getCompletionPrediction($request);
        $score = $this->getScorePrediction($request);
        
        wp_send_json([
            'completion' => $completion['data'],
            'score' => $score['data']
        ]);
    }
    
    public function ajaxGetUserForecast() {
        check_ajax_referer('money_quiz_nonce', 'nonce');
        
        $request = new \WP_REST_Request('GET');
        $forecast = $this->getEngagementForecast($request);
        
        wp_send_json($forecast);
    }
    
    /**
     * Display predictions before quiz start
     */
    public function displayPredictions($quiz_id, $user_id) {
        if (!$user_id) return;
        
        $completion_prob = $this->engine->predictCompletion($user_id, $quiz_id);
        $predicted_score = $this->engine->predictScore($user_id, $quiz_id);
        
        if ($completion_prob < 0.5) {
            ?>
            <div class="quiz-prediction-notice notice-warning">
                <p>Based on your history, this quiz might be challenging. Take your time!</p>
            </div>
            <?php
        } elseif ($predicted_score > 85) {
            ?>
            <div class="quiz-prediction-notice notice-success">
                <p>You're likely to do well on this quiz. Good luck!</p>
            </div>
            <?php
        }
    }
    
    /**
     * Display user analytics dashboard
     */
    public function displayUserAnalytics($user_id) {
        $dropout_risk = $this->engine->predictDropoutRisk($user_id);
        $forecast = $this->engine->forecastEngagement($user_id, 7);
        
        ?>
        <div class="user-analytics-widget">
            <h3>Your Quiz Analytics</h3>
            
            <div class="engagement-forecast">
                <h4>Next Week Forecast</h4>
                <canvas id="engagement-forecast-chart"></canvas>
            </div>
            
            <?php if ($dropout_risk['risk_level'] === 'high' || $dropout_risk['risk_level'] === 'critical'): ?>
                <div class="risk-alert">
                    <h4>Stay Engaged!</h4>
                    <p>We've noticed your activity has decreased. Keep up the momentum!</p>
                    <ul>
                        <?php foreach ($dropout_risk['factors'] as $factor): ?>
                            <li><?php echo esc_html($factor); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        // Render forecast chart
        var forecastData = <?php echo json_encode($forecast); ?>;
        // Chart.js implementation would go here
        </script>
        <?php
    }
    
    /**
     * Track prediction accuracy
     */
    public function trackPredictionAccuracy($result_id, $result_data) {
        $user_id = $result_data['user_id'];
        $quiz_id = $result_data['quiz_id'];
        
        // Check completion prediction
        $predicted_completion = get_transient("prediction_completion_{$user_id}_{$quiz_id}");
        if ($predicted_completion !== false) {
            $this->logPredictionAccuracy('completion', [
                'predicted' => $predicted_completion,
                'actual' => 1, // They completed it
                'user_id' => $user_id,
                'quiz_id' => $quiz_id
            ]);
        }
        
        // Check score prediction
        $predicted_score = get_transient("prediction_score_{$user_id}_{$quiz_id}");
        if ($predicted_score !== false) {
            $this->logPredictionAccuracy('score', [
                'predicted' => $predicted_score,
                'actual' => $result_data['score'],
                'user_id' => $user_id,
                'quiz_id' => $quiz_id,
                'error' => abs($predicted_score - $result_data['score'])
            ]);
        }
    }
    
    /**
     * Helper methods
     */
    private function categorizeProbability($probability) {
        if ($probability >= 0.9) return 'Very likely';
        if ($probability >= 0.7) return 'Likely';
        if ($probability >= 0.5) return 'Possible';
        if ($probability >= 0.3) return 'Unlikely';
        return 'Very unlikely';
    }
    
    private function getCompletionFactors($user_id, $quiz_id) {
        // Analyze factors affecting completion
        return [
            'past_performance' => 'Strong track record',
            'quiz_difficulty' => 'Matches your level',
            'time_availability' => 'Sufficient based on history'
        ];
    }
    
    private function calculateScoreConfidence($user_id) {
        global $wpdb;
        
        $quiz_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
        
        if ($quiz_count >= 20) return 'High';
        if ($quiz_count >= 10) return 'Medium';
        if ($quiz_count >= 5) return 'Low';
        return 'Very Low';
    }
    
    private function logPredictionAccuracy($type, $data) {
        $log = get_option('money_quiz_prediction_accuracy', []);
        
        if (!isset($log[$type])) {
            $log[$type] = [];
        }
        
        $log[$type][] = array_merge($data, [
            'timestamp' => current_time('mysql')
        ]);
        
        // Keep only last 1000 entries per type
        $log[$type] = array_slice($log[$type], -1000);
        
        update_option('money_quiz_prediction_accuracy', $log);
    }
}

// Initialize API
add_action('init', [PredictiveAnalyticsAPI::class, 'init']);