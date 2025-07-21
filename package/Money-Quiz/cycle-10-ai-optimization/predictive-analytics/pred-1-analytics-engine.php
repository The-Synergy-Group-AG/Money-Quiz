<?php
/**
 * Predictive Analytics Engine
 * 
 * @package MoneyQuiz\AI\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Analytics;

/**
 * Core Predictive Analytics Engine
 */
class PredictiveAnalyticsEngine {
    
    private static $instance = null;
    private $models = [];
    private $predictions_cache = [];
    
    private function __construct() {
        $this->initializeModels();
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
     * Initialize prediction models
     */
    private function initializeModels() {
        $this->models = [
            'completion_probability' => new CompletionProbabilityModel(),
            'score_prediction' => new ScorePredictionModel(),
            'dropout_risk' => new DropoutRiskModel(),
            'engagement_forecast' => new EngagementForecastModel(),
            'difficulty_adjustment' => new DifficultyAdjustmentModel()
        ];
    }
    
    /**
     * Predict quiz completion probability
     */
    public function predictCompletion($user_id, $quiz_id) {
        $features = $this->extractUserQuizFeatures($user_id, $quiz_id);
        return $this->models['completion_probability']->predict($features);
    }
    
    /**
     * Predict expected score
     */
    public function predictScore($user_id, $quiz_id) {
        $features = $this->extractUserQuizFeatures($user_id, $quiz_id);
        return $this->models['score_prediction']->predict($features);
    }
    
    /**
     * Predict dropout risk
     */
    public function predictDropoutRisk($user_id) {
        $features = $this->extractUserFeatures($user_id);
        return $this->models['dropout_risk']->predict($features);
    }
    
    /**
     * Forecast future engagement
     */
    public function forecastEngagement($user_id, $days = 30) {
        $historical_data = $this->getUserHistoricalData($user_id);
        return $this->models['engagement_forecast']->forecast($historical_data, $days);
    }
    
    /**
     * Suggest difficulty adjustment
     */
    public function suggestDifficultyAdjustment($user_id, $current_performance) {
        return $this->models['difficulty_adjustment']->suggest($user_id, $current_performance);
    }
    
    /**
     * Extract user-quiz features
     */
    private function extractUserQuizFeatures($user_id, $quiz_id) {
        global $wpdb;
        
        // User historical performance
        $user_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_quizzes,
                AVG(score) as avg_score,
                STD(score) as score_std,
                AVG(time_taken) as avg_time,
                MAX(completed_at) as last_activity
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d
        ", $user_id), ARRAY_A);
        
        // Quiz statistics
        $quiz_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as unique_takers,
                AVG(score) as avg_score,
                STD(score) as score_std,
                AVG(time_taken) as avg_time,
                COUNT(*) as total_attempts
            FROM {$wpdb->prefix}money_quiz_results
            WHERE quiz_id = %d
        ", $quiz_id), ARRAY_A);
        
        // Question count and difficulty
        $quiz_meta = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as question_count,
                SUM(points) as total_points
            FROM {$wpdb->prefix}money_quiz_questions
            WHERE quiz_id = %d
        ", $quiz_id), ARRAY_A);
        
        return array_merge(
            $this->normalizeFeatures($user_stats),
            $this->normalizeFeatures($quiz_stats),
            $this->normalizeFeatures($quiz_meta),
            [
                'time_since_last' => $this->calculateTimeSinceLastActivity($user_stats['last_activity']),
                'user_experience_level' => $this->calculateExperienceLevel($user_stats['total_quizzes']),
                'quiz_difficulty_rating' => $this->calculateQuizDifficulty($quiz_stats)
            ]
        );
    }
    
    /**
     * Extract user features
     */
    private function extractUserFeatures($user_id) {
        global $wpdb;
        
        // Recent activity pattern
        $recent_activity = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(completed_at) as date,
                COUNT(*) as quizzes_taken,
                AVG(score) as avg_score
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d 
            AND completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(completed_at)
        ", $user_id));
        
        // Calculate features
        return [
            'days_active' => count($recent_activity),
            'avg_daily_quizzes' => $this->calculateAverageDailyQuizzes($recent_activity),
            'consistency_score' => $this->calculateConsistencyScore($recent_activity),
            'trend' => $this->calculateTrend($recent_activity),
            'last_7_days_activity' => $this->getLast7DaysActivity($recent_activity)
        ];
    }
    
    /**
     * Get user historical data
     */
    private function getUserHistoricalData($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(completed_at) as date,
                COUNT(*) as quizzes,
                AVG(score) as avg_score,
                SUM(time_taken) as total_time
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d
            GROUP BY DATE(completed_at)
            ORDER BY date ASC
        ", $user_id), ARRAY_A);
    }
    
    /**
     * Normalize features
     */
    private function normalizeFeatures($features) {
        $normalized = [];
        
        foreach ($features as $key => $value) {
            if (is_numeric($value)) {
                $normalized[$key] = $this->normalizeValue($value, $key);
            }
        }
        
        return $normalized;
    }
    
    /**
     * Normalize single value
     */
    private function normalizeValue($value, $feature_name) {
        // Feature-specific normalization ranges
        $ranges = [
            'score' => [0, 100],
            'time_taken' => [0, 3600],
            'total_quizzes' => [0, 100],
            'question_count' => [1, 50]
        ];
        
        foreach ($ranges as $pattern => $range) {
            if (strpos($feature_name, $pattern) !== false) {
                return ($value - $range[0]) / ($range[1] - $range[0]);
            }
        }
        
        // Default normalization
        return $value / 100;
    }
    
    /**
     * Helper calculation methods
     */
    private function calculateTimeSinceLastActivity($last_activity) {
        if (!$last_activity) return 1;
        
        $days = (time() - strtotime($last_activity)) / (60 * 60 * 24);
        return min($days / 30, 1); // Normalize to 0-1 (30 days max)
    }
    
    private function calculateExperienceLevel($total_quizzes) {
        if ($total_quizzes >= 50) return 1.0;
        if ($total_quizzes >= 20) return 0.7;
        if ($total_quizzes >= 10) return 0.5;
        if ($total_quizzes >= 5) return 0.3;
        return 0.1;
    }
    
    private function calculateQuizDifficulty($quiz_stats) {
        if (!$quiz_stats['avg_score']) return 0.5;
        
        // Lower average score = higher difficulty
        $difficulty = 1 - ($quiz_stats['avg_score'] / 100);
        
        // Adjust based on standard deviation
        $difficulty += ($quiz_stats['score_std'] / 100) * 0.2;
        
        return min(max($difficulty, 0), 1);
    }
    
    private function calculateAverageDailyQuizzes($activity) {
        if (empty($activity)) return 0;
        
        $total = array_sum(array_column($activity, 'quizzes_taken'));
        return $total / 30; // 30-day average
    }
    
    private function calculateConsistencyScore($activity) {
        if (count($activity) < 7) return 0;
        
        // Calculate standard deviation of daily activity
        $daily_counts = array_column($activity, 'quizzes_taken');
        $mean = array_sum($daily_counts) / count($daily_counts);
        
        $variance = 0;
        foreach ($daily_counts as $count) {
            $variance += pow($count - $mean, 2);
        }
        $variance /= count($daily_counts);
        
        // Lower variance = higher consistency
        return 1 / (1 + sqrt($variance));
    }
    
    private function calculateTrend($activity) {
        if (count($activity) < 3) return 0;
        
        // Simple linear regression
        $x = range(0, count($activity) - 1);
        $y = array_column($activity, 'quizzes_taken');
        
        $n = count($x);
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_xx = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_xx += $x[$i] * $x[$i];
        }
        
        if ($n * $sum_xx - $sum_x * $sum_x == 0) return 0;
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
        
        return tanh($slope); // Normalize to -1 to 1
    }
    
    private function getLast7DaysActivity($activity) {
        $last_7_days = array_slice($activity, -7);
        return count($last_7_days) / 7; // Activity rate
    }
}

/**
 * Base Prediction Model
 */
abstract class PredictionModel {
    protected $weights = [];
    
    abstract public function predict($features);
    
    protected function loadWeights() {
        $this->weights = get_option($this->getWeightKey(), $this->getDefaultWeights());
    }
    
    abstract protected function getWeightKey();
    abstract protected function getDefaultWeights();
}

/**
 * Completion Probability Model
 */
class CompletionProbabilityModel extends PredictionModel {
    
    public function predict($features) {
        $this->loadWeights();
        
        $score = 0;
        foreach ($this->weights as $feature => $weight) {
            if (isset($features[$feature])) {
                $score += $features[$feature] * $weight;
            }
        }
        
        // Sigmoid activation
        return 1 / (1 + exp(-$score));
    }
    
    protected function getWeightKey() {
        return 'money_quiz_ml_completion_weights';
    }
    
    protected function getDefaultWeights() {
        return [
            'avg_score' => 0.3,
            'total_quizzes' => 0.2,
            'time_since_last' => -0.4,
            'user_experience_level' => 0.3,
            'quiz_difficulty_rating' => -0.2
        ];
    }
}