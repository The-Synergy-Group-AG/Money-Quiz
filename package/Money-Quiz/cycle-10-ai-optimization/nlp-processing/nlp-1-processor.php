<?php
/**
 * NLP Question Processor
 * 
 * @package MoneyQuiz\AI\NLP
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\NLP;

/**
 * Natural Language Processing for Questions
 */
class NLPProcessor {
    
    private static $instance = null;
    private $stop_words = [];
    private $sentiment_lexicon = [];
    
    private function __construct() {
        $this->loadResources();
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
     * Load NLP resources
     */
    private function loadResources() {
        $this->stop_words = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for',
            'from', 'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on',
            'that', 'the', 'to', 'was', 'will', 'with'
        ];
        
        $this->sentiment_lexicon = [
            'positive' => ['good', 'great', 'excellent', 'best', 'amazing', 'perfect', 'wonderful'],
            'negative' => ['bad', 'poor', 'worst', 'terrible', 'awful', 'horrible', 'difficult'],
            'neutral' => ['okay', 'average', 'normal', 'standard', 'typical', 'regular']
        ];
    }
    
    /**
     * Process question text
     */
    public function processQuestion($text) {
        $analysis = [
            'tokens' => $this->tokenize($text),
            'keywords' => $this->extractKeywords($text),
            'complexity' => $this->analyzeComplexity($text),
            'readability' => $this->calculateReadability($text),
            'topic' => $this->identifyTopic($text),
            'sentiment' => $this->analyzeSentiment($text),
            'entities' => $this->extractEntities($text)
        ];
        
        return $analysis;
    }
    
    /**
     * Tokenize text
     */
    private function tokenize($text) {
        // Convert to lowercase and split
        $text = strtolower($text);
        $tokens = preg_split('/\s+/', $text);
        
        // Remove punctuation
        $tokens = array_map(function($token) {
            return preg_replace('/[^\w\s]/', '', $token);
        }, $tokens);
        
        // Remove empty tokens
        return array_filter($tokens);
    }
    
    /**
     * Extract keywords
     */
    public function extractKeywords($text) {
        $tokens = $this->tokenize($text);
        
        // Remove stop words
        $keywords = array_diff($tokens, $this->stop_words);
        
        // Calculate term frequency
        $tf = array_count_values($keywords);
        
        // Sort by frequency
        arsort($tf);
        
        // Return top keywords
        return array_slice($tf, 0, 5, true);
    }
    
    /**
     * Analyze text complexity
     */
    private function analyzeComplexity($text) {
        $sentences = preg_split('/[.!?]+/', $text);
        $words = str_word_count($text, 0);
        $syllables = $this->countSyllables($text);
        
        return [
            'word_count' => $words,
            'sentence_count' => count(array_filter($sentences)),
            'avg_words_per_sentence' => $words / max(1, count($sentences)),
            'syllable_count' => $syllables,
            'complexity_score' => $this->calculateComplexityScore($words, $sentences, $syllables)
        ];
    }
    
    /**
     * Calculate readability score
     */
    private function calculateReadability($text) {
        $words = str_word_count($text, 0);
        $sentences = count(preg_split('/[.!?]+/', $text));
        $syllables = $this->countSyllables($text);
        
        // Flesch Reading Ease
        if ($sentences > 0 && $words > 0) {
            $score = 206.835 - 1.015 * ($words / $sentences) - 84.6 * ($syllables / $words);
            
            return [
                'score' => max(0, min(100, $score)),
                'level' => $this->getReadingLevel($score),
                'grade' => $this->estimateGradeLevel($score)
            ];
        }
        
        return ['score' => 50, 'level' => 'medium', 'grade' => 8];
    }
    
    /**
     * Identify question topic
     */
    private function identifyTopic($text) {
        $topics = [
            'financial' => ['money', 'finance', 'budget', 'invest', 'save', 'spend', 'income'],
            'psychological' => ['feel', 'think', 'believe', 'emotion', 'mindset', 'attitude'],
            'behavioral' => ['do', 'act', 'habit', 'practice', 'behavior', 'action', 'routine'],
            'educational' => ['learn', 'know', 'understand', 'study', 'education', 'knowledge'],
            'motivational' => ['goal', 'dream', 'achieve', 'success', 'motivate', 'inspire']
        ];
        
        $text_lower = strtolower($text);
        $topic_scores = [];
        
        foreach ($topics as $topic => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($text_lower, $keyword);
            }
            $topic_scores[$topic] = $score;
        }
        
        arsort($topic_scores);
        $top_topic = key($topic_scores);
        
        return [
            'primary' => $top_topic,
            'confidence' => $topic_scores[$top_topic] / max(1, array_sum($topic_scores)),
            'all_scores' => $topic_scores
        ];
    }
    
    /**
     * Analyze sentiment
     */
    private function analyzeSentiment($text) {
        $text_lower = strtolower($text);
        $scores = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($this->sentiment_lexicon as $sentiment => $words) {
            foreach ($words as $word) {
                $scores[$sentiment] += substr_count($text_lower, $word);
            }
        }
        
        $total = array_sum($scores);
        if ($total == 0) {
            return ['sentiment' => 'neutral', 'confidence' => 0.5];
        }
        
        $dominant = array_search(max($scores), $scores);
        
        return [
            'sentiment' => $dominant,
            'confidence' => $scores[$dominant] / $total,
            'scores' => $scores
        ];
    }
    
    /**
     * Extract entities
     */
    private function extractEntities($text) {
        $entities = [
            'numbers' => $this->extractNumbers($text),
            'percentages' => $this->extractPercentages($text),
            'currency' => $this->extractCurrency($text),
            'dates' => $this->extractDates($text)
        ];
        
        return array_filter($entities);
    }
    
    /**
     * Generate similar questions
     */
    public function generateSimilarQuestions($question, $count = 3) {
        $analysis = $this->processQuestion($question);
        $variations = [];
        
        // Simple template-based generation
        $templates = [
            'rephrased' => $this->rephraseQuestion($question, $analysis),
            'simplified' => $this->simplifyQuestion($question, $analysis),
            'expanded' => $this->expandQuestion($question, $analysis)
        ];
        
        return array_slice($templates, 0, $count);
    }
    
    /**
     * Helper methods
     */
    private function countSyllables($text) {
        $words = str_word_count($text, 1);
        $syllables = 0;
        
        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[aeiouAEIOU]/', $word, $matches));
        }
        
        return $syllables;
    }
    
    private function calculateComplexityScore($words, $sentences, $syllables) {
        if (count($sentences) == 0 || $words == 0) return 5;
        
        $avg_sentence_length = $words / count($sentences);
        $avg_syllables = $syllables / $words;
        
        // Simple complexity formula
        $score = ($avg_sentence_length / 20) * 5 + ($avg_syllables / 2) * 5;
        
        return min(10, max(1, $score));
    }
    
    private function getReadingLevel($score) {
        if ($score >= 90) return 'very_easy';
        if ($score >= 80) return 'easy';
        if ($score >= 70) return 'fairly_easy';
        if ($score >= 60) return 'standard';
        if ($score >= 50) return 'fairly_difficult';
        if ($score >= 30) return 'difficult';
        return 'very_difficult';
    }
    
    private function estimateGradeLevel($score) {
        return max(1, min(16, round((100 - $score) / 10)));
    }
    
    private function extractNumbers($text) {
        preg_match_all('/\b\d+\b/', $text, $matches);
        return $matches[0];
    }
    
    private function extractPercentages($text) {
        preg_match_all('/\d+%/', $text, $matches);
        return $matches[0];
    }
    
    private function extractCurrency($text) {
        preg_match_all('/\$\d+(?:\.\d{2})?/', $text, $matches);
        return $matches[0];
    }
    
    private function extractDates($text) {
        preg_match_all('/\b(?:\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2})\b/', $text, $matches);
        return $matches[0];
    }
    
    private function rephraseQuestion($question, $analysis) {
        // Simple rephrasing logic
        return str_replace(['What', 'How', 'Why'], ['Which', 'In what way', 'For what reason'], $question);
    }
    
    private function simplifyQuestion($question, $analysis) {
        // Remove complex words
        $simplified = $question;
        if ($analysis['complexity']['avg_words_per_sentence'] > 15) {
            // Split into shorter sentences
            $simplified = str_replace([', which', ', that'], ['. This', '. That'], $simplified);
        }
        return $simplified;
    }
    
    private function expandQuestion($question, $analysis) {
        // Add context based on topic
        $topic = $analysis['topic']['primary'];
        $expansions = [
            'financial' => ' (Consider your financial goals)',
            'psychological' => ' (Think about your feelings)',
            'behavioral' => ' (Reflect on your actions)',
            'educational' => ' (Based on what you know)',
            'motivational' => ' (Considering your aspirations)'
        ];
        
        return $question . ($expansions[$topic] ?? '');
    }
}