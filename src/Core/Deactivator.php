<?php
/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Deactivator class.
 *
 * @since 7.0.0
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear scheduled events.
		self::clear_scheduled_events();

		// Clean up temporary data.
		self::cleanup_temp_data();

		// Clear plugin caches.
		self::clear_caches();

		// Remove rewrite rules.
		flush_rewrite_rules();

		// Log deactivation if debug is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Money Quiz plugin deactivated at ' . current_time( 'mysql' ) );
		}

		// Fire deactivation hook.
		do_action( 'money_quiz_deactivated' );
	}

	/**
	 * Clear scheduled events.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function clear_scheduled_events(): void {
		// Get all scheduled events.
		$events = [
			'money_quiz_daily_cleanup',
			'money_quiz_hourly_cleanup',
		];

		// Clear each scheduled event.
		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
			
			// Clear all instances of the event.
			wp_clear_scheduled_hook( $event );
		}

		// Allow plugins to clear their own events.
		do_action( 'money_quiz_clear_scheduled_events' );
	}

	/**
	 * Clean up temporary data.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function cleanup_temp_data(): void {
		global $wpdb;

		// Clean up expired sessions from the database.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}money_quiz_results 
				WHERE completed = 0 
				AND created_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		// Clean up old rate limit entries.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}money_quiz_rate_limits 
				WHERE window_start < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) )
			)
		);

		// Clean up temporary files.
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/money-quiz-temp/';

		if ( is_dir( $temp_dir ) ) {
			$files = glob( $temp_dir . '*' );
			
			foreach ( $files as $file ) {
				if ( is_file( $file ) && filemtime( $file ) < strtotime( '-1 day' ) ) {
					unlink( $file );
				}
			}
		}

		// Allow extensions to clean up their temporary data.
		do_action( 'money_quiz_cleanup_temp_data' );
	}

	/**
	 * Clear plugin caches.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private static function clear_caches(): void {
		global $wpdb;

		// Clear transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_money_quiz_%' 
			OR option_name LIKE '_transient_timeout_money_quiz_%'"
		);

		// Clear object cache if available.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'money_quiz' );
		} else {
			// Fallback to flushing entire cache.
			wp_cache_flush();
		}

		// Clear any plugin-specific caches.
		do_action( 'money_quiz_clear_caches' );

		// Notify cache plugins.
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		
		if ( has_action( 'w3tc_flush_all' ) ) {
			do_action( 'w3tc_flush_all' );
		}
		
		if ( has_action( 'wp_cache_clear_cache' ) ) {
			do_action( 'wp_cache_clear_cache' );
		}
		
		if ( has_action( 'rocket_clean_domain' ) ) {
			do_action( 'rocket_clean_domain' );
		}
	}

	/**
	 * Get deactivation reason.
	 *
	 * Used for analytics and improving the plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function track_deactivation(): void {
		// Check if tracking is allowed.
		$settings = get_option( 'money_quiz_settings', [] );
		
		if ( empty( $settings['general']['enable_analytics'] ) ) {
			return;
		}

		// Record deactivation timestamp.
		update_option( 'money_quiz_deactivated_at', time() );

		// Allow tracking via action.
		do_action( 'money_quiz_track_deactivation' );
	}
}