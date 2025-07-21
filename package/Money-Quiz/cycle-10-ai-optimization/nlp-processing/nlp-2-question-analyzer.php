<?php
/**
 * NLP Question Analyzer
 * 
 * @package MoneyQuiz\AI\NLP
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\NLP;

/**
 * Advanced Question Analysis
 */
class QuestionAnalyzer {
    
    private static $instance = null;
    private $processor;
    private $question_patterns = [];
    
    private function __construct() {
        $this->processor = NLPProcessor::getInstance();
        $this->loadQuestionPatterns();
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
     * Load question patterns
     */
    private function loadQuestionPatterns() {
        $this->question_patterns = [
            'factual' => [
                'pattern' => '/^(what|which|who|when|where)\s/i',
                'characteristics' => ['objective', 'single_answer', 'knowledge_based']
            ],
            'analytical' => [
                'pattern' => '/^(why|how)\s/i',
                'characteristics' => ['reasoning', 'multiple_perspectives', 'complex']
            ],
            'evaluative' => [
                'pattern' => '/(evaluate|assess|judge|rate|rank)/i',
                'characteristics' => ['subjective', 'criteria_based', 'judgment']
            ],
            'hypothetical' => [
                'pattern' => '/(if|would|could|should|imagine)/i',
                'characteristics' => ['scenario_based', 'predictive', 'creative']
            ],
            'reflective' => [
                'pattern' => '/(think|feel|believe|consider)/i',
                'characteristics' => ['personal', 'introspective', 'emotional']
            ]
        ];
    }
    
    /**
     * Analyze quiz questions
     */
    public function analyzeQuizQuestions($quiz_id) {
        global $wpdb;
        
        $questions = $wpdb->get_results($wpdb->prepare("
            SELECT id, text, type, correct_answer, options
            FROM {$wpdb->prefix}money_quiz_questions
            WHERE quiz_id = %d
        ", $quiz_id));
        
        $analysis = [
            'total_questions' => count($questions),
            'question_types' => [],
            'complexity_distribution' => [],
            'topics' => [],
            'quality_score' => 0,
            'suggestions' => []
        ];
        
        foreach ($questions as $question) {
            $q_analysis = $this->analyzeQuestion($question);
            
            // Aggregate analysis
            $analysis['question_types'][$q_analysis['type']][] = $question->id;
            $analysis['complexity_distribution'][] = $q_analysis['complexity'];
            $analysis['topics'][] = $q_analysis['topic'];
        }
        
        // Calculate overall metrics
        $analysis['quality_score'] = $this->calculateQuizQuality($analysis);
        $analysis['suggestions'] = $this->generateSuggestions($analysis);
        
        return $analysis;
    }
    
    /**
     * Analyze individual question
     */
    public function analyzeQuestion($question) {
        $text_analysis = $this->processor->processQuestion($question->text);
        
        return [
            'id' => $question->id,
            'type' => $this->classifyQuestionType($question->text),
            'complexity' => $text_analysis['complexity']['complexity_score'],
            'readability' => $text_analysis['readability'],
            'topic' => $text_analysis['topic']['primary'],
            'keywords' => $text_analysis['keywords'],
            'quality_issues' => $this->identifyQualityIssues($question, $text_analysis),
            'improvement_suggestions' => $this->suggestImprovements($question, $text_analysis)
        ];
    }
    
    /**
     * Classify question type
     */
    private function classifyQuestionType($text) {
        foreach ($this->question_patterns as $type => $config) {
            if (preg_match($config['pattern'], $text)) {
                return $type;
            }
        }
        return 'general';
    }
    
    /**
     * Identify quality issues
     */
    private function identifyQualityIssues($question, $analysis) {
        $issues = [];
        
        // Check readability
        if ($analysis['readability']['score'] < 40) {
            $issues[] = [
                'type' => 'readability',
                'severity' => 'high',
                'message' => 'Question may be too difficult to read'
            ];
        }
        
        // Check length
        if ($analysis['complexity']['word_count'] > 50) {
            $issues[] = [
                'type' => 'length',
                'severity' => 'medium',
                'message' => 'Question is too long'
            ];
        }
        
        // Check ambiguity
        if ($this->checkAmbiguity($question->text)) {
            $issues[] = [
                'type' => 'ambiguity',
                'severity' => 'high',
                'message' => 'Question contains ambiguous terms'
            ];
        }
        
        // Check answer options
        if ($question->type === 'multiple' && $question->options) {
            $option_issues = $this->checkAnswerOptions(json_decode($question->options, true));
            $issues = array_merge($issues, $option_issues);
        }
        
        return $issues;
    }
    
    /**
     * Check for ambiguity
     */
    private function checkAmbiguity($text) {
        $ambiguous_terms = ['might', 'maybe', 'possibly', 'somewhat', 'usually', 'often', 'sometimes'];
        $text_lower = strtolower($text);
        
        foreach ($ambiguous_terms as $term) {
            if (strpos($text_lower, $term) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check answer options
     */
    private function checkAnswerOptions($options) {
        $issues = [];
        
        if (!is_array($options)) {
            return $issues;
        }
        
        // Check for duplicate options
        if (count($options) !== count(array_unique($options))) {
            $issues[] = [
                'type' => 'duplicate_options',
                'severity' => 'high',
                'message' => 'Answer options contain duplicates'
            ];
        }
        
        // Check option length consistency
        $lengths = array_map('strlen', $options);
        $avg_length = array_sum($lengths) / count($lengths);
        
        foreach ($lengths as $i => $length) {
            if (abs($length - $avg_length) > $avg_length * 0.5) {
                $issues[] = [
                    'type' => 'option_length',
                    'severity' => 'low',
                    'message' => 'Answer option lengths are inconsistent'
                ];
                break;
            }
        }
        
        // Check for obvious wrong answers
        if ($this->hasObviousWrongAnswers($options)) {
            $issues[] = [
                'type' => 'obvious_wrong',
                'severity' => 'medium',
                'message' => 'Some answer options are obviously wrong'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Check for obvious wrong answers
     */
    private function hasObviousWrongAnswers($options) {
        $obvious_patterns = ['none of the above', 'all of the above', 'n/a', 'not applicable'];
        
        foreach ($options as $option) {
            $option_lower = strtolower($option);
            foreach ($obvious_patterns as $pattern) {
                if (strpos($option_lower, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Suggest improvements
     */
    private function suggestImprovements($question, $analysis) {
        $suggestions = [];
        
        // Readability improvements
        if ($analysis['readability']['score'] < 60) {
            $suggestions[] = [
                'type' => 'simplify',
                'suggestion' => $this->processor->generateSimilarQuestions($question->text, 1)[0] ?? null
            ];
        }
        
        // Length optimization
        if ($analysis['complexity']['avg_words_per_sentence'] > 20) {
            $suggestions[] = [
                'type' => 'shorten',
                'suggestion' => 'Break this question into shorter sentences'
            ];
        }
        
        // Keyword optimization
        if (empty($analysis['keywords'])) {
            $suggestions[] = [
                'type' => 'keywords',
                'suggestion' => 'Add more specific keywords to improve clarity'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Calculate quiz quality score
     */
    private function calculateQuizQuality($analysis) {
        $score = 100;
        
        // Deduct for lack of variety
        $type_variety = count(array_unique(array_keys($analysis['question_types'])));
        if ($type_variety < 3) {
            $score -= 20;
        }
        
        // Deduct for complexity imbalance
        $complexity_values = $analysis['complexity_distribution'];
        $complexity_std = $this->calculateStandardDeviation($complexity_values);
        if ($complexity_std > 3) {
            $score -= 15;
        }
        
        // Deduct for topic concentration
        $topic_counts = array_count_values($analysis['topics']);
        $max_topic_percentage = max($topic_counts) / count($analysis['topics']);
        if ($max_topic_percentage > 0.7) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Generate quiz suggestions
     */
    private function generateSuggestions($analysis) {
        $suggestions = [];
        
        // Question type diversity
        if (count($analysis['question_types']) < 3) {
            $suggestions[] = 'Add more variety in question types (analytical, reflective, etc.)';
        }
        
        // Complexity balance
        $avg_complexity = array_sum($analysis['complexity_distribution']) / count($analysis['complexity_distribution']);
        if ($avg_complexity > 7) {
            $suggestions[] = 'Consider adding easier questions to balance difficulty';
        } elseif ($avg_complexity < 3) {
            $suggestions[] = 'Consider adding more challenging questions';
        }
        
        // Topic diversity
        $unique_topics = count(array_unique($analysis['topics']));
        if ($unique_topics < 3) {
            $suggestions[] = 'Diversify topics to create a more comprehensive quiz';
        }
        
        return $suggestions;
    }
    
    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation($values) {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= count($values);
        
        return sqrt($variance);
    }
    
    /**
     * Generate question from topic
     */
    public function generateQuestionFromTopic($topic, $difficulty = 'medium') {
        $templates = [
            'financial' => [
                'easy' => 'What is the most important aspect of {concept} for you?',
                'medium' => 'How does {concept} affect your financial decisions?',
                'hard' => 'Analyze the relationship between {concept} and long-term wealth building.'
            ],
            'psychological' => [
                'easy' => 'How do you feel about {concept}?',
                'medium' => 'What beliefs shape your approach to {concept}?',
                'hard' => 'Examine how childhood experiences might influence your {concept}.'
            ]
        ];
        
        if (isset($templates[$topic][$difficulty])) {
            return str_replace('{concept}', $this->getTopicConcept($topic), $templates[$topic][$difficulty]);
        }
        
        return "Tell us about your experience with this topic.";
    }
    
    /**
     * Get topic concept
     */
    private function getTopicConcept($topic) {
        $concepts = [
            'financial' => ['saving money', 'investing', 'budgeting', 'financial planning'],
            'psychological' => ['money mindset', 'financial stress', 'abundance thinking', 'scarcity fears'],
            'behavioral' => ['spending habits', 'financial routines', 'money management', 'saving behaviors'],
            'educational' => ['financial literacy', 'money knowledge', 'investment understanding'],
            'motivational' => ['financial goals', 'wealth aspirations', 'success metrics']
        ];
        
        $topic_concepts = $concepts[$topic] ?? ['this topic'];
        return $topic_concepts[array_rand($topic_concepts)];
    }
}