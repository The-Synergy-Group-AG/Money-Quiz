<?php
/**
 * Quiz Repository
 *
 * Handles quiz data persistence and retrieval.
 *
 * @package MoneyQuiz\Database\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Database\AbstractRepository;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Quiz repository class.
 *
 * @since 7.0.0
 */
class QuizRepository extends AbstractRepository {

	/**
	 * Set table name.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	protected function set_table_name(): void {
		$this->table = $this->db->prefix . 'money_quiz_quizzes';
	}

	/**
	 * Find quiz by slug.
	 *
	 * @since 7.0.0
	 *
	 * @param string $slug Quiz slug.
	 * @return array|null Quiz data or null.
	 */
	public function findBySlug( string $slug ): ?array {
		return $this->findBy( 'slug', $slug );
	}

	/**
	 * Get active quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param array $order Order configuration.
	 * @return array Active quizzes.
	 */
	public function getActive( array $order = [ 'created_at' => 'DESC' ] ): array {
		try {
			$query = $this->query()
				->where( 'status', 'active' );

			foreach ( $order as $column => $direction ) {
				$query->orderBy( $column, $direction );
			}

			return $query->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository getActive error', [
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get quizzes by category.
	 *
	 * @since 7.0.0
	 *
	 * @param int $category_id Category ID.
	 * @return array Quizzes in category.
	 */
	public function getByCategory( int $category_id ): array {
		try {
			return $this->query()
				->where( 'category_id', $category_id )
				->where( 'status', 'active' )
				->orderBy( 'title' )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository getByCategory error', [
				'category_id' => $category_id,
				'error'       => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get featured quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param int $limit Number of quizzes.
	 * @return array Featured quizzes.
	 */
	public function getFeatured( int $limit = 5 ): array {
		try {
			return $this->query()
				->where( 'is_featured', 1 )
				->where( 'status', 'active' )
				->orderBy( 'featured_order', 'ASC' )
				->orderBy( 'created_at', 'DESC' )
				->limit( $limit )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository getFeatured error', [
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get popular quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param int $limit Number of quizzes.
	 * @param int $days  Days to consider.
	 * @return array Popular quizzes.
	 */
	public function getPopular( int $limit = 10, int $days = 30 ): array {
		try {
			$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

			return $this->query()
				->select( [ 'q.*', 'COUNT(r.id) as result_count' ] )
				->from( $this->table . ' q' )
				->leftJoin(
					$this->db->prefix . 'money_quiz_results r',
					'q.id',
					'=',
					'r.quiz_id'
				)
				->where( 'q.status', 'active' )
				->where( 'r.created_at', $since, '>=' )
				->groupBy( 'q.id' )
				->orderBy( 'result_count', 'DESC' )
				->limit( $limit )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository getPopular error', [
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Search quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param string $query  Search query.
	 * @param array  $params Additional parameters.
	 * @return array Search results.
	 */
	public function search( string $query, array $params = [] ): array {
		try {
			$search_query = $this->query()
				->where( 'status', 'active' );

			// Search in title and description.
			$search_term = '%' . $this->db->esc_like( $query ) . '%';
			$search_query->where( 'title', $search_term, 'LIKE' )
				->orWhere( 'description', $search_term, 'LIKE' );

			// Apply category filter.
			if ( ! empty( $params['category_id'] ) ) {
				$search_query->where( 'category_id', $params['category_id'] );
			}

			// Apply sorting.
			$order_by = $params['order_by'] ?? 'relevance';
			switch ( $order_by ) {
				case 'newest':
					$search_query->orderBy( 'created_at', 'DESC' );
					break;
				case 'oldest':
					$search_query->orderBy( 'created_at', 'ASC' );
					break;
				case 'title':
					$search_query->orderBy( 'title', 'ASC' );
					break;
				default:
					// Relevance - already ordered by search match.
					break;
			}

			// Apply pagination.
			if ( isset( $params['page'] ) && isset( $params['per_page'] ) ) {
				$offset = ( $params['page'] - 1 ) * $params['per_page'];
				$search_query->limit( $params['per_page'] )
					->offset( $offset );
			}

			return $search_query->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository search error', [
				'query' => $query,
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get quiz with questions.
	 *
	 * @since 7.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array|null Quiz with questions or null.
	 */
	public function getWithQuestions( int $quiz_id ): ?array {
		try {
			$quiz = $this->find( $quiz_id );
			if ( ! $quiz ) {
				return null;
			}

			// Get questions.
			$questions = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->db->prefix}money_quiz_questions 
					WHERE quiz_id = %d 
					ORDER BY sort_order ASC",
					$quiz_id
				),
				ARRAY_A
			);

			// Get answers for each question.
			foreach ( $questions as &$question ) {
				$question['answers'] = $this->db->get_results(
					$this->db->prepare(
						"SELECT * FROM {$this->db->prefix}money_quiz_answers 
						WHERE question_id = %d 
						ORDER BY sort_order ASC",
						$question['id']
					),
					ARRAY_A
				);
			}

			$quiz['questions'] = $questions;

			return $quiz;
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository getWithQuestions error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return null;
		}
	}

	/**
	 * Update quiz statistics.
	 *
	 * @since 7.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return bool True if successful.
	 */
	public function updateStatistics( int $quiz_id ): bool {
		try {
			// Get result statistics.
			$stats = $this->db->get_row(
				$this->db->prepare(
					"SELECT 
						COUNT(*) as total_attempts,
						AVG(score) as average_score,
						MAX(score) as highest_score,
						MIN(score) as lowest_score,
						AVG(time_taken) as average_time
					FROM {$this->db->prefix}money_quiz_results 
					WHERE quiz_id = %d",
					$quiz_id
				),
				ARRAY_A
			);

			// Update quiz record.
			return $this->update( $quiz_id, [
				'total_attempts' => $stats['total_attempts'] ?? 0,
				'average_score'  => $stats['average_score'] ?? 0,
				'highest_score'  => $stats['highest_score'] ?? 0,
				'lowest_score'   => $stats['lowest_score'] ?? 0,
				'average_time'   => $stats['average_time'] ?? 0,
			] );
		} catch ( \Exception $e ) {
			$this->logger->error( 'QuizRepository updateStatistics error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Duplicate quiz.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id   Quiz ID to duplicate.
	 * @param string $new_title New quiz title.
	 * @return int|false New quiz ID or false.
	 */
	public function duplicate( int $quiz_id, string $new_title ): int|false {
		try {
			$this->beginTransaction();

			// Get original quiz.
			$quiz = $this->find( $quiz_id );
			if ( ! $quiz ) {
				throw new \Exception( 'Quiz not found' );
			}

			// Prepare new quiz data.
			unset( $quiz['id'] );
			$quiz['title'] = $new_title;
			$quiz['slug'] = sanitize_title( $new_title );
			$quiz['status'] = 'draft';
			$quiz['is_featured'] = 0;
			$quiz['total_attempts'] = 0;
			$quiz['average_score'] = 0;
			$quiz['created_at'] = current_time( 'mysql' );
			$quiz['updated_at'] = current_time( 'mysql' );

			// Create new quiz.
			$new_quiz_id = $this->create( $quiz );
			if ( ! $new_quiz_id ) {
				throw new \Exception( 'Failed to create quiz' );
			}

			// Duplicate questions.
			$questions = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->db->prefix}money_quiz_questions 
					WHERE quiz_id = %d",
					$quiz_id
				),
				ARRAY_A
			);

			foreach ( $questions as $question ) {
				$old_question_id = $question['id'];
				unset( $question['id'] );
				$question['quiz_id'] = $new_quiz_id;

				// Insert new question.
				$this->db->insert(
					$this->db->prefix . 'money_quiz_questions',
					$question
				);
				$new_question_id = $this->db->insert_id;

				// Duplicate answers.
				$answers = $this->db->get_results(
					$this->db->prepare(
						"SELECT * FROM {$this->db->prefix}money_quiz_answers 
						WHERE question_id = %d",
						$old_question_id
					),
					ARRAY_A
				);

				foreach ( $answers as $answer ) {
					unset( $answer['id'] );
					$answer['question_id'] = $new_question_id;

					$this->db->insert(
						$this->db->prefix . 'money_quiz_answers',
						$answer
					);
				}
			}

			$this->commit();

			$this->logger->info( 'Quiz duplicated', [
				'original_id' => $quiz_id,
				'new_id'      => $new_quiz_id,
			] );

			return $new_quiz_id;
		} catch ( \Exception $e ) {
			$this->rollback();
			$this->logger->error( 'QuizRepository duplicate error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return false;
		}
	}
}