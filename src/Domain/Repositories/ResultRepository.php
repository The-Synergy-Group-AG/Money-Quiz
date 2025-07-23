<?php
/**
 * Result Repository Interface
 *
 * Repository interface for result entities.
 *
 * @package MoneyQuiz\Domain\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Repositories;

use MoneyQuiz\Domain\Entities\Result;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result repository interface.
 *
 * @since 7.0.0
 */
interface ResultRepository extends RepositoryInterface {
    
    /**
     * Find results by user.
     *
     * @param int $user_id User ID.
     * @param int $limit   Limit results.
     * @param int $offset  Skip results.
     * @return array<Result> User's results.
     */
    public function find_by_user(int $user_id, int $limit = 10, int $offset = 0): array;
    
    /**
     * Find results by quiz.
     *
     * @param int $quiz_id Quiz ID.
     * @param int $limit   Limit results.
     * @param int $offset  Skip results.
     * @return array<Result> Quiz results.
     */
    public function find_by_quiz(int $quiz_id, int $limit = 10, int $offset = 0): array;
    
    /**
     * Find result by attempt.
     *
     * @param int $attempt_id Attempt ID.
     * @return Result|null Result or null.
     */
    public function find_by_attempt(int $attempt_id): ?Result;
    
    /**
     * Find results by archetype.
     *
     * @param int $archetype_id Archetype ID.
     * @param int $limit        Limit results.
     * @param int $offset       Skip results.
     * @return array<Result> Results with archetype.
     */
    public function find_by_archetype(int $archetype_id, int $limit = 10, int $offset = 0): array;
    
    /**
     * Get average score for quiz.
     *
     * @param int $quiz_id Quiz ID.
     * @return float Average score.
     */
    public function get_average_score(int $quiz_id): float;
    
    /**
     * Get archetype distribution for quiz.
     *
     * @param int $quiz_id Quiz ID.
     * @return array<int, int> Archetype ID => count.
     */
    public function get_archetype_distribution(int $quiz_id): array;
    
    /**
     * Find recent results.
     *
     * @param int $days  Days to look back.
     * @param int $limit Limit results.
     * @return array<Result> Recent results.
     */
    public function find_recent(int $days = 7, int $limit = 10): array;
}