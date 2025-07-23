<?php
/**
 * Rate Limit Exceeded Exception
 *
 * @package MoneyQuiz\Core\Exceptions
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Exceptions;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Rate limit exceeded exception class.
 *
 * @since 7.0.0
 */
class RateLimitExceededException extends \Exception {
	
	/**
	 * Retry after seconds.
	 *
	 * @var int
	 */
	private int $retry_after;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $message     Exception message.
	 * @param int    $retry_after Seconds until rate limit resets.
	 * @param int    $code        Exception code.
	 */
	public function __construct( string $message = '', int $retry_after = 60, int $code = 429 ) {
		parent::__construct( $message, $code );
		$this->retry_after = $retry_after;
	}

	/**
	 * Get retry after seconds.
	 *
	 * @since 7.0.0
	 *
	 * @return int Seconds until rate limit resets.
	 */
	public function get_retry_after(): int {
		return $this->retry_after;
	}
}