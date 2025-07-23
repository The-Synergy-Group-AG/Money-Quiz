<?php
/**
 * Recommendation Generator Service
 *
 * Generates personalized recommendations based on archetype and score.
 *
 * @package MoneyQuiz\Domain\Services
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Services;

use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\ValueObjects\Recommendation;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Recommendation generator class.
 *
 * @since 7.0.0
 */
class RecommendationGenerator {
    
    /**
     * Generate recommendations for a score.
     *
     * @param Archetype $archetype The archetype.
     * @param Score     $score     The quiz score.
     * @return array<Recommendation> Generated recommendations.
     */
    public static function generate(Archetype $archetype, Score $score): array {
        $recommendations = [];
        $templates = self::get_recommendation_templates($archetype);
        
        foreach ($templates as $template) {
            // Skip if condition doesn't match
            if (isset($template['condition']) && !self::evaluate_condition($template['condition'], $score)) {
                continue;
            }
            
            $recommendations[] = new Recommendation(
                $template['title'],
                self::personalize_text($template['description'], $score, $archetype),
                $template['type'] ?? 'general',
                $template['priority'] ?? 50,
                $template['metadata'] ?? []
            );
        }
        
        // Sort by priority
        usort($recommendations, fn($a, $b) => $b->get_priority() <=> $a->get_priority());
        
        return $recommendations;
    }
    
    /**
     * Get recommendation templates from archetype.
     *
     * @param Archetype $archetype The archetype.
     * @return array Templates.
     */
    private static function get_recommendation_templates(Archetype $archetype): array {
        // Use reflection to access private property
        $reflection = new \ReflectionClass($archetype);
        $prop = $reflection->getProperty('recommendation_templates');
        $prop->setAccessible(true);
        return $prop->getValue($archetype);
    }
    
    /**
     * Evaluate condition for recommendation.
     *
     * @param array $condition Condition to evaluate.
     * @param Score $score     Score to check against.
     * @return bool True if condition met.
     */
    private static function evaluate_condition(array $condition, Score $score): bool {
        if (isset($condition['min_score']) && $score->get_total() < $condition['min_score']) {
            return false;
        }
        
        if (isset($condition['max_score']) && $score->get_total() > $condition['max_score']) {
            return false;
        }
        
        if (isset($condition['dimension']) && isset($condition['min_value'])) {
            $dimension_score = $score->get_dimension_score($condition['dimension']);
            if ($dimension_score < $condition['min_value']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Personalize text with score data.
     *
     * @param string    $text      Text with placeholders.
     * @param Score     $score     Score for personalization.
     * @param Archetype $archetype Archetype for personalization.
     * @return string Personalized text.
     */
    private static function personalize_text(string $text, Score $score, Archetype $archetype): string {
        $replacements = [
            '{total_score}' => $score->get_total(),
            '{percentage}' => $score->get_percentage(),
            '{archetype_name}' => $archetype->get_name(),
        ];
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $text
        );
    }
}