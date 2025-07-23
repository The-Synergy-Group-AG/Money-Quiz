<?php
/**
 * Archetype Interface
 *
 * Contract for Archetype entity operations.
 *
 * @package MoneyQuiz\Domain\Contracts
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Contracts;

use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\ValueObjects\ArchetypeCriteria;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype interface.
 *
 * @since 7.0.0
 */
interface ArchetypeInterface {
    
    /**
     * Get archetype name.
     *
     * @return string Name.
     */
    public function get_name(): string;
    
    /**
     * Get archetype slug.
     *
     * @return string Slug.
     */
    public function get_slug(): string;
    
    /**
     * Get description.
     *
     * @return string Description.
     */
    public function get_description(): string;
    
    /**
     * Get characteristics.
     *
     * @return array<string> Characteristics.
     */
    public function get_characteristics(): array;
    
    /**
     * Get criteria.
     *
     * @return ArchetypeCriteria Criteria.
     */
    public function get_criteria(): ArchetypeCriteria;
    
    /**
     * Get display order.
     *
     * @return int Order.
     */
    public function get_order(): int;
    
    /**
     * Check if active.
     *
     * @return bool True if active.
     */
    public function is_active(): bool;
    
    /**
     * Check if score matches this archetype.
     *
     * @param Score $score Quiz score to check.
     * @return bool True if matches.
     */
    public function matches_score(Score $score): bool;
    
    /**
     * Generate recommendations for a score.
     *
     * @param Score $score The quiz score.
     * @return array<\MoneyQuiz\Domain\ValueObjects\Recommendation> Generated recommendations.
     */
    public function generate_recommendations(Score $score): array;
    
    /**
     * Validate entity.
     *
     * @throws \MoneyQuiz\Domain\Exceptions\EntityException If validation fails.
     * @return void
     */
    public function validate(): void;
}