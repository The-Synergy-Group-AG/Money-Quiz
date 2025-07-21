<?php
/**
 * ML Training Pipeline
 * 
 * @package MoneyQuiz\AI\Training
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Training;

/**
 * Training Pipeline
 */
class MLTrainingPipeline {
    
    private static $instance = null;
    private $models = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function registerModel($name, $model) {
        $this->models[$name] = $model;
    }
    
    public function train($model_name = null) {
        $start_time = microtime(true);
        
        // Collect training data
        $data = $this->collectTrainingData();
        
        if (empty($data)) {
            return ['error' => 'No training data available'];
        }
        
        // Train specific model or all
        $models_to_train = $model_name ? [$model_name => $this->models[$model_name]] : $this->models;
        $results = [];
        
        foreach ($models_to_train as $name => $model) {
            if (!$model) continue;
            
            $results[$name] = $this->trainModel($name, $model, $data);
        }
        
        $duration = microtime(true) - $start_time;
        
        // Log training session
        $this->logTraining([
            'models' => array_keys($results),
            'data_points' => count($data),
            'duration' => $duration,
            'results' => $results,
            'timestamp' => current_time('mysql')
        ]);
        
        return $results;
    }
    
    private function collectTrainingData() {
        global $wpdb;
        
        // Get recent results for training
        $results = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}money_quiz_results
            WHERE completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY completed_at DESC
            LIMIT 1000
        ", ARRAY_A);
        
        // Get user patterns
        $patterns = get_transient('money_quiz_ai_patterns');
        
        return [
            'results' => $results,
            'patterns' => $patterns ?: []
        ];
    }
    
    private function trainModel($name, $model, $data) {
        try {
            if (method_exists($model, 'train')) {
                return $model->train($data);
            }
            
            return ['error' => 'Model does not support training'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function logTraining($session) {
        $log = get_option('money_quiz_ml_training_log', []);
        $log[] = $session;
        
        // Keep last 100 sessions
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        
        update_option('money_quiz_ml_training_log', $log);
    }
    
    public function evaluate($model_name) {
        if (!isset($this->models[$model_name])) {
            return ['error' => 'Model not found'];
        }
        
        $model = $this->models[$model_name];
        
        if (!method_exists($model, 'evaluate')) {
            return ['error' => 'Model does not support evaluation'];
        }
        
        // Get test data
        $test_data = $this->getTestData();
        
        return $model->evaluate($test_data);
    }
    
    private function getTestData() {
        global $wpdb;
        
        // Get older data for testing
        return $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}money_quiz_results
            WHERE completed_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) 
                AND DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY RAND()
            LIMIT 200
        ", ARRAY_A);
    }
    
    public function getTrainingStatus() {
        $log = get_option('money_quiz_ml_training_log', []);
        
        if (empty($log)) {
            return ['last_training' => 'Never', 'models_trained' => 0];
        }
        
        $last = end($log);
        
        return [
            'last_training' => $last['timestamp'],
            'models_trained' => count($last['models']),
            'data_points' => $last['data_points'],
            'duration' => round($last['duration'], 2)
        ];
    }
}