<?php
/**
 * Result Interface
 *
 * Contract for Result entity operations.
 *
 * @package MoneyQuiz\Domain\Contracts
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Contracts;

use MoneyQuiz\Domain\ValueObjects\Recommendation;
use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\Entities\Archetype;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result interface.
 *
 * @since 7.0.0
 */
interface ResultInterface {
    
    /**
     * Add recommendation.
     *
     * @param Recommendation $recommendation Recommendation to add.
     * @return void
     */
    public function add_recommendation(Recommendation $recommendation): void;
    
    /**
     * Get attempt ID.
     *
     * @return int Attempt ID.
     */
    public function get_attempt_id(): int;
    
    /**
     * Get quiz ID.
     *
     * @return int Quiz ID.
     */
    public function get_quiz_id(): int;
    
    /**
     * Get user ID.
     *
     * @return int User ID.
     */
    public function get_user_id(): int;
    
    /**
     * Get score.
     *
     * @return Score Score object.
     */
    public function get_score(): Score;
    
    /**
     * Get archetype.
     *
     * @return Archetype|null Archetype or null.
     */
    public function get_archetype(): ?Archetype;
    
    /**
     * Get archetype ID.
     *
     * @return int|null Archetype ID or null.
     */
    public function get_archetype_id(): ?int;
    
    /**
     * Get recommendations.
     *
     * @return array<Recommendation> Recommendations.
     */
    public function get_recommendations(): array;
    
    /**
     * Get calculation timestamp.
     *
     * @return \DateTimeInterface Calculation time.
     */
    public function get_calculated_at(): \DateTimeInterface;
    
    /**
     * Get metadata.
     *
     * @return array Metadata.
     */
    public function get_metadata(): array;
    
    /**
     * Check if result has archetype assigned.
     *
     * @return bool True if archetype assigned.
     */
    public function has_archetype(): bool;
    
    /**
     * Validate entity.
     *
     * @throws \MoneyQuiz\Domain\Exceptions\EntityException If validation fails.
     * @return void
     */
    public function validate(): void;
}