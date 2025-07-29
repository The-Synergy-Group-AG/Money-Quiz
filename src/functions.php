<?php
/**
 * Global helper functions
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

use MoneyQuiz\Core\Plugin;

/**
 * Get the main plugin instance
 * 
 * @return Plugin
 */
function money_quiz(): Plugin {
    return Plugin::instance();
}

/**
 * Get a service from the container
 * 
 * @param string $service Service identifier
 * @return mixed
 */
function money_quiz_get( string $service ) {
    return money_quiz()->get( $service );
}

/**
 * Get plugin version
 * 
 * @return string
 */
function money_quiz_version(): string {
    return money_quiz()->get_version();
}

/**
 * Get plugin URL
 * 
 * @param string $path Optional path to append
 * @return string
 */
function money_quiz_url( string $path = '' ): string {
    $url = MONEY_QUIZ_PLUGIN_URL;
    
    if ( $path ) {
        $url = trailingslashit( $url ) . ltrim( $path, '/' );
    }
    
    return $url;
}

/**
 * Get plugin directory path
 * 
 * @param string $path Optional path to append
 * @return string
 */
function money_quiz_path( string $path = '' ): string {
    $dir = MONEY_QUIZ_PLUGIN_DIR;
    
    if ( $path ) {
        $dir = trailingslashit( $dir ) . ltrim( $path, '/' );
    }
    
    return $dir;
}

/**
 * Get template path
 * 
 * @param string $template Template name
 * @return string
 */
function money_quiz_template_path( string $template ): string {
    return money_quiz_path( 'templates/' . $template );
}

/**
 * Load a template
 * 
 * @param string $template Template name
 * @param array  $args     Arguments to pass to template
 * @return void
 */
function money_quiz_load_template( string $template, array $args = [] ): void {
    $template_path = money_quiz_template_path( $template );
    
    if ( file_exists( $template_path ) ) {
        extract( $args );
        include $template_path;
    }
}

/**
 * Get a template as string
 * 
 * @param string $template Template name
 * @param array  $args     Arguments to pass to template
 * @return string
 */
function money_quiz_get_template( string $template, array $args = [] ): string {
    ob_start();
    money_quiz_load_template( $template, $args );
    return ob_get_clean();
}

/**
 * Log a message
 * 
 * @param string $message Message to log
 * @param string $level   Log level (debug, info, warning, error)
 * @return void
 */
function money_quiz_log( string $message, string $level = 'info' ): void {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $logger = money_quiz_get( 'service.logger' );
        $logger->log( $message, $level );
    }
}

/**
 * Check if Money Quiz is in debug mode
 * 
 * @return bool
 */
function money_quiz_debug(): bool {
    $settings = get_option( 'money_quiz_settings', [] );
    return ! empty( $settings['enable_debug_mode'] ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
}