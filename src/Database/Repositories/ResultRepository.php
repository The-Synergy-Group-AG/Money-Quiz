<?php
/**
 * Result Repository
 *
 * Handles quiz result data persistence and retrieval.
 *
 * @package MoneyQuiz\Database\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Database\AbstractRepository;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Result repository class.
 *
 * @since 7.0.0
 */
class ResultRepository extends AbstractRepository {

	/**
	 * Set table name.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	protected function set_table_name(): void {
		$this->table = $this->db->prefix . 'money_quiz_results';
	}

	/**
	 * Get results by quiz.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $params  Additional parameters.
	 * @return array Results.
	 */
	public function getByQuiz( int $quiz_id, array $params = [] ): array {
		try {
			$query = $this->query()
				->where( 'quiz_id', $quiz_id );

			// Apply date range filter.
			if ( ! empty( $params['date_from'] ) ) {
				$query->where( 'created_at', $params['date_from'], '>=' );
			}
			if ( ! empty( $params['date_to'] ) ) {
				$query->where( 'created_at', $params['date_to'], '<=' );
			}

			// Apply user filter.
			if ( ! empty( $params['user_id'] ) ) {
				$query->where( 'user_id', $params['user_id'] );
			}

			// Apply ordering.
			$order_by = $params['order_by'] ?? 'created_at';
			$order_dir = $params['order_dir'] ?? 'DESC';
			$query->orderBy( $order_by, $order_dir );

			// Apply pagination.
			if ( isset( $params['page'] ) && isset( $params['per_page'] ) ) {
				$offset = ( $params['page'] - 1 ) * $params['per_page'];
				$query->limit( $params['per_page'] )
					->offset( $offset );
			}

			return $query->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getByQuiz error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get results by user.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $params  Additional parameters.
	 * @return array Results.
	 */
	public function getByUser( int $user_id, array $params = [] ): array {
		try {
			$query = $this->query()
				->select( [ 'r.*', 'q.title as quiz_title', 'q.slug as quiz_slug' ] )
				->from( $this->table . ' r' )
				->join(
					$this->db->prefix . 'money_quiz_quizzes q',
					'r.quiz_id',
					'=',
					'q.id'
				)
				->where( 'r.user_id', $user_id );

			// Apply status filter.
			if ( ! empty( $params['status'] ) ) {
				$query->where( 'r.status', $params['status'] );
			}

			// Apply ordering.
			$order_by = $params['order_by'] ?? 'r.created_at';
			$order_dir = $params['order_dir'] ?? 'DESC';
			$query->orderBy( $order_by, $order_dir );

			// Apply limit.
			if ( ! empty( $params['limit'] ) ) {
				$query->limit( $params['limit'] );
			}

			return $query->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getByUser error', [
				'user_id' => $user_id,
				'error'   => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get user's best results.
	 *
	 * @since 7.0.0
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of results.
	 * @return array Best results.
	 */
	public function getUserBestResults( int $user_id, int $limit = 5 ): array {
		try {
			return $this->query()
				->select( [ 'r.*', 'q.title as quiz_title', 'q.slug as quiz_slug' ] )
				->from( $this->table . ' r' )
				->join(
					$this->db->prefix . 'money_quiz_quizzes q',
					'r.quiz_id',
					'=',
					'q.id'
				)
				->where( 'r.user_id', $user_id )
				->where( 'r.status', 'completed' )
				->orderBy( 'r.score', 'DESC' )
				->limit( $limit )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getUserBestResults error', [
				'user_id' => $user_id,
				'error'   => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get recent results.
	 *
	 * @since 7.0.0
	 *
	 * @param int $limit Number of results.
	 * @return array Recent results.
	 */
	public function getRecent( int $limit = 10 ): array {
		try {
			return $this->query()
				->select( [
					'r.*',
					'q.title as quiz_title',
					'q.slug as quiz_slug',
					'u.display_name as user_name',
				] )
				->from( $this->table . ' r' )
				->join(
					$this->db->prefix . 'money_quiz_quizzes q',
					'r.quiz_id',
					'=',
					'q.id'
				)
				->leftJoin(
					$this->db->users . ' u',
					'r.user_id',
					'=',
					'u.ID'
				)
				->where( 'r.status', 'completed' )
				->orderBy( 'r.created_at', 'DESC' )
				->limit( $limit )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getRecent error', [
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get leaderboard.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id   Quiz ID (0 for global).
	 * @param string $timeframe Timeframe (all, month, week, day).
	 * @param int    $limit     Number of entries.
	 * @return array Leaderboard entries.
	 */
	public function getLeaderboard( int $quiz_id = 0, string $timeframe = 'all', int $limit = 10 ): array {
		try {
			$query = $this->query()
				->select( [
					'r.user_id',
					'u.display_name as user_name',
					'MAX(r.score) as best_score',
					'COUNT(r.id) as attempts',
					'AVG(r.score) as avg_score',
				] )
				->from( $this->table . ' r' )
				->join(
					$this->db->users . ' u',
					'r.user_id',
					'=',
					'u.ID'
				)
				->where( 'r.status', 'completed' )
				->whereNotNull( 'r.user_id' );

			// Filter by quiz.
			if ( $quiz_id > 0 ) {
				$query->where( 'r.quiz_id', $quiz_id );
			}

			// Filter by timeframe.
			switch ( $timeframe ) {
				case 'day':
					$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
					break;
				case 'week':
					$since = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
					break;
				case 'month':
					$since = gmdate( 'Y-m-d H:i:s', time() - MONTH_IN_SECONDS );
					break;
				default:
					$since = null;
			}

			if ( $since ) {
				$query->where( 'r.created_at', $since, '>=' );
			}

			return $query
				->groupBy( 'r.user_id' )
				->orderBy( 'best_score', 'DESC' )
				->orderBy( 'attempts', 'ASC' )
				->limit( $limit )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getLeaderboard error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Save quiz result.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Result data.
	 * @return int|false Result ID or false.
	 */
	public function saveResult( array $data ): int|false {
		try {
			$this->beginTransaction();

			// Create result record.
			$result_id = $this->create( $data );
			if ( ! $result_id ) {
				throw new \Exception( 'Failed to create result' );
			}

			// Save answers if provided.
			if ( ! empty( $data['answers'] ) ) {
				foreach ( $data['answers'] as $question_id => $answer_data ) {
					$this->db->insert(
						$this->db->prefix . 'money_quiz_result_answers',
						[
							'result_id'    => $result_id,
							'question_id'  => $question_id,
							'answer_id'    => $answer_data['answer_id'] ?? null,
							'answer_text'  => $answer_data['answer_text'] ?? null,
							'is_correct'   => $answer_data['is_correct'] ?? 0,
							'points'       => $answer_data['points'] ?? 0,
							'time_taken'   => $answer_data['time_taken'] ?? 0,
						]
					);
				}
			}

			$this->commit();

			// Update quiz statistics.
			do_action( 'money_quiz_result_saved', $result_id, $data );

			return $result_id;
		} catch ( \Exception $e ) {
			$this->rollback();
			$this->logger->error( 'ResultRepository saveResult error', [
				'error' => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Get result with details.
	 *
	 * @since 7.0.0
	 *
	 * @param int $result_id Result ID.
	 * @return array|null Result with details or null.
	 */
	public function getWithDetails( int $result_id ): ?array {
		try {
			$result = $this->find( $result_id );
			if ( ! $result ) {
				return null;
			}

			// Get quiz info.
			$result['quiz'] = $this->db->get_row(
				$this->db->prepare(
					"SELECT * FROM {$this->db->prefix}money_quiz_quizzes WHERE id = %d",
					$result['quiz_id']
				),
				ARRAY_A
			);

			// Get user info.
			if ( $result['user_id'] ) {
				$user = get_user_by( 'id', $result['user_id'] );
				$result['user'] = [
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
				];
			}

			// Get answers.
			$result['answers'] = $this->db->get_results(
				$this->db->prepare(
					"SELECT ra.*, q.question_text, a.answer_text as selected_answer
					FROM {$this->db->prefix}money_quiz_result_answers ra
					JOIN {$this->db->prefix}money_quiz_questions q ON ra.question_id = q.id
					LEFT JOIN {$this->db->prefix}money_quiz_answers a ON ra.answer_id = a.id
					WHERE ra.result_id = %d
					ORDER BY q.sort_order",
					$result_id
				),
				ARRAY_A
			);

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getWithDetails error', [
				'result_id' => $result_id,
				'error'     => $e->getMessage(),
			] );
			return null;
		}
	}

	/**
	 * Get statistics for a period.
	 *
	 * @since 7.0.0
	 *
	 * @param string $period Period (day, week, month, year).
	 * @param int    $quiz_id Quiz ID (0 for all).
	 * @return array Statistics.
	 */
	public function getStatistics( string $period = 'month', int $quiz_id = 0 ): array {
		try {
			// Determine date range.
			switch ( $period ) {
				case 'day':
					$interval = 'HOUR';
					$format = '%Y-%m-%d %H:00:00';
					$since = gmdate( 'Y-m-d 00:00:00' );
					break;
				case 'week':
					$interval = 'DAY';
					$format = '%Y-%m-%d';
					$since = gmdate( 'Y-m-d', time() - WEEK_IN_SECONDS );
					break;
				case 'year':
					$interval = 'MONTH';
					$format = '%Y-%m';
					$since = gmdate( 'Y-01-01' );
					break;
				default: // month
					$interval = 'DAY';
					$format = '%Y-%m-%d';
					$since = gmdate( 'Y-m-01' );
			}

			$where_quiz = $quiz_id > 0 ? $this->db->prepare( ' AND quiz_id = %d', $quiz_id ) : '';

			// Get completion stats.
			$stats = $this->db->get_results(
				$this->db->prepare(
					"SELECT 
						DATE_FORMAT(created_at, %s) as period,
						COUNT(*) as total,
						COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
						AVG(score) as avg_score,
						MAX(score) as max_score,
						AVG(time_taken) as avg_time
					FROM {$this->table}
					WHERE created_at >= %s
					{$where_quiz}
					GROUP BY period
					ORDER BY period",
					$format,
					$since
				),
				ARRAY_A
			);

			return [
				'period' => $period,
				'data'   => $stats,
				'summary' => $this->getStatisticsSummary( $since, $quiz_id ),
			];
		} catch ( \Exception $e ) {
			$this->logger->error( 'ResultRepository getStatistics error', [
				'period' => $period,
				'error'  => $e->getMessage(),
			] );
			return [
				'period' => $period,
				'data'   => [],
				'summary' => [],
			];
		}
	}

	/**
	 * Get statistics summary.
	 *
	 * @since 7.0.0
	 *
	 * @param string $since   Since date.
	 * @param int    $quiz_id Quiz ID.
	 * @return array Summary data.
	 */
	private function getStatisticsSummary( string $since, int $quiz_id = 0 ): array {
		$where_quiz = $quiz_id > 0 ? $this->db->prepare( ' AND quiz_id = %d', $quiz_id ) : '';

		return $this->db->get_row(
			$this->db->prepare(
				"SELECT 
					COUNT(*) as total_attempts,
					COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
					COUNT(DISTINCT user_id) as unique_users,
					AVG(score) as avg_score,
					AVG(time_taken) as avg_time,
					MAX(score) as highest_score
				FROM {$this->table}
				WHERE created_at >= %s
				{$where_quiz}",
				$since
			),
			ARRAY_A
		) ?: [];
	}
}