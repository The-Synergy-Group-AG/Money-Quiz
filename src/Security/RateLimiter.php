<?php
/**
 * Rate Limiter
 *
 * Database-backed rate limiting implementation.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Core\Exceptions\RateLimitExceededException;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Rate limiter class.
 *
 * @since 7.0.0
 */
class RateLimiter {

	/**
	 * Database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private string $table_prefix;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param \wpdb  $db           Database instance.
	 * @param string $table_prefix Table prefix.
	 */
	public function __construct( \wpdb $db, string $table_prefix ) {
		$this->db = $db;
		$this->table_prefix = $table_prefix;
	}

	/**
	 * Check rate limit.
	 *
	 * @since 7.0.0
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param string $action     Action being limited.
	 * @param int    $limit      Maximum attempts allowed.
	 * @param int    $window     Time window in seconds.
	 * @return bool True if within limit.
	 *
	 * @throws RateLimitExceededException When rate limit exceeded.
	 */
	public function check( string $identifier, string $action, int $limit, int $window ): bool {
		$table = $this->db->prefix . 'money_quiz_rate_limits';
		$window_start = gmdate( 'Y-m-d H:i:s', time() - $window );

		// Clean up old entries.
		$this->cleanup();

		// Check current attempts.
		$attempts = $this->db->get_var(
			$this->db->prepare(
				"SELECT attempts FROM {$table} 
				WHERE identifier = %s 
				AND action = %s 
				AND window_start > %s",
				$identifier,
				$action,
				$window_start
			)
		);

		if ( $attempts >= $limit ) {
			$retry_after = $this->get_retry_after( $identifier, $action, $window );
			throw new RateLimitExceededException(
				sprintf(
					/* translators: %d: seconds until retry */
					__( 'Rate limit exceeded. Please try again in %d seconds.', 'money-quiz' ),
					$retry_after
				),
				$retry_after
			);
		}

		// Increment attempts.
		$this->increment( $identifier, $action );

		return true;
	}

	/**
	 * Increment attempts.
	 *
	 * @since 7.0.0
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param string $action     Action being limited.
	 * @return void
	 */
	private function increment( string $identifier, string $action ): void {
		$table = $this->db->prefix . 'money_quiz_rate_limits';

		$this->db->query(
			$this->db->prepare(
				"INSERT INTO {$table} (identifier, action, attempts, window_start) 
				VALUES (%s, %s, 1, %s) 
				ON DUPLICATE KEY UPDATE 
				attempts = attempts + 1,
				window_start = CASE 
					WHEN window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR) 
					THEN %s 
					ELSE window_start 
				END",
				$identifier,
				$action,
				current_time( 'mysql', true ),
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Get remaining attempts.
	 *
	 * @since 7.0.0
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param string $action     Action being limited.
	 * @param int    $limit      Maximum attempts allowed.
	 * @param int    $window     Time window in seconds.
	 * @return int Remaining attempts.
	 */
	public function get_remaining_attempts( string $identifier, string $action, int $limit = 100, int $window = 3600 ): int {
		$table = $this->db->prefix . 'money_quiz_rate_limits';
		$window_start = gmdate( 'Y-m-d H:i:s', time() - $window );

		$attempts = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT attempts FROM {$table} 
				WHERE identifier = %s 
				AND action = %s 
				AND window_start > %s",
				$identifier,
				$action,
				$window_start
			)
		);

		return max( 0, $limit - $attempts );
	}

	/**
	 * Get retry after seconds.
	 *
	 * @since 7.0.0
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param string $action     Action being limited.
	 * @param int    $window     Time window in seconds.
	 * @return int Seconds until retry.
	 */
	private function get_retry_after( string $identifier, string $action, int $window ): int {
		$table = $this->db->prefix . 'money_quiz_rate_limits';

		$window_start = $this->db->get_var(
			$this->db->prepare(
				"SELECT window_start FROM {$table} 
				WHERE identifier = %s 
				AND action = %s 
				ORDER BY window_start DESC 
				LIMIT 1",
				$identifier,
				$action
			)
		);

		if ( ! $window_start ) {
			return $window;
		}

		$elapsed = time() - strtotime( $window_start );
		return max( 0, $window - $elapsed );
	}

	/**
	 * Reset rate limit.
	 *
	 * @since 7.0.0
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param string $action     Action being limited.
	 * @return void
	 */
	public function reset( string $identifier, string $action ): void {
		$table = $this->db->prefix . 'money_quiz_rate_limits';

		$this->db->delete(
			$table,
			[
				'identifier' => $identifier,
				'action' => $action,
			],
			[ '%s', '%s' ]
		);
	}

	/**
	 * Clean up old entries.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function cleanup(): void {
		$table = $this->db->prefix . 'money_quiz_rate_limits';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$this->db->query(
			$this->db->prepare(
				"DELETE FROM {$table} WHERE window_start < %s",
				$cutoff
			)
		);
	}
}