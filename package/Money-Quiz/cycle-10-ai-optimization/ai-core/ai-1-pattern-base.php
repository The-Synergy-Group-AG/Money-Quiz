<?php
/**
 * AI Pattern Recognition Base
 * 
 * @package MoneyQuiz\AI\Core
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Core;

/**
 * Pattern Recognition Engine
 */
class PatternRecognition {
    
    private static $instance = null;
    private $patterns = [];
    private $threshold = 0.75;
    
    private function __construct() {
        $this->initializePatterns();
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
     * Initialize pattern definitions
     */
    private function initializePatterns() {
        $this->patterns = [
            'quiz_completion' => [
                'features' => ['time_taken', 'score', 'attempts', 'device'],
                'weight' => [0.3, 0.4, 0.2, 0.1]
            ],
            'user_behavior' => [
                'features' => ['login_frequency', 'quiz_frequency', 'avg_score', 'completion_rate'],
                'weight' => [0.2, 0.3, 0.3, 0.2]
            ],
            'question_difficulty' => [
                'features' => ['correct_rate', 'avg_time', 'skip_rate', 'retry_rate'],
                'weight' => [0.4, 0.2, 0.2, 0.2]
            ]
        ];
    }
    
    /**
     * Detect pattern in data
     */
    public function detectPattern($type, $data) {
        if (!isset($this->patterns[$type])) {
            return null;
        }
        
        $pattern = $this->patterns[$type];
        $score = $this->calculatePatternScore($pattern, $data);
        
        return [
            'type' => $type,
            'score' => $score,
            'confidence' => $this->calculateConfidence($score),
            'features' => $this->extractFeatures($pattern, $data)
        ];
    }
    
    /**
     * Calculate pattern score
     */
    private function calculatePatternScore($pattern, $data) {
        $score = 0;
        $features = $pattern['features'];
        $weights = $pattern['weight'];
        
        foreach ($features as $i => $feature) {
            if (isset($data[$feature])) {
                $normalized = $this->normalizeValue($data[$feature]);
                $score += $normalized * $weights[$i];
            }
        }
        
        return $score;
    }
    
    /**
     * Normalize value to 0-1 range
     */
    private function normalizeValue($value) {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        
        if (is_numeric($value)) {
            return max(0, min(1, $value / 100));
        }
        
        return 0;
    }
    
    /**
     * Calculate confidence level
     */
    private function calculateConfidence($score) {
        if ($score >= 0.9) return 'very_high';
        if ($score >= 0.75) return 'high';
        if ($score >= 0.6) return 'medium';
        if ($score >= 0.4) return 'low';
        return 'very_low';
    }
    
    /**
     * Extract features from data
     */
    private function extractFeatures($pattern, $data) {
        $features = [];
        
        foreach ($pattern['features'] as $feature) {
            $features[$feature] = $data[$feature] ?? null;
        }
        
        return $features;
    }
    
    /**
     * Find similar patterns
     */
    public function findSimilar($patternData, $dataset, $limit = 5) {
        $similarities = [];
        
        foreach ($dataset as $id => $data) {
            $similarity = $this->calculateSimilarity($patternData, $data);
            if ($similarity >= $this->threshold) {
                $similarities[$id] = $similarity;
            }
        }
        
        arsort($similarities);
        return array_slice($similarities, 0, $limit, true);
    }
    
    /**
     * Calculate similarity between patterns
     */
    private function calculateSimilarity($pattern1, $pattern2) {
        $common = array_intersect_key($pattern1, $pattern2);
        
        if (empty($common)) {
            return 0;
        }
        
        $distance = 0;
        foreach ($common as $key => $value) {
            $distance += pow($pattern1[$key] - $pattern2[$key], 2);
        }
        
        return 1 / (1 + sqrt($distance));
    }
    
    /**
     * Update pattern weights
     */
    public function updateWeights($type, $feedback) {
        if (!isset($this->patterns[$type])) {
            return false;
        }
        
        $pattern = &$this->patterns[$type];
        $learningRate = 0.1;
        
        foreach ($pattern['weight'] as $i => &$weight) {
            $adjustment = $feedback['adjustments'][$i] ?? 0;
            $weight += $learningRate * $adjustment;
            $weight = max(0, min(1, $weight));
        }
        
        // Normalize weights
        $sum = array_sum($pattern['weight']);
        if ($sum > 0) {
            foreach ($pattern['weight'] as &$weight) {
                $weight /= $sum;
            }
        }
        
        return true;
    }
}