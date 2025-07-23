<?php
/**
 * Attempt Repository Interface
 *
 * Repository interface for attempt entities.
 *
 * @package MoneyQuiz\Domain\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Repositories;

use MoneyQuiz\Domain\Entities\Attempt;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt repository interface.
 *
 * @since 7.0.0
 */
interface AttemptRepository extends RepositoryInterface {
    
    /**
     * Find attempt by session token.
     *
     * @param string $token Session token.
     * @return Attempt|null Attempt or null.
     */
    public function find_by_session_token(string $token): ?Attempt;
    
    /**
     * Find active attempts by user.
     *
     * @param int $user_id User ID.
     * @return array<Attempt> Active attempts.
     */
    public function find_active_by_user(int $user_id): array;
    
    /**
     * Find attempts by quiz.
     *
     * @param int   $quiz_id Quiz ID.
     * @param array $filters Optional filters.
     * @return array<Attempt> Attempts.
     */
    public function find_by_quiz(int $quiz_id, array $filters = []): array;
    
    /**
     * Find attempts by user.
     *
     * @param int   $user_id User ID.
     * @param array $filters Optional filters.
     * @return array<Attempt> Attempts.
     */
    public function find_by_user(int $user_id, array $filters = []): array;
    
    /**
     * Clean up abandoned attempts.
     *
     * @param int $hours Hours after which to consider abandoned.
     * @return int Number of attempts cleaned.
     */
    public function cleanup_abandoned(int $hours = 24): int;
    
    /**
     * Get attempt statistics.
     *
     * @param int    $quiz_id Quiz ID (0 for all).
     * @param string $period  Time period.
     * @return array Statistics.
     */
    public function get_statistics(int $quiz_id = 0, string $period = 'day'): array;
}