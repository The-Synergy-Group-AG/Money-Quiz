<?php
/**
 * Result Methods Trait
 *
 * Provides additional methods for Result entity.
 *
 * @package MoneyQuiz\Domain\Traits
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Traits;

use MoneyQuiz\Domain\ValueObjects\Recommendation;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Trait for Result entity methods.
 *
 * @since 7.0.0
 */
trait ResultMethods {
    
    /**
     * Add recommendation.
     *
     * @param Recommendation $recommendation Recommendation to add.
     * @return void
     */
    public function add_recommendation(Recommendation $recommendation): void {
        $this->recommendations[] = $recommendation;
        $this->update_timestamps();
    }
    
    /**
     * Get attempt ID.
     *
     * @return int Attempt ID.
     */
    public function get_attempt_id(): int {
        return $this->attempt_id;
    }
    
    /**
     * Get quiz ID.
     *
     * @return int Quiz ID.
     */
    public function get_quiz_id(): int {
        return $this->quiz_id;
    }
    
    /**
     * Get user ID.
     *
     * @return int User ID.
     */
    public function get_user_id(): int {
        return $this->user_id;
    }
    
    /**
     * Get score.
     *
     * @return \MoneyQuiz\Domain\ValueObjects\Score Score object.
     */
    public function get_score(): \MoneyQuiz\Domain\ValueObjects\Score {
        return $this->score;
    }
    
    /**
     * Get archetype.
     *
     * @return \MoneyQuiz\Domain\Entities\Archetype|null Archetype or null.
     */
    public function get_archetype(): ?\MoneyQuiz\Domain\Entities\Archetype {
        return $this->archetype;
    }
    
    /**
     * Get archetype ID.
     *
     * @return int|null Archetype ID or null.
     */
    public function get_archetype_id(): ?int {
        return $this->archetype_id;
    }
    
    /**
     * Get recommendations.
     *
     * @return array<Recommendation> Recommendations.
     */
    public function get_recommendations(): array {
        return $this->recommendations;
    }
    
    /**
     * Get calculation timestamp.
     *
     * @return \DateTimeInterface Calculation time.
     */
    public function get_calculated_at(): \DateTimeInterface {
        return $this->calculated_at;
    }
    
    /**
     * Get metadata.
     *
     * @return array Metadata.
     */
    public function get_metadata(): array {
        return $this->metadata;
    }
    
    /**
     * Check if result has archetype assigned.
     *
     * @return bool True if archetype assigned.
     */
    public function has_archetype(): bool {
        return $this->archetype !== null;
    }
    
    /**
     * Validate entity.
     *
     * @throws \MoneyQuiz\Domain\Exceptions\EntityException If validation fails.
     * @return void
     */
    public function validate(): void {
        if ($this->attempt_id <= 0) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Invalid attempt ID');
        }
        
        if ($this->quiz_id <= 0) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Invalid quiz ID');
        }
        
        if ($this->user_id <= 0) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Invalid user ID');
        }
        
        // Score validation happens in Score value object
    }
}