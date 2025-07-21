<?php
/**
 * AI Data Processor
 * 
 * @package MoneyQuiz\AI\Core
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Core;

/**
 * Data Processing Engine for AI
 */
class AIDataProcessor {
    
    private static $instance = null;
    private $cache = [];
    
    private function __construct() {}
    
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
     * Prepare quiz data for AI processing
     */
    public function prepareQuizData($quiz_id, $options = []) {
        global $wpdb;
        
        $data = [
            'quiz_metrics' => $this->getQuizMetrics($quiz_id),
            'user_patterns' => $this->getUserPatterns($quiz_id),
            'temporal_data' => $this->getTemporalData($quiz_id),
            'question_stats' => $this->getQuestionStats($quiz_id)
        ];
        
        if (!empty($options['include_raw'])) {
            $data['raw_results'] = $this->getRawResults($quiz_id, $options['limit'] ?? 100);
        }
        
        return $this->normalizeData($data);
    }
    
    /**
     * Get quiz metrics
     */
    private function getQuizMetrics($quiz_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as total_attempts,
                AVG(score) as avg_score,
                MIN(score) as min_score,
                MAX(score) as max_score,
                STD(score) as score_std_dev,
                AVG(time_taken) as avg_time,
                SUM(CASE WHEN score >= 70 THEN 1 ELSE 0 END) / COUNT(*) * 100 as pass_rate
            FROM {$wpdb->prefix}money_quiz_results
            WHERE quiz_id = %d
        ", $quiz_id), ARRAY_A);
    }
    
    /**
     * Get user behavior patterns
     */
    private function getUserPatterns($quiz_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                user_id,
                COUNT(*) as attempts,
                AVG(score) as avg_score,
                MAX(score) as best_score,
                MIN(time_taken) as fastest_time,
                DATEDIFF(MAX(completed_at), MIN(completed_at)) as days_active
            FROM {$wpdb->prefix}money_quiz_results
            WHERE quiz_id = %d AND user_id > 0
            GROUP BY user_id
            HAVING attempts > 1
        ", $quiz_id), ARRAY_A);
    }
    
    /**
     * Get temporal data
     */
    private function getTemporalData($quiz_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(completed_at) as date,
                HOUR(completed_at) as hour,
                COUNT(*) as attempts,
                AVG(score) as avg_score,
                AVG(time_taken) as avg_time
            FROM {$wpdb->prefix}money_quiz_results
            WHERE quiz_id = %d 
            AND completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(completed_at), HOUR(completed_at)
        ", $quiz_id), ARRAY_A);
    }
    
    /**
     * Get question statistics
     */
    private function getQuestionStats($quiz_id) {
        global $wpdb;
        
        $questions = $wpdb->get_results($wpdb->prepare("
            SELECT id, text, correct_answer 
            FROM {$wpdb->prefix}money_quiz_questions
            WHERE quiz_id = %d
        ", $quiz_id));
        
        $stats = [];
        foreach ($questions as $question) {
            $stats[$question->id] = $this->calculateQuestionStats($question->id);
        }
        
        return $stats;
    }
    
    /**
     * Calculate individual question statistics
     */
    private function calculateQuestionStats($question_id) {
        global $wpdb;
        
        // This would analyze answer patterns from results
        return [
            'difficulty' => rand(1, 10) / 10, // Placeholder
            'discrimination' => rand(1, 10) / 10,
            'avg_time' => rand(10, 60),
            'skip_rate' => rand(0, 20) / 100
        ];
    }
    
    /**
     * Get raw results
     */
    private function getRawResults($quiz_id, $limit) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}money_quiz_results
            WHERE quiz_id = %d
            ORDER BY completed_at DESC
            LIMIT %d
        ", $quiz_id, $limit), ARRAY_A);
    }
    
    /**
     * Normalize data for AI processing
     */
    private function normalizeData($data) {
        $normalized = [];
        
        foreach ($data as $key => $values) {
            if (is_array($values)) {
                $normalized[$key] = $this->normalizeArray($values);
            } else {
                $normalized[$key] = $values;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Normalize array values
     */
    private function normalizeArray($array) {
        if (empty($array)) return $array;
        
        // For numeric arrays, normalize to 0-1 range
        $normalized = [];
        foreach ($array as $key => $value) {
            if (is_numeric($value)) {
                $normalized[$key] = $this->scaleValue($value);
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeArray($value);
            } else {
                $normalized[$key] = $value;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Scale value to 0-1 range
     */
    private function scaleValue($value, $min = 0, $max = 100) {
        if ($max == $min) return 0;
        return ($value - $min) / ($max - $min);
    }
    
    /**
     * Extract features for ML
     */
    public function extractFeatures($data) {
        return [
            'statistical' => $this->extractStatisticalFeatures($data),
            'temporal' => $this->extractTemporalFeatures($data),
            'behavioral' => $this->extractBehavioralFeatures($data)
        ];
    }
    
    /**
     * Extract statistical features
     */
    private function extractStatisticalFeatures($data) {
        if (!isset($data['quiz_metrics'])) return [];
        
        $metrics = $data['quiz_metrics'];
        return [
            'mean_score' => $metrics['avg_score'] ?? 0,
            'score_variance' => $metrics['score_std_dev'] ?? 0,
            'pass_rate' => $metrics['pass_rate'] ?? 0,
            'engagement_rate' => $metrics['unique_users'] / max(1, $metrics['total_attempts'])
        ];
    }
    
    /**
     * Extract temporal features
     */
    private function extractTemporalFeatures($data) {
        if (!isset($data['temporal_data'])) return [];
        
        return [
            'peak_hours' => $this->findPeakHours($data['temporal_data']),
            'trend' => $this->calculateTrend($data['temporal_data']),
            'seasonality' => $this->detectSeasonality($data['temporal_data'])
        ];
    }
    
    /**
     * Extract behavioral features
     */
    private function extractBehavioralFeatures($data) {
        if (!isset($data['user_patterns'])) return [];
        
        return [
            'retention_rate' => $this->calculateRetention($data['user_patterns']),
            'improvement_rate' => $this->calculateImprovement($data['user_patterns']),
            'engagement_depth' => $this->calculateEngagementDepth($data['user_patterns'])
        ];
    }
    
    /**
     * Helper methods
     */
    private function findPeakHours($temporal_data) {
        // Implementation would find peak activity hours
        return [9, 14, 20]; // Placeholder
    }
    
    private function calculateTrend($temporal_data) {
        // Implementation would calculate trend
        return 'increasing'; // Placeholder
    }
    
    private function detectSeasonality($temporal_data) {
        // Implementation would detect seasonal patterns
        return 'weekly'; // Placeholder
    }
    
    private function calculateRetention($user_patterns) {
        // Implementation would calculate retention
        return 0.75; // Placeholder
    }
    
    private function calculateImprovement($user_patterns) {
        // Implementation would calculate improvement rate
        return 0.15; // Placeholder
    }
    
    private function calculateEngagementDepth($user_patterns) {
        // Implementation would calculate engagement depth
        return 0.6; // Placeholder
    }
}