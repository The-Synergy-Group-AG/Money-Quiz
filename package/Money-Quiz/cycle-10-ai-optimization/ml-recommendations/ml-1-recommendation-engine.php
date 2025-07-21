<?php
/**
 * ML Recommendation Engine
 * 
 * @package MoneyQuiz\AI\Recommendations
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Recommendations;

/**
 * Quiz Recommendation Engine
 */
class RecommendationEngine {
    
    private static $instance = null;
    private $algorithms = [];
    private $weights = [];
    
    private function __construct() {
        $this->initializeAlgorithms();
        $this->loadWeights();
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
     * Initialize recommendation algorithms
     */
    private function initializeAlgorithms() {
        $this->algorithms = [
            'collaborative' => [$this, 'collaborativeFiltering'],
            'content_based' => [$this, 'contentBasedFiltering'],
            'hybrid' => [$this, 'hybridRecommendation'],
            'popularity' => [$this, 'popularityBased'],
            'personalized' => [$this, 'personalizedRecommendation']
        ];
    }
    
    /**
     * Load algorithm weights
     */
    private function loadWeights() {
        $this->weights = get_option('money_quiz_ml_weights', [
            'collaborative' => 0.3,
            'content_based' => 0.25,
            'hybrid' => 0.2,
            'popularity' => 0.15,
            'personalized' => 0.1
        ]);
    }
    
    /**
     * Get quiz recommendations for user
     */
    public function getRecommendations($user_id, $limit = 5) {
        $user_data = $this->getUserData($user_id);
        $recommendations = [];
        
        foreach ($this->algorithms as $name => $algorithm) {
            $results = call_user_func($algorithm, $user_data, $limit);
            
            foreach ($results as $quiz_id => $score) {
                if (!isset($recommendations[$quiz_id])) {
                    $recommendations[$quiz_id] = 0;
                }
                $recommendations[$quiz_id] += $score * $this->weights[$name];
            }
        }
        
        arsort($recommendations);
        
        return array_slice($recommendations, 0, $limit, true);
    }
    
    /**
     * Get user data for recommendations
     */
    private function getUserData($user_id) {
        global $wpdb;
        
        return [
            'user_id' => $user_id,
            'completed_quizzes' => $this->getCompletedQuizzes($user_id),
            'preferences' => $this->getUserPreferences($user_id),
            'performance' => $this->getUserPerformance($user_id),
            'behavior' => $this->getUserBehavior($user_id)
        ];
    }
    
    /**
     * Get completed quizzes
     */
    private function getCompletedQuizzes($user_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT quiz_id 
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d
        ", $user_id));
    }
    
    /**
     * Get user preferences
     */
    private function getUserPreferences($user_id) {
        return get_user_meta($user_id, 'money_quiz_preferences', true) ?: [
            'difficulty' => 'medium',
            'topics' => [],
            'quiz_length' => 'medium'
        ];
    }
    
    /**
     * Get user performance metrics
     */
    private function getUserPerformance($user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(score) as avg_score,
                COUNT(DISTINCT quiz_id) as quizzes_taken,
                AVG(time_taken) as avg_time,
                MAX(score) as best_score
            FROM {$wpdb->prefix}money_quiz_results
            WHERE user_id = %d
        ", $user_id), ARRAY_A);
    }
    
    /**
     * Get user behavior patterns
     */
    private function getUserBehavior($user_id) {
        global $wpdb;
        
        return [
            'last_activity' => $this->getLastActivity($user_id),
            'frequency' => $this->getActivityFrequency($user_id),
            'preferred_time' => $this->getPreferredTime($user_id)
        ];
    }
    
    /**
     * Collaborative filtering algorithm
     */
    private function collaborativeFiltering($user_data, $limit) {
        global $wpdb;
        
        $similar_users = $this->findSimilarUsers($user_data['user_id']);
        $recommendations = [];
        
        foreach ($similar_users as $similar_user_id => $similarity) {
            $quizzes = $this->getCompletedQuizzes($similar_user_id);
            
            foreach ($quizzes as $quiz_id) {
                if (!in_array($quiz_id, $user_data['completed_quizzes'])) {
                    if (!isset($recommendations[$quiz_id])) {
                        $recommendations[$quiz_id] = 0;
                    }
                    $recommendations[$quiz_id] += $similarity;
                }
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Content-based filtering algorithm
     */
    private function contentBasedFiltering($user_data, $limit) {
        $preferences = $user_data['preferences'];
        $recommendations = [];
        
        // Get quizzes matching user preferences
        $matching_quizzes = $this->getQuizzesByPreferences($preferences);
        
        foreach ($matching_quizzes as $quiz) {
            if (!in_array($quiz->id, $user_data['completed_quizzes'])) {
                $score = $this->calculateContentScore($quiz, $preferences);
                $recommendations[$quiz->id] = $score;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Hybrid recommendation algorithm
     */
    private function hybridRecommendation($user_data, $limit) {
        $collab = $this->collaborativeFiltering($user_data, $limit);
        $content = $this->contentBasedFiltering($user_data, $limit);
        
        $hybrid = [];
        $all_quiz_ids = array_unique(array_merge(array_keys($collab), array_keys($content)));
        
        foreach ($all_quiz_ids as $quiz_id) {
            $hybrid[$quiz_id] = (($collab[$quiz_id] ?? 0) + ($content[$quiz_id] ?? 0)) / 2;
        }
        
        return $hybrid;
    }
    
    /**
     * Popularity-based recommendations
     */
    private function popularityBased($user_data, $limit) {
        global $wpdb;
        
        $popular = $wpdb->get_results("
            SELECT 
                quiz_id,
                COUNT(*) as popularity,
                AVG(score) as avg_score
            FROM {$wpdb->prefix}money_quiz_results
            WHERE completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY quiz_id
            ORDER BY popularity DESC
            LIMIT 20
        ");
        
        $recommendations = [];
        foreach ($popular as $quiz) {
            if (!in_array($quiz->quiz_id, $user_data['completed_quizzes'])) {
                $recommendations[$quiz->quiz_id] = $quiz->popularity / 100;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Personalized recommendations
     */
    private function personalizedRecommendation($user_data, $limit) {
        $performance = $user_data['performance'];
        $recommendations = [];
        
        // Recommend based on performance level
        $difficulty_preference = $this->determineDifficultyPreference($performance);
        
        $suitable_quizzes = $this->getQuizzesByDifficulty($difficulty_preference);
        
        foreach ($suitable_quizzes as $quiz) {
            if (!in_array($quiz->id, $user_data['completed_quizzes'])) {
                $recommendations[$quiz->id] = $this->calculatePersonalizationScore($quiz, $user_data);
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Helper methods
     */
    private function findSimilarUsers($user_id) {
        // Implementation would use cosine similarity or Pearson correlation
        return []; // Placeholder
    }
    
    private function getQuizzesByPreferences($preferences) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE status = 'published'");
    }
    
    private function calculateContentScore($quiz, $preferences) {
        return rand(1, 100) / 100; // Placeholder
    }
    
    private function determineDifficultyPreference($performance) {
        if ($performance['avg_score'] > 85) return 'hard';
        if ($performance['avg_score'] > 70) return 'medium';
        return 'easy';
    }
    
    private function getQuizzesByDifficulty($difficulty) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE status = 'published'");
    }
    
    private function calculatePersonalizationScore($quiz, $user_data) {
        return rand(1, 100) / 100; // Placeholder
    }
    
    private function getLastActivity($user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(completed_at) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
    }
    
    private function getActivityFrequency($user_id) {
        // Implementation would calculate activity frequency
        return 'weekly'; // Placeholder
    }
    
    private function getPreferredTime($user_id) {
        // Implementation would analyze activity patterns
        return 'evening'; // Placeholder
    }
}