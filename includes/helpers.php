<?php
/**
 * Helper Functions
 *
 * Global helper functions for the Money Quiz plugin.
 *
 * @package MoneyQuiz
 * @since   7.0.0
 */

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Get the Money Quiz plugin instance.
 *
 * @since 7.0.0
 *
 * @return \MoneyQuiz\Core\Plugin|null Plugin instance or null if not initialized.
 */
function money_quiz() {
	static $plugin = null;
	
	if ( $plugin === null ) {
		// Try to get plugin from bootstrap if available.
		try {
			// Access the container through a static method to avoid globals.
			$container = \MoneyQuiz\Core\Container::get_instance();
			if ( $container && $container->has( 'bootstrap' ) ) {
				$bootstrap = $container->get( 'bootstrap' );
				$plugin = $bootstrap->get_plugin();
			}
		} catch ( \Exception $e ) {
			// Plugin not initialized yet.
			return null;
		}
	}
	
	return $plugin;
}

/**
 * Get a service from the container.
 *
 * @since 7.0.0
 *
 * @param string $id Service identifier.
 * @return mixed|null Service instance or null if not found.
 */
function money_quiz_get( string $id ) {
	$plugin = money_quiz();
	
	if ( ! $plugin || ! $plugin->is_initialized() ) {
		return null;
	}

	try {
		return $plugin->get( $id );
	} catch ( \Exception $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Money Quiz service not found: ' . $id );
		}
		return null;
	}
}

/**
 * Get plugin version.
 *
 * @since 7.0.0
 *
 * @return string Plugin version.
 */
function money_quiz_version(): string {
	return defined( 'MONEY_QUIZ_VERSION' ) ? MONEY_QUIZ_VERSION : '0.0.0';
}

/**
 * Get plugin URL.
 *
 * @since 7.0.0
 *
 * @param string $path Optional path to append.
 * @return string Plugin URL.
 */
function money_quiz_url( string $path = '' ): string {
	$url = defined( 'MONEY_QUIZ_PLUGIN_URL' ) ? MONEY_QUIZ_PLUGIN_URL : '';
	
	if ( $path ) {
		$url = trailingslashit( $url ) . ltrim( $path, '/' );
	}

	return $url;
}

/**
 * Get plugin directory path.
 *
 * @since 7.0.0
 *
 * @param string $path Optional path to append.
 * @return string Plugin directory path.
 */
function money_quiz_dir( string $path = '' ): string {
	$dir = defined( 'MONEY_QUIZ_PLUGIN_DIR' ) ? MONEY_QUIZ_PLUGIN_DIR : '';
	
	if ( $path ) {
		$dir = trailingslashit( $dir ) . ltrim( $path, '/' );
	}

	return $dir;
}

/**
 * Check if Money Quiz is active and initialized.
 *
 * @since 7.0.0
 *
 * @return bool True if active and initialized.
 */
function money_quiz_is_active(): bool {
	$plugin = money_quiz();
	return $plugin && $plugin->is_initialized();
}

/**
 * Get Money Quiz admin page URL.
 *
 * @since 7.0.0
 *
 * @param string $page Page slug (e.g., 'settings', 'analytics').
 * @param array  $args Optional query arguments.
 * @return string Admin page URL.
 */
function money_quiz_admin_url( string $page = '', array $args = [] ): string {
	$base_url = admin_url( 'admin.php' );
	
	if ( ! $page ) {
		$args['page'] = 'money-quiz';
	} else {
		$args['page'] = 'money-quiz-' . $page;
	}

	return add_query_arg( $args, $base_url );
}

/**
 * Check if current user can manage Money Quiz.
 *
 * @since 7.0.0
 *
 * @param string $capability Specific capability to check.
 * @return bool True if user has capability.
 */
function money_quiz_can( string $capability = 'manage_money_quiz' ): bool {
	return current_user_can( $capability );
}

/**
 * Get Money Quiz setting value.
 *
 * @since 7.0.0
 *
 * @param string $key     Setting key (use dot notation for nested: 'general.enable_analytics').
 * @param mixed  $default Default value if setting not found.
 * @return mixed Setting value.
 */
function money_quiz_setting( string $key, $default = null ) {
	static $settings = null;

	if ( $settings === null ) {
		$settings = get_option( 'money_quiz_settings', [] );
	}

	// Handle dot notation.
	$keys = explode( '.', $key );
	$value = $settings;

	foreach ( $keys as $k ) {
		if ( ! is_array( $value ) || ! isset( $value[ $k ] ) ) {
			return $default;
		}
		$value = $value[ $k ];
	}

	return $value;
}

/**
 * Log a message to Money Quiz logs.
 *
 * @since 7.0.0
 *
 * @param string $message Log message.
 * @param string $level   Log level (info, warning, error, debug).
 * @param array  $context Additional context data.
 * @return void
 */
function money_quiz_log( string $message, string $level = 'info', array $context = [] ): void {
	$logger = money_quiz_get( 'Logger' );
	
	if ( ! $logger ) {
		return;
	}

	switch ( $level ) {
		case 'error':
			$logger->error( $message, $context );
			break;
		case 'warning':
			$logger->warning( $message, $context );
			break;
		case 'debug':
			$logger->debug( $message, $context );
			break;
		default:
			$logger->info( $message, $context );
	}
}

/**
 * Sanitize and validate quiz ID.
 *
 * @since 7.0.0
 *
 * @param mixed $quiz_id Quiz ID to validate.
 * @return int|false Valid quiz ID or false.
 */
function money_quiz_validate_id( $quiz_id ) {
	$quiz_id = absint( $quiz_id );
	
	if ( $quiz_id <= 0 ) {
		return false;
	}

	return $quiz_id;
}

/**
 * Format date for display.
 *
 * @since 7.0.0
 *
 * @param string $date   Date string.
 * @param string $format Optional format string.
 * @return string Formatted date.
 */
function money_quiz_format_date( string $date, string $format = '' ): string {
	if ( ! $format ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	}

	$timestamp = strtotime( $date );
	
	if ( ! $timestamp ) {
		return $date;
	}

	return wp_date( $format, $timestamp );
}

/**
 * Get quiz shortcode with attributes.
 *
 * @since 7.0.0
 *
 * @param int   $quiz_id Quiz ID.
 * @param array $atts    Additional attributes.
 * @return string Shortcode string.
 */
function money_quiz_shortcode( int $quiz_id, array $atts = [] ): string {
	$atts['id'] = $quiz_id;
	
	$shortcode = '[money_quiz';
	
	foreach ( $atts as $key => $value ) {
		$shortcode .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
	}
	
	$shortcode .= ']';

	return $shortcode;
}

/**
 * Check if we're on a Money Quiz admin page.
 *
 * @since 7.0.0
 *
 * @param string $page Optional specific page to check.
 * @return bool True if on Money Quiz admin page.
 */
function money_quiz_is_admin_page( string $page = '' ): bool {
	if ( ! is_admin() ) {
		return false;
	}

	$screen = get_current_screen();
	
	if ( ! $screen ) {
		return false;
	}

	if ( $page ) {
		return $screen->id === 'money-quiz_page_money-quiz-' . $page;
	}

	return strpos( $screen->id, 'money-quiz' ) !== false;
}

/**
 * Get the current quiz session ID.
 *
 * @since 7.0.0
 *
 * @return string|null Session ID or null if no session.
 */
function money_quiz_session_id(): ?string {
	$session_manager = money_quiz_get( 'SessionManager' );
	
	if ( ! $session_manager ) {
		return null;
	}

	return $session_manager->get_session_id();
}

/**
 * Display a Money Quiz admin notice.
 *
 * @since 7.0.0
 *
 * @param string $message Notice message.
 * @param string $type    Notice type (success, error, warning, info).
 * @param bool   $dismissible Whether notice is dismissible.
 * @return void
 */
function money_quiz_admin_notice( string $message, string $type = 'info', bool $dismissible = true ): void {
	$notice_manager = money_quiz_get( 'NoticeManager' );
	
	if ( ! $notice_manager ) {
		return;
	}

	$notice_manager->add( $message, $type, $dismissible );
}

/**
 * Get Money Quiz asset URL with version.
 *
 * @since 7.0.0
 *
 * @param string $path Asset path relative to assets directory.
 * @param string $type Asset type (css, js, images).
 * @return string Asset URL with version parameter.
 */
function money_quiz_asset_url( string $path, string $type = 'js' ): string {
	$base_url = money_quiz_url( 'assets/' . $type . '/' );
	$url = $base_url . ltrim( $path, '/' );

	// Add version for cache busting.
	return add_query_arg( 'ver', money_quiz_version(), $url );
}

/**
 * Check if Money Quiz debug mode is enabled.
 *
 * @since 7.0.0
 *
 * @return bool True if debug mode is enabled.
 */
function money_quiz_is_debug(): bool {
	return money_quiz_setting( 'advanced.enable_debug_mode', false ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
}