<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation tasks.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Activator class.
 *
 * @since 7.0.0
 */
class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Check minimum requirements.
		self::check_requirements();

		// Create database tables.
		self::create_tables();

		// Set default options.
		self::set_default_options();

		// Create necessary directories.
		self::create_directories();

		// Schedule events.
		self::schedule_events();

		// Add capabilities.
		self::add_capabilities();

		// Clear rewrite rules.
		flush_rewrite_rules();

		// Set activation flag.
		set_transient( 'money_quiz_activated', true, 60 );

		// Fire activation hook.
		do_action( 'money_quiz_activated' );
	}

	/**
	 * Check plugin requirements.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 *
	 * @throws \Exception If requirements not met.
	 */
	private static function check_requirements(): void {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, MONEY_QUIZ_MINIMUM_PHP_VERSION, '<' ) ) {
			deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
			
			// Add admin notice instead of wp_die for better security.
			add_action( 'admin_notices', function() {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Required PHP version */
						esc_html__( 'Money Quiz requires PHP version %s or higher. The plugin has been deactivated.', 'money-quiz' ),
						MONEY_QUIZ_MINIMUM_PHP_VERSION
					)
				);
			} );
			
			// Trigger redirect to avoid showing a broken state.
			add_action( 'admin_init', function() {
				wp_safe_redirect( admin_url( 'plugins.php?error=true' ) );
				exit;
			} );
			
			return;
		}

		// Check WordPress version.
		if ( version_compare( get_bloginfo( 'version' ), MONEY_QUIZ_MINIMUM_WP_VERSION, '<' ) ) {
			deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
			
			// Add admin notice instead of wp_die for better security.
			add_action( 'admin_notices', function() {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: %s: Required WordPress version */
						esc_html__( 'Money Quiz requires WordPress version %s or higher. The plugin has been deactivated.', 'money-quiz' ),
						MONEY_QUIZ_MINIMUM_WP_VERSION
					)
				);
			} );
			
			// Trigger redirect to avoid showing a broken state.
			add_action( 'admin_init', function() {
				wp_safe_redirect( admin_url( 'plugins.php?error=true' ) );
				exit;
			} );
			
			return;
		}

		// Check for required PHP extensions.
		$required_extensions = [ 'json', 'mbstring' ];
		$missing_extensions = [];

		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$missing_extensions[] = $extension;
			}
		}

		if ( ! empty( $missing_extensions ) ) {
			deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
			
			// Add admin notice instead of wp_die for better security.
			add_action( 'admin_notices', function() use ( $missing_extensions ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: %s: List of missing extensions */
						esc_html__( 'Money Quiz requires the following PHP extensions: %s. The plugin has been deactivated.', 'money-quiz' ),
						implode( ', ', $missing_extensions )
					)
				);
			} );
			
			// Trigger redirect to avoid showing a broken state.
			add_action( 'admin_init', function() {
				wp_safe_redirect( admin_url( 'plugins.php?error=true' ) );
				exit;
			} );
			
			return;
		}
	}

	/**
	 * Create database tables.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Load upgrade functions.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Quizzes table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_quizzes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text,
			settings longtext,
			status varchar(20) NOT NULL DEFAULT 'draft',
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_by (created_by)
		) $charset_collate;";
		dbDelta( $sql );

		// Questions table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_questions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quiz_id bigint(20) unsigned NOT NULL,
			question text NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'single_choice',
			settings longtext,
			order_index int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY quiz_id (quiz_id),
			KEY order_index (order_index)
		) $charset_collate;";
		dbDelta( $sql );

		// Answers table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_answers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question_id bigint(20) unsigned NOT NULL,
			answer text NOT NULL,
			value int(11) NOT NULL DEFAULT 0,
			is_correct tinyint(1) NOT NULL DEFAULT 0,
			order_index int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY question_id (question_id),
			KEY order_index (order_index)
		) $charset_collate;";
		dbDelta( $sql );

		// Results table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_results (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quiz_id bigint(20) unsigned NOT NULL,
			session_id varchar(255) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			score int(11) NOT NULL DEFAULT 0,
			percentage decimal(5,2) NOT NULL DEFAULT 0.00,
			completed tinyint(1) NOT NULL DEFAULT 0,
			email varchar(255) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY quiz_id (quiz_id),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY completed (completed),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// Responses table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_responses (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			result_id bigint(20) unsigned NOT NULL,
			question_id bigint(20) unsigned NOT NULL,
			answer_id bigint(20) unsigned NOT NULL,
			value text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY result_id (result_id),
			KEY question_id (question_id),
			KEY answer_id (answer_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Analytics table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_analytics (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quiz_id bigint(20) unsigned NOT NULL,
			event_type varchar(50) NOT NULL,
			event_data longtext,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY quiz_id (quiz_id),
			KEY event_type (event_type),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// Rate limits table.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_rate_limits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identifier varchar(255) NOT NULL,
			action varchar(100) NOT NULL,
			attempts int(11) NOT NULL DEFAULT 1,
			window_start datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY rate_limit (identifier, action),
			KEY window_start (window_start)
		) $charset_collate;";
		dbDelta( $sql );
	}

	/**
	 * Set default options.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		// Set plugin version.
		add_option( 'money_quiz_version', MONEY_QUIZ_VERSION );

		// Set default settings.
		$default_settings = [
			'general' => [
				'enable_analytics' => true,
				'enable_email_results' => true,
				'results_per_page' => 20,
				'quiz_time_limit' => 0,
			],
			'security' => [
				'enable_rate_limiting' => true,
				'rate_limit_attempts' => 5,
				'rate_limit_window' => 300, // 5 minutes.
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
				'cache_duration' => 3600, // 1 hour.
			],
		];

		add_option( 'money_quiz_settings', $default_settings );

		// Set installation timestamp.
		add_option( 'money_quiz_installed', time() );
	}

	/**
	 * Create necessary directories.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function create_directories(): void {
		$upload_dir = wp_upload_dir();
		$directories = [
			$upload_dir['basedir'] . '/money-quiz-logs/',
			$upload_dir['basedir'] . '/money-quiz-exports/',
			$upload_dir['basedir'] . '/money-quiz-temp/',
		];

		foreach ( $directories as $directory ) {
			if ( ! file_exists( $directory ) ) {
				wp_mkdir_p( $directory );
				
				// Add .htaccess to protect directories.
				$htaccess_content = "Order deny,allow\nDeny from all";
				file_put_contents( $directory . '.htaccess', $htaccess_content );
				
				// Add index.php for extra protection.
				$index_content = '<?php // Silence is golden.';
				file_put_contents( $directory . 'index.php', $index_content );
			}
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function schedule_events(): void {
		// Schedule daily cleanup.
		if ( ! wp_next_scheduled( 'money_quiz_daily_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'money_quiz_daily_cleanup' );
		}

		// Schedule hourly cleanup.
		if ( ! wp_next_scheduled( 'money_quiz_hourly_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'money_quiz_hourly_cleanup' );
		}
	}

	/**
	 * Add plugin capabilities.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function add_capabilities(): void {
		$roles = [ 'administrator' ];
		
		$capabilities = [
			'manage_money_quiz',
			'create_money_quiz',
			'edit_money_quiz',
			'delete_money_quiz',
			'view_money_quiz_analytics',
			'export_money_quiz_data',
			'manage_money_quiz_settings',
		];

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			
			if ( $role ) {
				foreach ( $capabilities as $capability ) {
					$role->add_cap( $capability );
				}
			}
		}

		// Allow filtering of capabilities.
		do_action( 'money_quiz_add_capabilities' );
	}
}