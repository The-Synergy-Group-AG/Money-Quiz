<?php
/**
 * User Similarity Calculator
 * 
 * @package MoneyQuiz\AI\Recommendations
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Recommendations;

/**
 * Calculate user similarities for recommendations
 */
class SimilarityCalculator {
    
    private static $instance = null;
    private $cache = [];
    private $cache_duration = 3600;
    
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
     * Calculate similarity between two users
     */
    public function calculateUserSimilarity($user1_id, $user2_id) {
        $cache_key = min($user1_id, $user2_id) . '_' . max($user1_id, $user2_id);
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $user1_data = $this->getUserVector($user1_id);
        $user2_data = $this->getUserVector($user2_id);
        
        $similarity = $this->cosineSimilarity($user1_data, $user2_data);
        
        $this->cache[$cache_key] = $similarity;
        
        return $similarity;
    }
    
    /**
     * Find most similar users
     */
    public function findSimilarUsers($user_id, $limit = 10) {
        global $wpdb;
        
        // Get all other users who have taken quizzes
        $other_users = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id != %d AND user_id > 0
            LIMIT 100
        ");
        
        $similarities = [];
        
        foreach ($other_users as $other_user_id) {
            $similarity = $this->calculateUserSimilarity($user_id, $other_user_id);
            if ($similarity > 0.5) { // Threshold for relevance
                $similarities[$other_user_id] = $similarity;
            }
        }
        
        arsort($similarities);
        
        return array_slice($similarities, 0, $limit, true);
    }
    
    /**
     * Get user vector for similarity calculation
     */
    private function getUserVector($user_id) {
        global $wpdb;
        
        // Get user's quiz scores
        $quiz_scores = $wpdb->get_results($wpdb->prepare("
            SELECT quiz_id, AVG(score) as avg_score
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d
            GROUP BY quiz_id
        ", $user_id), OBJECT_K);
        
        // Get user behavior metrics
        $behavior = $this->getUserBehaviorVector($user_id);
        
        // Get user preferences
        $preferences = $this->getUserPreferenceVector($user_id);
        
        return [
            'scores' => $quiz_scores,
            'behavior' => $behavior,
            'preferences' => $preferences
        ];
    }
    
    /**
     * Get user behavior vector
     */
    private function getUserBehaviorVector($user_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                AVG(time_taken) as avg_time,
                STD(score) as score_variance,
                DATEDIFF(MAX(completed_at), MIN(completed_at)) as days_active,
                COUNT(DISTINCT DATE(completed_at)) as active_days
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d
        ", $user_id), ARRAY_A);
        
        return [
            'engagement' => $stats['total_attempts'] / max(1, $stats['days_active']),
            'consistency' => 1 - ($stats['score_variance'] / 100),
            'speed' => 1 / (1 + $stats['avg_time'] / 60),
            'persistence' => $stats['active_days'] / max(1, $stats['days_active'])
        ];
    }
    
    /**
     * Get user preference vector
     */
    private function getUserPreferenceVector($user_id) {
        $preferences = get_user_meta($user_id, 'money_quiz_preferences', true) ?: [];
        
        return [
            'difficulty' => $this->encodeDifficulty($preferences['difficulty'] ?? 'medium'),
            'length' => $this->encodeLength($preferences['quiz_length'] ?? 'medium'),
            'topics' => $this->encodeTopics($preferences['topics'] ?? [])
        ];
    }
    
    /**
     * Calculate cosine similarity
     */
    private function cosineSimilarity($vector1, $vector2) {
        // Calculate score similarity
        $score_sim = $this->calculateScoreSimilarity($vector1['scores'], $vector2['scores']);
        
        // Calculate behavior similarity
        $behavior_sim = $this->calculateVectorSimilarity($vector1['behavior'], $vector2['behavior']);
        
        // Calculate preference similarity
        $pref_sim = $this->calculatePreferenceSimilarity($vector1['preferences'], $vector2['preferences']);
        
        // Weighted combination
        return (0.5 * $score_sim) + (0.3 * $behavior_sim) + (0.2 * $pref_sim);
    }
    
    /**
     * Calculate score similarity
     */
    private function calculateScoreSimilarity($scores1, $scores2) {
        $common_quizzes = array_intersect_key($scores1, $scores2);
        
        if (empty($common_quizzes)) {
            return 0;
        }
        
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        foreach ($common_quizzes as $quiz_id => $score1) {
            $score2 = $scores2[$quiz_id]->avg_score;
            $score1 = $score1->avg_score;
            
            $dot_product += $score1 * $score2;
            $norm1 += $score1 * $score1;
            $norm2 += $score2 * $score2;
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }
    
    /**
     * Calculate vector similarity
     */
    private function calculateVectorSimilarity($vector1, $vector2) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        foreach ($vector1 as $key => $value1) {
            $value2 = $vector2[$key] ?? 0;
            
            $dot_product += $value1 * $value2;
            $norm1 += $value1 * $value1;
            $norm2 += $value2 * $value2;
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }
    
    /**
     * Calculate preference similarity
     */
    private function calculatePreferenceSimilarity($pref1, $pref2) {
        $similarity = 0;
        $count = 0;
        
        // Difficulty similarity
        $similarity += 1 - abs($pref1['difficulty'] - $pref2['difficulty']) / 2;
        $count++;
        
        // Length similarity
        $similarity += 1 - abs($pref1['length'] - $pref2['length']) / 2;
        $count++;
        
        // Topic similarity
        if (!empty($pref1['topics']) && !empty($pref2['topics'])) {
            $similarity += $this->calculateTopicSimilarity($pref1['topics'], $pref2['topics']);
            $count++;
        }
        
        return $similarity / $count;
    }
    
    /**
     * Calculate topic similarity
     */
    private function calculateTopicSimilarity($topics1, $topics2) {
        $intersection = array_intersect($topics1, $topics2);
        $union = array_unique(array_merge($topics1, $topics2));
        
        if (empty($union)) {
            return 0;
        }
        
        return count($intersection) / count($union);
    }
    
    /**
     * Encoding helper methods
     */
    private function encodeDifficulty($difficulty) {
        $map = ['easy' => 0, 'medium' => 1, 'hard' => 2];
        return $map[$difficulty] ?? 1;
    }
    
    private function encodeLength($length) {
        $map = ['short' => 0, 'medium' => 1, 'long' => 2];
        return $map[$length] ?? 1;
    }
    
    private function encodeTopics($topics) {
        // In a real implementation, this would create a feature vector
        return $topics;
    }
    
    /**
     * Clear similarity cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Batch calculate similarities
     */
    public function batchCalculateSimilarities($user_ids) {
        $results = [];
        
        for ($i = 0; $i < count($user_ids); $i++) {
            for ($j = $i + 1; $j < count($user_ids); $j++) {
                $similarity = $this->calculateUserSimilarity($user_ids[$i], $user_ids[$j]);
                $results[$user_ids[$i]][$user_ids[$j]] = $similarity;
                $results[$user_ids[$j]][$user_ids[$i]] = $similarity;
            }
        }
        
        return $results;
    }
}