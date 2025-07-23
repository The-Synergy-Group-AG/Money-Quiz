<?php
/**
 * Archetype Repository Interface
 *
 * Repository interface for archetype entities.
 *
 * @package MoneyQuiz\Domain\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Repositories;

use MoneyQuiz\Domain\Entities\Archetype;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype repository interface.
 *
 * @since 7.0.0
 */
interface ArchetypeRepository extends RepositoryInterface {
    
    /**
     * Find active archetypes.
     *
     * @return array<Archetype> Active archetypes ordered by priority.
     */
    public function find_active(): array;
    
    /**
     * Find archetype by slug.
     *
     * @param string $slug Archetype slug.
     * @return Archetype|null Archetype or null.
     */
    public function find_by_slug(string $slug): ?Archetype;
    
    /**
     * Get archetype usage count.
     *
     * @param int $archetype_id Archetype ID.
     * @param int $days         Days to look back (0 = all time).
     * @return int Usage count.
     */
    public function get_usage_count(int $archetype_id, int $days = 0): int;
    
    /**
     * Find archetypes by quiz.
     *
     * @param int $quiz_id Quiz ID.
     * @return array<Archetype> Archetypes used with quiz.
     */
    public function find_by_quiz(int $quiz_id): array;
}