<?php
/**
 * Analytics Repository
 *
 * Handles analytics data persistence and retrieval.
 *
 * @package MoneyQuiz\Database\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Database\AbstractRepository;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Analytics repository class.
 *
 * @since 7.0.0
 */
class AnalyticsRepository extends AbstractRepository {

	/**
	 * Set table name.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	protected function set_table_name(): void {
		$this->table = $this->db->prefix . 'money_quiz_analytics';
	}

	/**
	 * Track event.
	 *
	 * @since 7.0.0
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Event data.
	 * @return int|false Event ID or false.
	 */
	public function trackEvent( string $event_type, array $data = [] ): int|false {
		try {
			$event_data = [
				'event_type'  => $event_type,
				'user_id'     => get_current_user_id() ?: null,
				'session_id'  => $this->get_session_id(),
				'ip_address'  => $this->get_client_ip(),
				'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'referer'     => $_SERVER['HTTP_REFERER'] ?? '',
				'url'         => $this->get_current_url(),
				'event_data'  => wp_json_encode( $data ),
				'created_at'  => current_time( 'mysql' ),
			];

			return $this->create( $event_data );
		} catch ( \Exception $e ) {
			$this->logger->error( 'AnalyticsRepository trackEvent error', [
				'event_type' => $event_type,
				'error'      => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Get event counts.
	 *
	 * @since 7.0.0
	 *
	 * @param array $params Query parameters.
	 * @return array Event counts.
	 */
	public function getEventCounts( array $params = [] ): array {
		try {
			$query = $this->query()
				->select( [ 'event_type', 'COUNT(*) as count' ] );

			// Apply date range.
			if ( ! empty( $params['date_from'] ) ) {
				$query->where( 'created_at', $params['date_from'], '>=' );
			}
			if ( ! empty( $params['date_to'] ) ) {
				$query->where( 'created_at', $params['date_to'], '<=' );
			}

			// Apply user filter.
			if ( isset( $params['user_id'] ) ) {
				if ( $params['user_id'] === 0 ) {
					$query->whereNull( 'user_id' );
				} else {
					$query->where( 'user_id', $params['user_id'] );
				}
			}

			return $query
				->groupBy( 'event_type' )
				->orderBy( 'count', 'DESC' )
				->get();
		} catch ( \Exception $e ) {
			$this->logger->error( 'AnalyticsRepository getEventCounts error', [
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get quiz analytics.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $params  Query parameters.
	 * @return array Analytics data.
	 */
	public function getQuizAnalytics( int $quiz_id, array $params = [] ): array {
		try {
			$period = $params['period'] ?? 'month';
			$metrics = [];

			// Get view count.
			$metrics['views'] = $this->getQuizViews( $quiz_id, $period );

			// Get start count.
			$metrics['starts'] = $this->getQuizStarts( $quiz_id, $period );

			// Get completion rate.
			$metrics['completion_rate'] = $this->getQuizCompletionRate( $quiz_id, $period );

			// Get average score.
			$metrics['avg_score'] = $this->getQuizAverageScore( $quiz_id, $period );

			// Get time metrics.
			$metrics['time_metrics'] = $this->getQuizTimeMetrics( $quiz_id, $period );

			// Get question performance.
			$metrics['question_performance'] = $this->getQuestionPerformance( $quiz_id );

			// Get user demographics.
			$metrics['demographics'] = $this->getQuizDemographics( $quiz_id, $period );

			return $metrics;
		} catch ( \Exception $e ) {
			$this->logger->error( 'AnalyticsRepository getQuizAnalytics error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get quiz views.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $period  Time period.
	 * @return int View count.
	 */
	private function getQuizViews( int $quiz_id, string $period ): int {
		$since = $this->get_period_start( $period );

		return $this->query()
			->where( 'event_type', 'quiz_view' )
			->where( 'event_data', '%"quiz_id":' . $quiz_id . '%', 'LIKE' )
			->where( 'created_at', $since, '>=' )
			->count();
	}

	/**
	 * Get quiz starts.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $period  Time period.
	 * @return int Start count.
	 */
	private function getQuizStarts( int $quiz_id, string $period ): int {
		$since = $this->get_period_start( $period );

		return $this->query()
			->where( 'event_type', 'quiz_start' )
			->where( 'event_data', '%"quiz_id":' . $quiz_id . '%', 'LIKE' )
			->where( 'created_at', $since, '>=' )
			->count();
	}

	/**
	 * Get quiz completion rate.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $period  Time period.
	 * @return float Completion rate.
	 */
	private function getQuizCompletionRate( int $quiz_id, string $period ): float {
		$starts = $this->getQuizStarts( $quiz_id, $period );
		if ( $starts === 0 ) {
			return 0.0;
		}

		$since = $this->get_period_start( $period );
		$completions = $this->query()
			->where( 'event_type', 'quiz_complete' )
			->where( 'event_data', '%"quiz_id":' . $quiz_id . '%', 'LIKE' )
			->where( 'created_at', $since, '>=' )
			->count();

		return round( ( $completions / $starts ) * 100, 2 );
	}

	/**
	 * Get quiz average score.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $period  Time period.
	 * @return float Average score.
	 */
	private function getQuizAverageScore( int $quiz_id, string $period ): float {
		$since = $this->get_period_start( $period );

		$avg = $this->db->get_var(
			$this->db->prepare(
				"SELECT AVG(score) 
				FROM {$this->db->prefix}money_quiz_results 
				WHERE quiz_id = %d 
				AND status = 'completed' 
				AND created_at >= %s",
				$quiz_id,
				$since
			)
		);

		return round( (float) $avg, 2 );
	}

	/**
	 * Get quiz time metrics.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $period  Time period.
	 * @return array Time metrics.
	 */
	private function getQuizTimeMetrics( int $quiz_id, string $period ): array {
		$since = $this->get_period_start( $period );

		$metrics = $this->db->get_row(
			$this->db->prepare(
				"SELECT 
					AVG(time_taken) as avg_time,
					MIN(time_taken) as min_time,
					MAX(time_taken) as max_time
				FROM {$this->db->prefix}money_quiz_results 
				WHERE quiz_id = %d 
				AND status = 'completed' 
				AND created_at >= %s",
				$quiz_id,
				$since
			),
			ARRAY_A
		);

		return [
			'average' => round( (float) ( $metrics['avg_time'] ?? 0 ), 2 ),
			'minimum' => (int) ( $metrics['min_time'] ?? 0 ),
			'maximum' => (int) ( $metrics['max_time'] ?? 0 ),
		];
	}

	/**
	 * Get question performance.
	 *
	 * @since 7.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Question performance data.
	 */
	private function getQuestionPerformance( int $quiz_id ): array {
		try {
			return $this->db->get_results(
				$this->db->prepare(
					"SELECT 
						q.id,
						q.question_text,
						COUNT(ra.id) as attempts,
						SUM(ra.is_correct) as correct_answers,
						AVG(ra.time_taken) as avg_time,
						(SUM(ra.is_correct) / COUNT(ra.id) * 100) as success_rate
					FROM {$this->db->prefix}money_quiz_questions q
					LEFT JOIN {$this->db->prefix}money_quiz_result_answers ra ON q.id = ra.question_id
					WHERE q.quiz_id = %d
					GROUP BY q.id
					ORDER BY q.sort_order",
					$quiz_id
				),
				ARRAY_A
			);
		} catch ( \Exception $e ) {
			$this->logger->error( 'AnalyticsRepository getQuestionPerformance error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get quiz demographics.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $period  Time period.
	 * @return array Demographics data.
	 */
	private function getQuizDemographics( int $quiz_id, string $period ): array {
		$since = $this->get_period_start( $period );

		// Get user type breakdown.
		$user_types = $this->db->get_results(
			$this->db->prepare(
				"SELECT 
					CASE 
						WHEN user_id IS NULL THEN 'guest'
						ELSE 'registered'
					END as user_type,
					COUNT(*) as count
				FROM {$this->db->prefix}money_quiz_results
				WHERE quiz_id = %d
				AND created_at >= %s
				GROUP BY user_type",
				$quiz_id,
				$since
			),
			ARRAY_A
		);

		// Get device breakdown from user agents.
		$devices = $this->getDeviceBreakdown( $quiz_id, $since );

		// Get geographic data if available.
		$geographic = $this->getGeographicBreakdown( $quiz_id, $since );

		return [
			'user_types' => $user_types,
			'devices'    => $devices,
			'geographic' => $geographic,
		];
	}

	/**
	 * Get device breakdown.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $since   Since date.
	 * @return array Device breakdown.
	 */
	private function getDeviceBreakdown( int $quiz_id, string $since ): array {
		$events = $this->db->get_col(
			$this->db->prepare(
				"SELECT user_agent 
				FROM {$this->table}
				WHERE event_type = 'quiz_start'
				AND event_data LIKE %s
				AND created_at >= %s",
				'%"quiz_id":' . $quiz_id . '%',
				$since
			)
		);

		$devices = [
			'desktop' => 0,
			'mobile'  => 0,
			'tablet'  => 0,
			'other'   => 0,
		];

		foreach ( $events as $user_agent ) {
			if ( preg_match( '/Mobile|Android|iPhone/i', $user_agent ) ) {
				$devices['mobile']++;
			} elseif ( preg_match( '/Tablet|iPad/i', $user_agent ) ) {
				$devices['tablet']++;
			} elseif ( preg_match( '/Windows|Mac|Linux/i', $user_agent ) ) {
				$devices['desktop']++;
			} else {
				$devices['other']++;
			}
		}

		return $devices;
	}

	/**
	 * Get geographic breakdown.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $since   Since date.
	 * @return array Geographic data.
	 */
	private function getGeographicBreakdown( int $quiz_id, string $since ): array {
		// This would require IP geolocation service.
		// For now, return empty array.
		return [];
	}

	/**
	 * Get funnel analytics.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $params  Query parameters.
	 * @return array Funnel data.
	 */
	public function getFunnelAnalytics( int $quiz_id, array $params = [] ): array {
		try {
			$period = $params['period'] ?? 'month';
			$since = $this->get_period_start( $period );

			// Get funnel stages.
			$funnel = [];

			// Stage 1: Views.
			$funnel['view'] = $this->getQuizViews( $quiz_id, $period );

			// Stage 2: Starts.
			$funnel['start'] = $this->getQuizStarts( $quiz_id, $period );

			// Stage 3: Questions answered.
			$funnel['progress'] = $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(DISTINCT session_id)
					FROM {$this->table}
					WHERE event_type = 'question_answered'
					AND event_data LIKE %s
					AND created_at >= %s",
					'%"quiz_id":' . $quiz_id . '%',
					$since
				)
			);

			// Stage 4: Completions.
			$funnel['complete'] = $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*)
					FROM {$this->db->prefix}money_quiz_results
					WHERE quiz_id = %d
					AND status = 'completed'
					AND created_at >= %s",
					$quiz_id,
					$since
				)
			);

			// Calculate drop-off rates.
			$dropoff = [];
			if ( $funnel['view'] > 0 ) {
				$dropoff['view_to_start'] = round( ( ( $funnel['view'] - $funnel['start'] ) / $funnel['view'] ) * 100, 2 );
			}
			if ( $funnel['start'] > 0 ) {
				$dropoff['start_to_progress'] = round( ( ( $funnel['start'] - $funnel['progress'] ) / $funnel['start'] ) * 100, 2 );
			}
			if ( $funnel['progress'] > 0 ) {
				$dropoff['progress_to_complete'] = round( ( ( $funnel['progress'] - $funnel['complete'] ) / $funnel['progress'] ) * 100, 2 );
			}

			return [
				'funnel'  => $funnel,
				'dropoff' => $dropoff,
			];
		} catch ( \Exception $e ) {
			$this->logger->error( 'AnalyticsRepository getFunnelAnalytics error', [
				'quiz_id' => $quiz_id,
				'error'   => $e->getMessage(),
			] );
			return [
				'funnel'  => [],
				'dropoff' => [],
			];
		}
	}

	/**
	 * Get period start date.
	 *
	 * @since 7.0.0
	 *
	 * @param string $period Period name.
	 * @return string Start date.
	 */
	private function get_period_start( string $period ): string {
		switch ( $period ) {
			case 'day':
				return gmdate( 'Y-m-d 00:00:00' );
			case 'week':
				return gmdate( 'Y-m-d 00:00:00', time() - WEEK_IN_SECONDS );
			case 'year':
				return gmdate( 'Y-01-01 00:00:00' );
			default: // month
				return gmdate( 'Y-m-01 00:00:00' );
		}
	}

	/**
	 * Get session ID.
	 *
	 * @since 7.0.0
	 *
	 * @return string Session ID.
	 */
	private function get_session_id(): string {
		if ( ! session_id() ) {
			session_start();
		}
		return session_id();
	}

	/**
	 * Get client IP.
	 *
	 * @since 7.0.0
	 *
	 * @return string Client IP.
	 */
	private function get_client_ip(): string {
		$ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR', 'HTTP_CLIENT_IP' ];

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				$ip = filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP );
				if ( $ip !== false ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get current URL.
	 *
	 * @since 7.0.0
	 *
	 * @return string Current URL.
	 */
	private function get_current_url(): string {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$host = $_SERVER['HTTP_HOST'] ?? '';
		$uri = $_SERVER['REQUEST_URI'] ?? '';

		return $protocol . $host . $uri;
	}
}