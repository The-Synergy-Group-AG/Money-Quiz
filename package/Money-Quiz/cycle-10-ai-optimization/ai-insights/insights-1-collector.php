<?php
/**
 * AI Insights Collector
 * 
 * @package MoneyQuiz\AI\Insights
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Insights;

/**
 * Collects AI insights
 */
class InsightsCollector {
    
    private static $instance = null;
    private $features = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function registerFeature($name, $callback) {
        $this->features[$name] = $callback;
    }
    
    public function collectAllInsights() {
        $insights = [];
        
        foreach ($this->features as $feature => $callback) {
            try {
                $insights[$feature] = apply_filters(
                    "money_quiz_ai_{$feature}_insights",
                    []
                );
            } catch (\Exception $e) {
                $insights[$feature] = ['error' => $e->getMessage()];
            }
        }
        
        // Add global metrics
        $insights['global'] = $this->getGlobalMetrics();
        
        return $insights;
    }
    
    private function getGlobalMetrics() {
        return [
            'ai_enabled' => get_option('money_quiz_ai_enabled', true),
            'total_predictions' => $this->getTotalPredictions(),
            'accuracy_rate' => $this->getOverallAccuracy(),
            'processing_time' => $this->getAvgProcessingTime(),
            'data_points' => $this->getTotalDataPoints()
        ];
    }
    
    private function getTotalPredictions() {
        $total = 0;
        
        // Sum from various sources
        $sources = [
            'money_quiz_prediction_count',
            'money_quiz_recommendation_count',
            'money_quiz_analysis_count'
        ];
        
        foreach ($sources as $option) {
            $total += intval(get_option($option, 0));
        }
        
        return $total;
    }
    
    private function getOverallAccuracy() {
        $accuracies = [];
        
        // Collect accuracy from each feature
        foreach ($this->features as $feature => $callback) {
            $insights = apply_filters("money_quiz_ai_{$feature}_insights", []);
            if (isset($insights['accuracy'])) {
                $accuracies[] = $insights['accuracy'];
            }
        }
        
        if (empty($accuracies)) return 0;
        
        return round(array_sum($accuracies) / count($accuracies), 1);
    }
    
    private function getAvgProcessingTime() {
        $performance = get_option('money_quiz_ai_performance', []);
        
        if (empty($performance['response_times'])) return 0;
        
        $all_times = [];
        foreach ($performance['response_times'] as $operation => $times) {
            $all_times = array_merge($all_times, $times);
        }
        
        if (empty($all_times)) return 0;
        
        return round(array_sum($all_times) / count($all_times), 3);
    }
    
    private function getTotalDataPoints() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results
        ");
    }
    
    public function getInsightsByFeature($feature) {
        if (!isset($this->features[$feature])) {
            return ['error' => 'Feature not found'];
        }
        
        return apply_filters("money_quiz_ai_{$feature}_insights", []);
    }
    
    public function getInsightsSummary() {
        $all_insights = $this->collectAllInsights();
        $summary = [];
        
        foreach ($all_insights as $feature => $insights) {
            if (isset($insights['error'])) {
                $summary[$feature] = 'Error';
                continue;
            }
            
            // Extract key metric
            if (isset($insights['accuracy'])) {
                $summary[$feature] = $insights['accuracy'] . '% accuracy';
            } elseif (isset($insights['total'])) {
                $summary[$feature] = $insights['total'] . ' total';
            } else {
                $summary[$feature] = 'Active';
            }
        }
        
        return $summary;
    }
}