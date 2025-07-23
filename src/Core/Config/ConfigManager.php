<?php
/**
 * Configuration Manager
 *
 * Manages plugin configuration and settings.
 *
 * @package MoneyQuiz\Core\Config
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Config;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Configuration manager class.
 *
 * @since 7.0.0
 */
class ConfigManager {

	/**
	 * Configuration directory.
	 *
	 * @var string
	 */
	private string $config_dir;

	/**
	 * Loaded configuration.
	 *
	 * @var array<string, mixed>
	 */
	private array $config = [];

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $config_dir Configuration directory path.
	 */
	public function __construct( string $config_dir ) {
		$this->config_dir = trailingslashit( $config_dir );
	}

	/**
	 * Load configuration files.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function load(): void {
		// Load default configuration.
		$this->load_defaults();

		// Load database configuration.
		$this->load_from_database();

		// Allow plugins to modify configuration.
		$this->config = apply_filters( 'money_quiz_config', $this->config );
	}

	/**
	 * Load default configuration.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function load_defaults(): void {
		$defaults = [
			'general' => [
				'enable_analytics' => true,
				'enable_email_results' => true,
				'results_per_page' => 20,
				'quiz_time_limit' => 0,
			],
			'security' => [
				'enable_rate_limiting' => true,
				'rate_limit_attempts' => 100,
				'rate_limit_window' => 3600, // 1 hour
				'enable_honeypot' => true,
				'require_login' => false,
			],
			'email' => [
				'from_name' => get_bloginfo( 'name' ),
				'from_email' => get_option( 'admin_email' ),
				'email_subject' => __( 'Your Quiz Results', 'money-quiz' ),
				'enable_admin_notifications' => true,
				'admin_email' => get_option( 'admin_email' ),
			],
			'advanced' => [
				'remove_data_on_uninstall' => false,
				'enable_debug_mode' => false,
				'cache_duration' => 3600,
			],
		];

		$this->config = array_merge( $this->config, $defaults );
	}

	/**
	 * Load configuration from database.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function load_from_database(): void {
		$saved = get_option( 'money_quiz_settings', [] );
		
		if ( ! empty( $saved ) ) {
			$this->config = array_replace_recursive( $this->config, $saved );
		}
	}

	/**
	 * Get configuration value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key     Configuration key (dot notation).
	 * @param mixed  $default Default value.
	 * @return mixed Configuration value.
	 */
	public function get( string $key, $default = null ) {
		$keys = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! isset( $value[ $k ] ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}

	/**
	 * Set configuration value.
	 *
	 * @since 7.0.0
	 *
	 * @param string $key   Configuration key (dot notation).
	 * @param mixed  $value Configuration value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$keys = explode( '.', $key );
		$config = &$this->config;

		foreach ( $keys as $i => $k ) {
			if ( $i === count( $keys ) - 1 ) {
				$config[ $k ] = $value;
			} else {
				if ( ! isset( $config[ $k ] ) || ! is_array( $config[ $k ] ) ) {
					$config[ $k ] = [];
				}
				$config = &$config[ $k ];
			}
		}
	}

	/**
	 * Save configuration to database.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True on success.
	 */
	public function save(): bool {
		return update_option( 'money_quiz_settings', $this->config );
	}

	/**
	 * Get all configuration.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> All configuration.
	 */
	public function all(): array {
		return $this->config;
	}

	/**
	 * Reset configuration to defaults.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->config = [];
		$this->load_defaults();
		$this->save();
	}
}