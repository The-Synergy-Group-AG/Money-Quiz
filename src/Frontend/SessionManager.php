<?php
/**
 * Session Manager
 *
 * Secure session management for quiz progress tracking.
 *
 * @package MoneyQuiz\Frontend
 * @since   7.0.0
 */

namespace MoneyQuiz\Frontend;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Session manager class.
 *
 * @since 7.0.0
 */
class SessionManager {

	/**
	 * Session prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Session duration in seconds.
	 *
	 * @var int
	 */
	private int $duration = 7200; // 2 hours

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $prefix Session prefix.
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix . '_session_';
	}

	/**
	 * Initialize session handling.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Start PHP session if not already started.
		if ( ! session_id() && ! headers_sent() ) {
			session_start( [
				'cookie_secure' => is_ssl(),
				'cookie_httponly' => true,
				'cookie_samesite' => 'Strict',
				'use_strict_mode' => true,
			] );
		}

		// Initialize session data if new.
		if ( ! $this->has( 'initialized' ) ) {
			$this->set( 'initialized', time() );
			$this->set( 'session_id', $this->generate_session_id() );
		}

		// Check session expiration.
		$this->check_expiration();
	}

	/**
	 * Get session value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key     Session key.
	 * @param mixed  $default Default value.
	 * @return mixed Session value.
	 */
	public function get( string $key, $default = null ) {
		if ( ! isset( $_SESSION[ $this->prefix . $key ] ) ) {
			return $default;
		}

		$data = $_SESSION[ $this->prefix . $key ];

		// Unserialize if needed.
		if ( is_string( $data ) && $this->is_serialized( $data ) ) {
			$data = unserialize( $data );
		}

		return $data;
	}

	/**
	 * Set session value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		// Serialize complex data types.
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = serialize( $value );
		}

		$_SESSION[ $this->prefix . $key ] = $value;
		$_SESSION[ $this->prefix . 'last_activity' ] = time();
	}

	/**
	 * Check if session key exists.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key Session key.
	 * @return bool True if exists.
	 */
	public function has( string $key ): bool {
		return isset( $_SESSION[ $this->prefix . $key ] );
	}

	/**
	 * Remove session value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key Session key.
	 * @return void
	 */
	public function remove( string $key ): void {
		unset( $_SESSION[ $this->prefix . $key ] );
	}

	/**
	 * Clear all session data.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function clear(): void {
		foreach ( $_SESSION as $key => $value ) {
			if ( strpos( $key, $this->prefix ) === 0 ) {
				unset( $_SESSION[ $key ] );
			}
		}
	}

	/**
	 * Get session ID.
	 *
	 * @since 7.0.0
	 *
	 * @return string Session ID.
	 */
	public function get_session_id(): string {
		return $this->get( 'session_id', '' );
	}

	/**
	 * Regenerate session ID.
	 *
	 * @since 7.0.0
	 *
	 * @return string New session ID.
	 */
	public function regenerate(): string {
		// Regenerate PHP session ID for security.
		if ( session_id() ) {
			session_regenerate_id( true );
		}

		// Generate new internal session ID.
		$new_id = $this->generate_session_id();
		$this->set( 'session_id', $new_id );

		return $new_id;
	}

	/**
	 * Check session expiration.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function check_expiration(): void {
		$last_activity = $this->get( 'last_activity', 0 );

		if ( $last_activity && ( time() - $last_activity ) > $this->duration ) {
			// Session expired, clear data.
			$this->clear();
			$this->set( 'initialized', time() );
			$this->set( 'session_id', $this->generate_session_id() );
		}
	}

	/**
	 * Generate secure session ID.
	 *
	 * @since 7.0.0
	 *
	 * @return string Session ID.
	 */
	private function generate_session_id(): string {
		return wp_generate_password( 32, false );
	}

	/**
	 * Check if data is serialized.
	 *
	 * @since 7.0.0
	 *
	 * @param string $data Data to check.
	 * @return bool True if serialized.
	 */
	private function is_serialized( string $data ): bool {
		return ( $data === 'b:0;' || @unserialize( $data ) !== false );
	}

	/**
	 * Set session duration.
	 *
	 * @since 7.0.0
	 *
	 * @param int $seconds Duration in seconds.
	 * @return void
	 */
	public function set_duration( int $seconds ): void {
		$this->duration = $seconds;
	}

	/**
	 * Get remaining session time.
	 *
	 * @since 7.0.0
	 *
	 * @return int Remaining seconds.
	 */
	public function get_remaining_time(): int {
		$last_activity = $this->get( 'last_activity', time() );
		$elapsed = time() - $last_activity;
		
		return max( 0, $this->duration - $elapsed );
	}

	/**
	 * Clean up expired sessions.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function cleanup_expired_sessions(): void {
		global $wpdb;

		// Clean up database-stored session data if implemented.
		$table = $wpdb->prefix . 'money_quiz_sessions';
		
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE last_activity < %s",
					gmdate( 'Y-m-d H:i:s', time() - $this->duration )
				)
			);
		}
	}
}