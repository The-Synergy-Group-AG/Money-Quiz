<?php
/**
 * Archetype Methods Trait
 *
 * Provides methods for Archetype entity.
 *
 * @package MoneyQuiz\Domain\Traits
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Traits;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Trait for Archetype entity methods.
 *
 * @since 7.0.0
 */
trait ArchetypeMethods {
    
    /**
     * Get archetype name.
     *
     * @return string Name.
     */
    public function get_name(): string {
        return $this->name;
    }
    
    /**
     * Get archetype slug.
     *
     * @return string Slug.
     */
    public function get_slug(): string {
        return $this->slug;
    }
    
    /**
     * Get description.
     *
     * @return string Description.
     */
    public function get_description(): string {
        return $this->description;
    }
    
    /**
     * Get characteristics.
     *
     * @return array<string> Characteristics.
     */
    public function get_characteristics(): array {
        return $this->characteristics;
    }
    
    /**
     * Get criteria.
     *
     * @return \MoneyQuiz\Domain\ValueObjects\ArchetypeCriteria Criteria.
     */
    public function get_criteria(): \MoneyQuiz\Domain\ValueObjects\ArchetypeCriteria {
        return $this->criteria;
    }
    
    /**
     * Get display order.
     *
     * @return int Order.
     */
    public function get_order(): int {
        return $this->order;
    }
    
    /**
     * Check if active.
     *
     * @return bool True if active.
     */
    public function is_active(): bool {
        return $this->is_active;
    }
    
    /**
     * Check if score matches this archetype.
     *
     * @param \MoneyQuiz\Domain\ValueObjects\Score $score Quiz score to check.
     * @return bool True if matches.
     */
    public function matches_score(\MoneyQuiz\Domain\ValueObjects\Score $score): bool {
        return $this->criteria->matches($score);
    }
    
    /**
     * Evaluate condition for recommendation.
     *
     * @param array $condition Condition to evaluate.
     * @param \MoneyQuiz\Domain\ValueObjects\Score $score Score to check against.
     * @return bool True if condition met.
     */
    private function evaluate_condition(array $condition, \MoneyQuiz\Domain\ValueObjects\Score $score): bool {
        // Simple condition evaluation
        // Could be extended for complex conditions
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
     * @param string $text  Text with placeholders.
     * @param \MoneyQuiz\Domain\ValueObjects\Score $score Score for personalization.
     * @return string Personalized text.
     */
    private function personalize_text(string $text, \MoneyQuiz\Domain\ValueObjects\Score $score): string {
        $replacements = [
            '{total_score}' => $score->get_total(),
            '{percentage}' => $score->get_percentage(),
            '{archetype_name}' => $this->name,
        ];
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $text
        );
    }
    
    /**
     * Validate entity.
     *
     * @throws \MoneyQuiz\Domain\Exceptions\EntityException If validation fails.
     * @return void
     */
    public function validate(): void {
        if (empty($this->name)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Archetype name is required');
        }
        
        if (strlen($this->name) > 100) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Archetype name must not exceed 100 characters');
        }
        
        if (empty($this->slug)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Archetype slug is required');
        }
        
        if (!preg_match('/^[a-z0-9\-]+$/', $this->slug)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Archetype slug must contain only lowercase letters, numbers, and hyphens');
        }
        
        if (strlen($this->description) > 2000) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Description must not exceed 2000 characters');
        }
        
        if (empty($this->characteristics)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('At least one characteristic is required');
        }
        
        if ($this->order < 0) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Order must be non-negative');
        }
    }
}