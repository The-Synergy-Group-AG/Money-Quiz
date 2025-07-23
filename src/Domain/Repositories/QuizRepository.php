<?php
/**
 * Quiz Repository Interface
 *
 * Repository interface for quiz entities.
 *
 * @package MoneyQuiz\Domain\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Repositories;

use MoneyQuiz\Domain\Entities\Quiz;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz repository interface.
 *
 * Extends the base repository with quiz-specific methods.
 *
 * @since 7.0.0
 */
interface QuizRepository extends RepositoryInterface {
    
    /**
     * Find published quizzes.
     *
     * @param int $limit  Limit results.
     * @param int $offset Skip results.
     * @return array<Quiz> Published quizzes.
     */
    public function find_published(int $limit = 10, int $offset = 0): array;
    
    /**
     * Find quizzes by creator.
     *
     * @param int $user_id Creator user ID.
     * @param int $limit   Limit results.
     * @param int $offset  Skip results.
     * @return array<Quiz> User's quizzes.
     */
    public function find_by_creator(int $user_id, int $limit = 10, int $offset = 0): array;
    
    /**
     * Find quiz by slug.
     *
     * @param string $slug Quiz slug.
     * @return Quiz|null Quiz instance or null.
     */
    public function find_by_slug(string $slug): ?Quiz;
    
    /**
     * Count quizzes by status.
     *
     * @param string $status Quiz status.
     * @return int Count.
     */
    public function count_by_status(string $status): int;
    
    /**
     * Get popular quizzes.
     *
     * @param int $days  Days to look back.
     * @param int $limit Limit results.
     * @return array<Quiz> Popular quizzes.
     */
    public function find_popular(int $days = 30, int $limit = 10): array;
    
    /**
     * Search quizzes.
     *
     * @param string $query  Search query.
     * @param array  $filters Additional filters.
     * @param int    $limit  Limit results.
     * @param int    $offset Skip results.
     * @return array<Quiz> Matching quizzes.
     */
    public function search(
        string $query,
        array $filters = [],
        int $limit = 10,
        int $offset = 0
    ): array;
    
    /**
     * Find quiz with questions.
     *
     * @param int $id Quiz ID.
     * @return Quiz|null Quiz with loaded questions.
     */
    public function find_with_questions(int $id): ?Quiz;
    
    /**
     * Duplicate quiz.
     *
     * @param Quiz $quiz    Quiz to duplicate.
     * @param int  $user_id New owner ID.
     * @return Quiz Duplicated quiz.
     */
    public function duplicate(Quiz $quiz, int $user_id): Quiz;
}