<?php
/**
 * Uninstall Handler
 *
 * Handles plugin uninstallation cleanup.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Uninstall handler class.
 *
 * @since 7.0.0
 */
class UninstallHandler {

	/**
	 * Handle uninstallation.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		// Check if user has permission to uninstall plugins.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Remove plugin options.
		self::remove_options();

		// Remove custom tables.
		self::remove_tables();

		// Remove custom capabilities.
		self::remove_capabilities();

		// Clear scheduled events.
		self::clear_scheduled_events();

		// Clear transients.
		self::clear_transients();

		// Remove uploads directory.
		self::remove_uploads();

		// Clear any cached data.
		self::clear_cache();
	}

	/**
	 * Remove plugin options.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function remove_options(): void {
		$options = [
			'money_quiz_version',
			'money_quiz_settings',
			'money_quiz_activation_time',
			'money_quiz_install_data',
			'money_quiz_license_key',
			'money_quiz_license_status',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Remove options with dynamic names.
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE 'money_quiz_%' 
			OR option_name LIKE '_transient_money_quiz_%' 
			OR option_name LIKE '_transient_timeout_money_quiz_%'"
		);
	}

	/**
	 * Remove custom tables.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function remove_tables(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'money_quiz_quizzes',
			$wpdb->prefix . 'money_quiz_questions',
			$wpdb->prefix . 'money_quiz_answers',
			$wpdb->prefix . 'money_quiz_results',
			$wpdb->prefix . 'money_quiz_result_answers',
			$wpdb->prefix . 'money_quiz_analytics',
			$wpdb->prefix . 'money_quiz_categories',
			$wpdb->prefix . 'money_quiz_rate_limits',
			$wpdb->prefix . 'money_quiz_security_events',
			$wpdb->prefix . 'money_quiz_migrations',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Remove custom capabilities.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function remove_capabilities(): void {
		$capabilities = [
			'manage_money_quiz',
			'edit_money_quiz',
			'delete_money_quiz',
			'publish_money_quiz',
			'view_money_quiz_analytics',
			'export_money_quiz_data',
			'manage_money_quiz_settings',
		];

		$roles = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $capabilities as $cap ) {
					if ( $role->has_cap( $cap ) ) {
						$role->remove_cap( $cap );
					}
				}
			}
		}
	}

	/**
	 * Clear scheduled events.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function clear_scheduled_events(): void {
		$events = [
			'money_quiz_daily_cleanup',
			'money_quiz_hourly_sync',
			'money_quiz_weekly_report',
		];

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}
	}

	/**
	 * Clear transients.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function clear_transients(): void {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_money_quiz_%' 
			OR option_name LIKE '_transient_timeout_money_quiz_%'"
		);
	}

	/**
	 * Remove uploads directory.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function remove_uploads(): void {
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/money-quiz';

		if ( is_dir( $plugin_upload_dir ) ) {
			self::remove_directory( $plugin_upload_dir );
		}
	}

	/**
	 * Remove directory recursively.
	 *
	 * @since 7.0.0
	 *
	 * @param string $dir Directory path.
	 * @return bool True if removed.
	 */
	private static function remove_directory( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), [ '.', '..' ] );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::remove_directory( $path );
			} else {
				unlink( $path );
			}
		}

		return rmdir( $dir );
	}

	/**
	 * Clear cache.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function clear_cache(): void {
		// Clear object cache.
		wp_cache_flush();

		// Clear page cache if available.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// Clear opcache if available.
		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
		}
	}
}