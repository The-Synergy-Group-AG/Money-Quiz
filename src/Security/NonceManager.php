<?php
/**
 * Nonce Manager
 *
 * Manages WordPress nonces for CSRF protection.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Nonce manager class.
 *
 * @since 7.0.0
 */
class NonceManager {

	/**
	 * Nonce prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Nonce lifetime in seconds.
	 *
	 * @var int
	 */
	private int $lifetime = 3600; // 1 hour default for security

	/**
	 * Critical action lifetimes.
	 *
	 * @var array
	 */
	private array $critical_actions = [
		'delete_quiz' => 300,        // 5 minutes
		'export_data' => 600,        // 10 minutes
		'change_settings' => 900,    // 15 minutes
		'manage_users' => 600,       // 10 minutes
		'reset_data' => 300,         // 5 minutes
	];

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $prefix Nonce prefix.
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix . '_';
	}

	/**
	 * Create nonce.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action Nonce action.
	 * @return string Nonce value.
	 */
	public function create( string $action ): string {
		return wp_create_nonce( $this->prefix . $action );
	}

	/**
	 * Verify nonce.
	 *
	 * @since 7.0.0
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Nonce action.
	 * @return bool True if valid.
	 */
	public function verify( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $this->prefix . $action ) !== false;
	}

	/**
	 * Verify nonce from request.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action     Nonce action.
	 * @param string $query_arg  Query argument name.
	 * @return bool True if valid.
	 */
	public function verify_request( string $action, string $query_arg = '_wpnonce' ): bool {
		$nonce = $_REQUEST[ $query_arg ] ?? '';
		return $this->verify( $nonce, $action );
	}

	/**
	 * Create nonce field.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action     Nonce action.
	 * @param string $name       Field name.
	 * @param bool   $referer    Include referer field.
	 * @param bool   $echo       Echo the field.
	 * @return string Nonce field HTML.
	 */
	public function field( string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true ): string {
		return wp_nonce_field( $this->prefix . $action, $name, $referer, $echo );
	}

	/**
	 * Create nonce URL.
	 *
	 * @since 7.0.0
	 *
	 * @param string $url        URL to add nonce to.
	 * @param string $action     Nonce action.
	 * @param string $query_arg  Query argument name.
	 * @return string URL with nonce.
	 */
	public function url( string $url, string $action, string $query_arg = '_wpnonce' ): string {
		return wp_nonce_url( $url, $this->prefix . $action, $query_arg );
	}

	/**
	 * Check admin referer.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action     Nonce action.
	 * @param string $query_arg  Query argument name.
	 * @return bool True if valid.
	 */
	public function check_admin_referer( string $action, string $query_arg = '_wpnonce' ): bool {
		return check_admin_referer( $this->prefix . $action, $query_arg ) !== false;
	}

	/**
	 * Check AJAX referer.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action     Nonce action.
	 * @param string $query_arg  Query argument name.
	 * @param bool   $die        Whether to die on failure.
	 * @return bool True if valid.
	 */
	public function check_ajax_referer( string $action, string $query_arg = '_wpnonce', bool $die = true ): bool {
		return check_ajax_referer( $this->prefix . $action, $query_arg, $die ) !== false;
	}

	/**
	 * Get nonce lifetime.
	 *
	 * @since 7.0.0
	 *
	 * @param string|null $action Action name to check for critical lifetime.
	 * @return int Lifetime in seconds.
	 */
	public function get_lifetime( ?string $action = null ): int {
		// Check if this is a critical action.
		if ( $action && isset( $this->critical_actions[ $action ] ) ) {
			return $this->critical_actions[ $action ];
		}

		// Use filter for standard lifetime.
		return apply_filters( 'money_quiz_nonce_life', $this->lifetime, $action );
	}

	/**
	 * Set lifetime for critical action.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action   Action name.
	 * @param int    $lifetime Lifetime in seconds.
	 * @return void
	 */
	public function set_critical_lifetime( string $action, int $lifetime ): void {
		$this->critical_actions[ $action ] = $lifetime;
	}
}