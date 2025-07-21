<?php
/**
 * Cache Access Predictor
 * 
 * @package MoneyQuiz\AI\Cache
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Cache;

/**
 * Predicts cache access patterns
 */
class CachePredictor {
    
    private static $instance = null;
    private $patterns = [];
    private $predictions = [];
    
    private function __construct() {
        $this->loadPatterns();
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
     * Load access patterns
     */
    private function loadPatterns() {
        $this->patterns = get_option('money_quiz_cache_patterns', [
            'user_patterns' => [],
            'time_patterns' => [],
            'sequence_patterns' => [],
            'correlation_matrix' => []
        ]);
    }
    
    /**
     * Predict next cache accesses
     */
    public function predictNextAccesses($current_key, $limit = 5) {
        $predictions = [];
        
        // Use multiple prediction methods
        $sequence_pred = $this->sequencePrediction($current_key);
        $time_pred = $this->timePrediction($current_key);
        $correlation_pred = $this->correlationPrediction($current_key);
        $user_pred = $this->userPatternPrediction($current_key);
        
        // Combine predictions with weights
        $combined = $this->combinePredictions([
            'sequence' => ['predictions' => $sequence_pred, 'weight' => 0.3],
            'time' => ['predictions' => $time_pred, 'weight' => 0.2],
            'correlation' => ['predictions' => $correlation_pred, 'weight' => 0.3],
            'user' => ['predictions' => $user_pred, 'weight' => 0.2]
        ]);
        
        // Sort by probability and limit
        arsort($combined);
        
        return array_slice($combined, 0, $limit, true);
    }
    
    /**
     * Sequence-based prediction
     */
    private function sequencePrediction($current_key) {
        if (!isset($this->patterns['sequence_patterns'][$current_key])) {
            return [];
        }
        
        $sequences = $this->patterns['sequence_patterns'][$current_key];
        $predictions = [];
        
        foreach ($sequences as $next_key => $count) {
            $total = array_sum($sequences);
            $predictions[$next_key] = $count / $total;
        }
        
        return $predictions;
    }
    
    /**
     * Time-based prediction
     */
    private function timePrediction($current_key) {
        $current_hour = date('H');
        $current_day = date('w');
        
        if (!isset($this->patterns['time_patterns'][$current_hour][$current_day])) {
            return [];
        }
        
        $time_keys = $this->patterns['time_patterns'][$current_hour][$current_day];
        $predictions = [];
        
        foreach ($time_keys as $key => $frequency) {
            if ($key !== $current_key) {
                $predictions[$key] = $frequency / 100; // Normalize
            }
        }
        
        return $predictions;
    }
    
    /**
     * Correlation-based prediction
     */
    private function correlationPrediction($current_key) {
        if (!isset($this->patterns['correlation_matrix'][$current_key])) {
            return [];
        }
        
        $correlations = $this->patterns['correlation_matrix'][$current_key];
        $predictions = [];
        
        foreach ($correlations as $key => $correlation) {
            if ($correlation > 0.5) { // Threshold for significant correlation
                $predictions[$key] = $correlation;
            }
        }
        
        return $predictions;
    }
    
    /**
     * User pattern prediction
     */
    private function userPatternPrediction($current_key) {
        $user_id = get_current_user_id();
        
        if (!$user_id || !isset($this->patterns['user_patterns'][$user_id])) {
            return [];
        }
        
        $user_patterns = $this->patterns['user_patterns'][$user_id];
        $predictions = [];
        
        // Find keys frequently accessed by this user after current key
        if (isset($user_patterns['sequences'][$current_key])) {
            foreach ($user_patterns['sequences'][$current_key] as $next_key => $count) {
                $predictions[$next_key] = $count / array_sum($user_patterns['sequences'][$current_key]);
            }
        }
        
        return $predictions;
    }
    
    /**
     * Combine predictions from multiple methods
     */
    private function combinePredictions($prediction_sets) {
        $combined = [];
        
        foreach ($prediction_sets as $method => $data) {
            $predictions = $data['predictions'];
            $weight = $data['weight'];
            
            foreach ($predictions as $key => $probability) {
                if (!isset($combined[$key])) {
                    $combined[$key] = 0;
                }
                $combined[$key] += $probability * $weight;
            }
        }
        
        return $combined;
    }
    
    /**
     * Update patterns with new access
     */
    public function updatePatterns($key, $context = []) {
        // Update sequence patterns
        $this->updateSequencePattern($key, $context);
        
        // Update time patterns
        $this->updateTimePattern($key);
        
        // Update user patterns
        $this->updateUserPattern($key);
        
        // Update correlation matrix
        $this->updateCorrelationMatrix($key, $context);
        
        // Periodically save patterns
        if (rand(1, 100) === 1) {
            $this->savePatterns();
        }
    }
    
    /**
     * Update sequence pattern
     */
    private function updateSequencePattern($key, $context) {
        static $last_key = null;
        
        if ($last_key !== null) {
            if (!isset($this->patterns['sequence_patterns'][$last_key])) {
                $this->patterns['sequence_patterns'][$last_key] = [];
            }
            
            if (!isset($this->patterns['sequence_patterns'][$last_key][$key])) {
                $this->patterns['sequence_patterns'][$last_key][$key] = 0;
            }
            
            $this->patterns['sequence_patterns'][$last_key][$key]++;
        }
        
        $last_key = $key;
    }
    
    /**
     * Update time pattern
     */
    private function updateTimePattern($key) {
        $hour = date('H');
        $day = date('w');
        
        if (!isset($this->patterns['time_patterns'][$hour][$day])) {
            $this->patterns['time_patterns'][$hour][$day] = [];
        }
        
        if (!isset($this->patterns['time_patterns'][$hour][$day][$key])) {
            $this->patterns['time_patterns'][$hour][$day][$key] = 0;
        }
        
        $this->patterns['time_patterns'][$hour][$day][$key]++;
    }
    
    /**
     * Update user pattern
     */
    private function updateUserPattern($key) {
        $user_id = get_current_user_id();
        
        if (!$user_id) return;
        
        if (!isset($this->patterns['user_patterns'][$user_id])) {
            $this->patterns['user_patterns'][$user_id] = [
                'access_count' => [],
                'sequences' => [],
                'preferences' => []
            ];
        }
        
        // Update access count
        if (!isset($this->patterns['user_patterns'][$user_id]['access_count'][$key])) {
            $this->patterns['user_patterns'][$user_id]['access_count'][$key] = 0;
        }
        $this->patterns['user_patterns'][$user_id]['access_count'][$key]++;
        
        // Update sequences
        static $user_last_key = [];
        if (isset($user_last_key[$user_id])) {
            $last = $user_last_key[$user_id];
            
            if (!isset($this->patterns['user_patterns'][$user_id]['sequences'][$last])) {
                $this->patterns['user_patterns'][$user_id]['sequences'][$last] = [];
            }
            
            if (!isset($this->patterns['user_patterns'][$user_id]['sequences'][$last][$key])) {
                $this->patterns['user_patterns'][$user_id]['sequences'][$last][$key] = 0;
            }
            
            $this->patterns['user_patterns'][$user_id]['sequences'][$last][$key]++;
        }
        
        $user_last_key[$user_id] = $key;
    }
    
    /**
     * Update correlation matrix
     */
    private function updateCorrelationMatrix($key, $context) {
        static $session_keys = [];
        
        // Add to current session
        $session_keys[] = $key;
        
        // Keep session size reasonable
        if (count($session_keys) > 20) {
            array_shift($session_keys);
        }
        
        // Update correlations for keys in same session
        foreach ($session_keys as $other_key) {
            if ($other_key === $key) continue;
            
            if (!isset($this->patterns['correlation_matrix'][$key])) {
                $this->patterns['correlation_matrix'][$key] = [];
            }
            
            if (!isset($this->patterns['correlation_matrix'][$key][$other_key])) {
                $this->patterns['correlation_matrix'][$key][$other_key] = 0;
            }
            
            // Increase correlation score
            $this->patterns['correlation_matrix'][$key][$other_key] += 0.1;
            
            // Keep correlation symmetric
            if (!isset($this->patterns['correlation_matrix'][$other_key])) {
                $this->patterns['correlation_matrix'][$other_key] = [];
            }
            $this->patterns['correlation_matrix'][$other_key][$key] = 
                $this->patterns['correlation_matrix'][$key][$other_key];
        }
    }
    
    /**
     * Save patterns
     */
    private function savePatterns() {
        // Cleanup old patterns
        $this->cleanupPatterns();
        
        update_option('money_quiz_cache_patterns', $this->patterns);
    }
    
    /**
     * Cleanup old patterns
     */
    private function cleanupPatterns() {
        // Remove low-frequency patterns
        foreach ($this->patterns['sequence_patterns'] as $key => &$sequences) {
            $sequences = array_filter($sequences, function($count) {
                return $count > 2;
            });
            
            if (empty($sequences)) {
                unset($this->patterns['sequence_patterns'][$key]);
            }
        }
        
        // Limit user patterns per user
        foreach ($this->patterns['user_patterns'] as $user_id => &$data) {
            // Keep only top 100 accessed keys
            arsort($data['access_count']);
            $data['access_count'] = array_slice($data['access_count'], 0, 100, true);
        }
        
        // Normalize correlation matrix
        foreach ($this->patterns['correlation_matrix'] as &$correlations) {
            foreach ($correlations as &$score) {
                $score = min(1.0, $score); // Cap at 1.0
            }
        }
    }
    
    /**
     * Get pattern insights
     */
    public function getInsights() {
        return [
            'total_patterns' => count($this->patterns['sequence_patterns']),
            'user_count' => count($this->patterns['user_patterns']),
            'time_coverage' => $this->getTimeCoverage(),
            'prediction_accuracy' => $this->getPredictionAccuracy(),
            'popular_sequences' => $this->getPopularSequences()
        ];
    }
    
    /**
     * Get time coverage
     */
    private function getTimeCoverage() {
        $covered_hours = 0;
        
        for ($h = 0; $h < 24; $h++) {
            if (isset($this->patterns['time_patterns'][$h])) {
                $covered_hours++;
            }
        }
        
        return ($covered_hours / 24) * 100;
    }
    
    /**
     * Get prediction accuracy
     */
    private function getPredictionAccuracy() {
        $accuracy_log = get_option('money_quiz_cache_prediction_accuracy', []);
        
        if (empty($accuracy_log)) {
            return 0;
        }
        
        $correct = array_sum(array_column($accuracy_log, 'correct'));
        $total = count($accuracy_log);
        
        return ($correct / $total) * 100;
    }
    
    /**
     * Get popular sequences
     */
    private function getPopularSequences() {
        $all_sequences = [];
        
        foreach ($this->patterns['sequence_patterns'] as $from => $destinations) {
            foreach ($destinations as $to => $count) {
                $all_sequences[$from . ' → ' . $to] = $count;
            }
        }
        
        arsort($all_sequences);
        
        return array_slice($all_sequences, 0, 10, true);
    }
}