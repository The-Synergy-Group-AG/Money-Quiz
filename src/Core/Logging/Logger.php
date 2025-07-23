<?php
/**
 * Logger Class
 *
 * Secure logging with automatic sanitization of sensitive data.
 *
 * @package MoneyQuiz\Core\Logging
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Logging;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Logger class.
 *
 * @since 7.0.0
 */
class Logger {

	/**
	 * Log directory path.
	 *
	 * @var string
	 */
	private string $log_dir;

	/**
	 * Sensitive patterns to sanitize.
	 *
	 * @var array<string, string>
	 */
	private array $sensitive_patterns = [
		'/password["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i' => 'password: [REDACTED]',
		'/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i' => 'api_key: [REDACTED]',
		'/token["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i' => 'token: [REDACTED]',
		'/secret["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i' => 'secret: [REDACTED]',
		'/nonce["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i' => 'nonce: [REDACTED]',
		'/cookie["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i' => 'cookie: [REDACTED]',
		'/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '[CREDIT_CARD]',
		'/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN]',
		'/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[EMAIL]',
	];

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $log_dir Log directory path.
	 */
	public function __construct( string $log_dir ) {
		$this->log_dir = trailingslashit( $log_dir );
	}

	/**
	 * Log info message.
	 *
	 * @since 7.0.0
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log( 'INFO', $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @since 7.0.0
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( 'WARNING', $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @since 7.0.0
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * Log debug message.
	 *
	 * @since 7.0.0
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function debug( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$this->log( 'DEBUG', $message, $context );
	}

	/**
	 * Write log entry.
	 *
	 * @since 7.0.0
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	private function log( string $level, string $message, array $context ): void {
		// Sanitize message and context.
		$message = $this->sanitize( $message );
		$context = $this->sanitize_array( $context );

		// Format log entry.
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$entry = sprintf(
			"[%s] [%s] %s\n",
			$timestamp,
			$level,
			$message
		);

		// Add context if provided.
		if ( ! empty( $context ) ) {
			$entry .= "Context: " . wp_json_encode( $context, JSON_PRETTY_PRINT ) . "\n";
		}

		// Get log file path.
		$log_file = $this->get_log_file();

		// Write to log file.
		error_log( $entry, 3, $log_file );

		// Rotate logs if needed.
		$this->rotate_logs( $log_file );
	}

	/**
	 * Sanitize sensitive data from string.
	 *
	 * @since 7.0.0
	 *
	 * @param string $data Data to sanitize.
	 * @return string Sanitized data.
	 */
	private function sanitize( string $data ): string {
		foreach ( $this->sensitive_patterns as $pattern => $replacement ) {
			$data = preg_replace( $pattern, $replacement, $data );
		}

		return $data;
	}

	/**
	 * Sanitize array recursively.
	 *
	 * @since 7.0.0
	 *
	 * @param array $data Data to sanitize.
	 * @return array Sanitized data.
	 */
	private function sanitize_array( array $data ): array {
		foreach ( $data as $key => $value ) {
			// Sanitize sensitive keys.
			$lower_key = strtolower( $key );
			if ( in_array( $lower_key, [ 'password', 'api_key', 'token', 'secret', 'nonce' ], true ) ) {
				$data[ $key ] = '[REDACTED]';
				continue;
			}

			// Recursively sanitize arrays.
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->sanitize_array( $value );
			} elseif ( is_string( $value ) ) {
				$data[ $key ] = $this->sanitize( $value );
			}
		}

		return $data;
	}

	/**
	 * Get current log file path.
	 *
	 * @since 7.0.0
	 *
	 * @return string Log file path.
	 */
	private function get_log_file(): string {
		$date = gmdate( 'Y-m-d' );
		return $this->log_dir . "money-quiz-{$date}.log";
	}

	/**
	 * Rotate logs if file size exceeds limit.
	 *
	 * @since 7.0.0
	 *
	 * @param string $log_file Current log file.
	 * @return void
	 */
	private function rotate_logs( string $log_file ): void {
		// Check file size (10MB limit).
		if ( ! file_exists( $log_file ) || filesize( $log_file ) < 10 * 1024 * 1024 ) {
			return;
		}

		// Rotate file.
		$rotated_file = $log_file . '.' . time();
		rename( $log_file, $rotated_file );

		// Clean up old logs (keep 30 days).
		$this->cleanup_old_logs();
	}

	/**
	 * Clean up old log files.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function cleanup_old_logs(): void {
		$files = glob( $this->log_dir . 'money-quiz-*.log*' );
		$cutoff = time() - ( 30 * DAY_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Get sanitization patterns.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, string> Sanitization patterns.
	 */
	public function get_sanitization_patterns(): array {
		return $this->sensitive_patterns;
	}

	/**
	 * Add custom sanitization pattern.
	 *
	 * @since 7.0.0
	 *
	 * @param string $pattern     Regex pattern.
	 * @param string $replacement Replacement string.
	 * @return void
	 */
	public function add_sanitization_pattern( string $pattern, string $replacement ): void {
		$this->sensitive_patterns[ $pattern ] = $replacement;
	}
}