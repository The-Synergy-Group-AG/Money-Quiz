<?php
/**
 * Predictive Analytics Loader
 * 
 * @package MoneyQuiz\AI\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Analytics;

// Load predictive analytics components
require_once __DIR__ . '/pred-1-analytics-engine.php';
require_once __DIR__ . '/pred-2-prediction-models.php';
require_once __DIR__ . '/pred-3-analytics-api.php';

/**
 * Predictive Analytics Manager
 */
class PredictiveAnalyticsManager {
    
    private static $instance = null;
    private $engine;
    private $api;
    private $models = [];
    
    private function __construct() {
        $this->engine = PredictiveAnalyticsEngine::getInstance();
        $this->api = PredictiveAnalyticsAPI::getInstance();
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
     * Initialize Predictive Analytics
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Initialize API
        PredictiveAnalyticsAPI::init();
        
        // Register training hooks
        add_action('money_quiz_ai_train_predictive_analytics', [$instance, 'trainModels']);
        
        // Register insights filter
        add_filter('money_quiz_ai_predictive_analytics_insights', [$instance, 'getInsights']);
        
        // Add admin pages
        add_action('admin_menu', [$instance, 'addAdminPages']);
        
        // Schedule model evaluation
        if (!wp_next_scheduled('money_quiz_evaluate_predictions')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_evaluate_predictions');
        }
        
        add_action('money_quiz_evaluate_predictions', [$instance, 'evaluateModels']);
        
        // Add dashboard widgets
        add_action('wp_dashboard_setup', [$instance, 'addDashboardWidget']);
    }
    
    /**
     * Train prediction models
     */
    public function trainModels($training_data) {
        $start_time = microtime(true);
        
        // Train each model type
        $models_trained = [
            'completion' => $this->trainCompletionModel($training_data),
            'score' => $this->trainScoreModel($training_data),
            'dropout' => $this->trainDropoutModel($training_data),
            'engagement' => $this->trainEngagementModel($training_data)
        ];
        
        $duration = microtime(true) - $start_time;
        
        // Log training session
        $this->logTrainingSession([
            'models_trained' => $models_trained,
            'data_points' => count($training_data),
            'duration' => $duration,
            'timestamp' => current_time('mysql')
        ]);
        
        // Clear prediction caches
        $this->clearPredictionCaches();
    }
    
    /**
     * Train individual models
     */
    private function trainCompletionModel($data) {
        // Extract features and labels
        $features = [];
        $labels = [];
        
        foreach ($data as $entry) {
            if (isset($entry['patterns']['type']) && $entry['patterns']['type'] === 'quiz_completion') {
                $features[] = $entry['patterns']['features'];
                $labels[] = 1; // Completed
            }
        }
        
        // Update model weights using gradient descent
        if (!empty($features)) {
            $this->updateModelWeights('completion', $features, $labels);
            return count($features);
        }
        
        return 0;
    }
    
    private function trainScoreModel($data) {
        // Similar implementation for score prediction
        return 0; // Placeholder
    }
    
    private function trainDropoutModel($data) {
        // Analyze user engagement patterns
        return 0; // Placeholder
    }
    
    private function trainEngagementModel($data) {
        // Time series analysis for engagement
        return 0; // Placeholder
    }
    
    /**
     * Update model weights
     */
    private function updateModelWeights($model_type, $features, $labels) {
        $weight_key = "money_quiz_ml_{$model_type}_weights";
        $current_weights = get_option($weight_key, []);
        
        // Simple gradient descent (placeholder)
        // In production, use proper ML library
        
        update_option($weight_key, $current_weights);
    }
    
    /**
     * Evaluate model performance
     */
    public function evaluateModels() {
        $accuracy_log = get_option('money_quiz_prediction_accuracy', []);
        
        $evaluation = [];
        
        // Evaluate completion predictions
        if (isset($accuracy_log['completion'])) {
            $evaluation['completion'] = $this->evaluateCompletionAccuracy($accuracy_log['completion']);
        }
        
        // Evaluate score predictions
        if (isset($accuracy_log['score'])) {
            $evaluation['score'] = $this->evaluateScoreAccuracy($accuracy_log['score']);
        }
        
        // Store evaluation results
        update_option('money_quiz_model_evaluation', [
            'results' => $evaluation,
            'timestamp' => current_time('mysql')
        ]);
        
        // Trigger retraining if accuracy drops
        foreach ($evaluation as $model => $metrics) {
            if (isset($metrics['accuracy']) && $metrics['accuracy'] < 0.7) {
                do_action('money_quiz_ai_retrain_needed', $model);
            }
        }
    }
    
    /**
     * Evaluate completion accuracy
     */
    private function evaluateCompletionAccuracy($predictions) {
        if (empty($predictions)) {
            return ['accuracy' => 0, 'samples' => 0];
        }
        
        $correct = 0;
        $total = count($predictions);
        
        foreach ($predictions as $pred) {
            if (($pred['predicted'] > 0.5 && $pred['actual'] == 1) ||
                ($pred['predicted'] <= 0.5 && $pred['actual'] == 0)) {
                $correct++;
            }
        }
        
        return [
            'accuracy' => $correct / $total,
            'samples' => $total,
            'precision' => $this->calculatePrecision($predictions),
            'recall' => $this->calculateRecall($predictions)
        ];
    }
    
    /**
     * Evaluate score accuracy
     */
    private function evaluateScoreAccuracy($predictions) {
        if (empty($predictions)) {
            return ['mae' => 0, 'rmse' => 0, 'samples' => 0];
        }
        
        $total_error = 0;
        $squared_error = 0;
        $count = count($predictions);
        
        foreach ($predictions as $pred) {
            $error = abs($pred['predicted'] - $pred['actual']);
            $total_error += $error;
            $squared_error += $error * $error;
        }
        
        return [
            'mae' => $total_error / $count, // Mean Absolute Error
            'rmse' => sqrt($squared_error / $count), // Root Mean Square Error
            'samples' => $count
        ];
    }
    
    /**
     * Get predictive analytics insights
     */
    public function getInsights() {
        $evaluation = get_option('money_quiz_model_evaluation', []);
        $last_training = get_option('money_quiz_ml_last_training');
        
        return [
            'model_performance' => $evaluation['results'] ?? [],
            'last_evaluation' => $evaluation['timestamp'] ?? 'Never',
            'last_training' => $last_training,
            'prediction_counts' => $this->getPredictionCounts(),
            'accuracy_trends' => $this->getAccuracyTrends()
        ];
    }
    
    /**
     * Add admin pages
     */
    public function addAdminPages() {
        add_submenu_page(
            'money-quiz-ai',
            'Predictive Analytics',
            'Predictions',
            'manage_options',
            'money-quiz-predictions',
            [$this, 'renderPredictionsPage']
        );
    }
    
    /**
     * Render predictions page
     */
    public function renderPredictionsPage() {
        $insights = $this->getInsights();
        ?>
        <div class="wrap">
            <h1>Predictive Analytics</h1>
            
            <div class="card">
                <h2>Model Performance</h2>
                <?php foreach ($insights['model_performance'] as $model => $metrics): ?>
                    <h3><?php echo ucfirst($model); ?> Model</h3>
                    <ul>
                        <?php foreach ($metrics as $metric => $value): ?>
                            <li><?php echo ucfirst($metric); ?>: <?php echo is_numeric($value) ? round($value, 3) : $value; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>
            
            <div class="card">
                <h2>Prediction Activity</h2>
                <canvas id="prediction-activity-chart"></canvas>
            </div>
            
            <div class="card">
                <h2>Actions</h2>
                <button class="button" onclick="evaluateModels()">Evaluate Models</button>
                <button class="button" onclick="exportPredictions()">Export Predictions</button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add dashboard widget
     */
    public function addDashboardWidget() {
        wp_add_dashboard_widget(
            'money_quiz_predictions',
            'Quiz Predictions',
            [$this, 'renderDashboardWidget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget() {
        $insights = $this->getInsights();
        ?>
        <div class="prediction-summary">
            <p>Last model update: <?php echo $insights['last_training'] ?: 'Never'; ?></p>
            <ul>
                <?php foreach ($insights['prediction_counts'] as $type => $count): ?>
                    <li><?php echo ucfirst($type); ?>: <?php echo number_format($count); ?> predictions</li>
                <?php endforeach; ?>
            </ul>
            <a href="<?php echo admin_url('admin.php?page=money-quiz-predictions'); ?>">View Details</a>
        </div>
        <?php
    }
    
    /**
     * Helper methods
     */
    private function clearPredictionCaches() {
        global $wpdb;
        
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_prediction_%'
        ");
    }
    
    private function logTrainingSession($session) {
        $log = get_option('money_quiz_training_log', []);
        $log[] = $session;
        
        // Keep last 50 sessions
        $log = array_slice($log, -50);
        
        update_option('money_quiz_training_log', $log);
    }
    
    private function calculatePrecision($predictions) {
        $true_positives = 0;
        $false_positives = 0;
        
        foreach ($predictions as $pred) {
            if ($pred['predicted'] > 0.5) {
                if ($pred['actual'] == 1) {
                    $true_positives++;
                } else {
                    $false_positives++;
                }
            }
        }
        
        if ($true_positives + $false_positives == 0) return 0;
        
        return $true_positives / ($true_positives + $false_positives);
    }
    
    private function calculateRecall($predictions) {
        $true_positives = 0;
        $false_negatives = 0;
        
        foreach ($predictions as $pred) {
            if ($pred['actual'] == 1) {
                if ($pred['predicted'] > 0.5) {
                    $true_positives++;
                } else {
                    $false_negatives++;
                }
            }
        }
        
        if ($true_positives + $false_negatives == 0) return 0;
        
        return $true_positives / ($true_positives + $false_negatives);
    }
    
    private function getPredictionCounts() {
        $log = get_option('money_quiz_prediction_accuracy', []);
        
        $counts = [];
        foreach ($log as $type => $predictions) {
            $counts[$type] = count($predictions);
        }
        
        return $counts;
    }
    
    private function getAccuracyTrends() {
        // Calculate accuracy trends over time
        return []; // Placeholder
    }
}

// Initialize Predictive Analytics
add_action('plugins_loaded', [PredictiveAnalyticsManager::class, 'init']);