<?php
/**
 * Safe Mode Deactivator
 * 
 * Handles plugin deactivation in safe mode
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Safe Deactivator Class
 */
class Money_Quiz_Safe_Deactivator {
    
    /**
     * Deactivate the plugin in safe mode
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Clear transients
        self::clear_transients();
        
        // Log deactivation
        self::log_deactivation();
        
        // Clean up temporary files
        self::cleanup_temp_files();
        
        // Update activation count
        $count = get_option( 'money_quiz_activation_count', 0 );
        update_option( 'money_quiz_activation_count', $count + 1 );
        
        // Clear caches
        wp_cache_flush();
    }
    
    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        $events = array(
            'money_quiz_daily_safety_check',
            'money_quiz_hourly_monitoring',
            'money_quiz_weekly_cleanup',
            'money_quiz_safety_check',
            'money_quiz_daily_maintenance',
            'money_quiz_cleanup_old_data',
        );
        
        foreach ( $events as $event ) {
            wp_clear_scheduled_hook( $event );
        }
    }
    
    /**
     * Clear transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete Money Quiz specific transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_money_quiz_%' 
             OR option_name LIKE '_transient_timeout_money_quiz_%'"
        );
        
        // Specific transients to delete
        $transients = array(
            'money_quiz_notices',
            'money_quiz_dependency_check',
            'money_quiz_safety_check',
            'money_quiz_cache_version',
        );
        
        foreach ( $transients as $transient ) {
            delete_transient( $transient );
        }
    }
    
    /**
     * Log deactivation
     */
    private static function log_deactivation() {
        // Get current user
        $current_user = wp_get_current_user();
        
        // Log entry
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'action' => 'plugin_deactivated',
            'user_id' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
        );
        
        // Get existing log
        $deactivation_log = get_option( 'money_quiz_deactivation_log', array() );
        
        // Add new entry
        $deactivation_log[] = $log_entry;
        
        // Keep only last 50 entries
        if ( count( $deactivation_log ) > 50 ) {
            $deactivation_log = array_slice( $deactivation_log, -50 );
        }
        
        // Save log
        update_option( 'money_quiz_deactivation_log', $deactivation_log );
        
        // Also log to error log if debug is enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Money Quiz deactivated by user: ' . $current_user->user_login );
        }
    }
    
    /**
     * Clean up temporary files
     */
    private static function cleanup_temp_files() {
        $temp_dirs = array(
            MONEY_QUIZ_PLUGIN_DIR . 'temp',
            MONEY_QUIZ_PLUGIN_DIR . 'cache',
        );
        
        foreach ( $temp_dirs as $dir ) {
            if ( is_dir( $dir ) ) {
                // Get all files in directory
                $files = glob( $dir . '/*' );
                
                foreach ( $files as $file ) {
                    // Skip .htaccess and index.php
                    if ( basename( $file ) === '.htaccess' || basename( $file ) === 'index.php' ) {
                        continue;
                    }
                    
                    // Delete files older than 24 hours
                    if ( is_file( $file ) && time() - filemtime( $file ) > 86400 ) {
                        @unlink( $file );
                    }
                }
            }
        }
    }
}