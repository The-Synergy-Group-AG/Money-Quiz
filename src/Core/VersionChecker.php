<?php
/**
 * Version Checker
 *
 * Handles version checking and upgrade notifications.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Version checker class.
 *
 * @since 7.0.0
 */
class VersionChecker {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Minimum PHP version.
	 *
	 * @var string
	 */
	private string $min_php;

	/**
	 * Minimum WordPress version.
	 *
	 * @var string
	 */
	private string $min_wp;

	/**
	 * Update URI.
	 *
	 * @var string
	 */
	private string $update_uri;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $version    Plugin version.
	 * @param string $min_php    Minimum PHP version.
	 * @param string $min_wp     Minimum WordPress version.
	 * @param string $update_uri Update check URI.
	 */
	public function __construct( string $version, string $min_php, string $min_wp, string $update_uri ) {
		$this->version = $version;
		$this->min_php = $min_php;
		$this->min_wp = $min_wp;
		$this->update_uri = $update_uri;
	}

	/**
	 * Check if environment meets requirements.
	 *
	 * @since 7.0.0
	 *
	 * @return array{compatible: bool, errors: array<string>}
	 */
	public function check_compatibility(): array {
		$errors = [];

		// Check PHP version.
		if ( version_compare( PHP_VERSION, $this->min_php, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				__( 'Money Quiz requires PHP %1$s or higher. Your version is %2$s.', 'money-quiz' ),
				$this->min_php,
				PHP_VERSION
			);
		}

		// Check WordPress version.
		if ( version_compare( get_bloginfo( 'version' ), $this->min_wp, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: Required WP version, 2: Current WP version */
				__( 'Money Quiz requires WordPress %1$s or higher. Your version is %2$s.', 'money-quiz' ),
				$this->min_wp,
				get_bloginfo( 'version' )
			);
		}

		// Check required PHP extensions.
		$required_extensions = [ 'json', 'mbstring' ];
		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$errors[] = sprintf(
					/* translators: %s: PHP extension name */
					__( 'Money Quiz requires the PHP %s extension.', 'money-quiz' ),
					$extension
				);
			}
		}

		return [
			'compatible' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Check if update is available.
	 *
	 * @since 7.0.0
	 *
	 * @return array{available: bool, version: string, url: string}|null
	 */
	public function check_for_updates(): ?array {
		// Check transient cache first.
		$cache_key = 'money_quiz_update_check';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Perform update check.
		$response = wp_remote_get(
			$this->update_uri,
			[
				'timeout' => 10,
				'headers' => [
					'X-Money-Quiz-Version' => $this->version,
					'X-Site-URL' => home_url(),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			// Cache failure for 1 hour.
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['version'] ) ) {
			// Cache failure for 1 hour.
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}

		$update_available = version_compare( $data['version'], $this->version, '>' );

		$result = [
			'available' => $update_available,
			'version' => $data['version'],
			'url' => $data['download_url'] ?? '',
		];

		// Cache result for 12 hours.
		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get current plugin version.
	 *
	 * @since 7.0.0
	 *
	 * @return string Plugin version.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Compare version with another version.
	 *
	 * @since 7.0.0
	 *
	 * @param string $version Version to compare.
	 * @param string $operator Comparison operator.
	 * @return bool Comparison result.
	 */
	public function compare( string $version, string $operator = '>=' ): bool {
		return version_compare( $this->version, $version, $operator );
	}

	/**
	 * Get version history.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, string> Version history.
	 */
	public function get_history(): array {
		return [
			'7.0.0' => __( 'Complete security-focused rewrite with PSR-11 container', 'money-quiz' ),
			'6.0.0' => __( 'Failed security audit - deprecated', 'money-quiz' ),
			'5.0.0' => __( 'Failed implementation - deprecated', 'money-quiz' ),
			'3.22.10' => __( 'Legacy version with security vulnerabilities', 'money-quiz' ),
		];
	}
}