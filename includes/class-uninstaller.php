<?php
/**
 * Money Quiz Uninstaller
 * 
 * Handles complete plugin removal with data preservation option
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Uninstaller Class
 */
class Money_Quiz_Uninstaller {
    
    /**
     * Uninstall the plugin
     */
    public static function uninstall() {
        // Check if we should preserve data
        $preserve_data = get_option( 'money_quiz_preserve_data_on_uninstall', false );
        
        if ( $preserve_data ) {
            // Only remove plugin files, keep data
            self::light_cleanup();
        } else {
            // Complete removal
            self::complete_removal();
        }
        
        // Always remove these regardless of preserve setting
        self::remove_capabilities();
        self::clear_scheduled_events();
        self::clear_cache();
    }
    
    /**
     * Light cleanup - preserves user data
     */
    private static function light_cleanup() {
        // Remove only plugin-specific options
        $plugin_options = array(
            'money_quiz_safe_mode',
            'money_quiz_safe_mode_strict',
            'money_quiz_quarantine_override',
            'money_quiz_log_security_events',
            'money_quiz_block_dangerous_queries',
            'money_quiz_sanitize_inputs',
            'money_quiz_enable_monitoring',
            'money_quiz_enable_auto_updates',
            'money_quiz_cache_lifetime',
            'money_quiz_mode',
        );
        
        foreach ( $plugin_options as $option ) {
            delete_option( $option );
        }
        
        // Remove transients
        self::remove_transients();
        
        // Log the uninstall
        self::log_uninstall( 'light' );
    }
    
    /**
     * Complete removal - removes all data
     */
    private static function complete_removal() {
        global $wpdb;
        
        // Drop all plugin tables
        $tables = array(
            'mq_master',
            'mq_prospects',
            'mq_taken',
            'mq_results',
            'mq_coach',
            'mq_archetypes',
            'mq_cta',
            'mq_template_layout',
            'mq_activity_log',
            'mq_settings',
            'mq_question_screen_setting',
            'mq_email_content_setting',
            'mq_answer_label',
            'mq_register_result_setting',
            'mq_email_signature',
            'mq_quiz_result',
            'mq_recaptcha_setting',
            'mq_quiz_archive_tag_line',
        );
        
        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        }
        
        // Remove all options
        $all_options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE 'money_quiz_%' 
             OR option_name LIKE 'mq_%' 
             OR option_name LIKE 'moneyquiz_%'"
        );
        
        foreach ( $all_options as $option ) {
            delete_option( $option );
        }
        
        // Remove user meta
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'money_quiz_%' 
             OR meta_key LIKE 'mq_%'"
        );
        
        // Remove transients
        self::remove_transients();
        
        // Remove uploaded files
        self::remove_uploaded_files();
        
        // Log the uninstall
        self::log_uninstall( 'complete' );
    }
    
    /**
     * Remove capabilities
     */
    private static function remove_capabilities() {
        // Get all roles
        global $wp_roles;
        
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }
        
        // Remove custom capabilities
        $capabilities = array(
            'manage_money_quiz',
            'edit_money_quiz',
            'view_money_quiz_reports',
            'export_money_quiz_data',
        );
        
        foreach ( $wp_roles->roles as $role_name => $role_info ) {
            $role = get_role( $role_name );
            
            if ( $role ) {
                foreach ( $capabilities as $cap ) {
                    $role->remove_cap( $cap );
                }
            }
        }
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
     * Clear cache
     */
    private static function clear_cache() {
        // Clear object cache
        wp_cache_flush();
        
        // Clear any file-based cache
        $cache_dir = MONEY_QUIZ_PLUGIN_DIR . 'cache';
        if ( is_dir( $cache_dir ) ) {
            self::remove_directory( $cache_dir );
        }
        
        // Clear logs directory
        $logs_dir = MONEY_QUIZ_PLUGIN_DIR . 'logs';
        if ( is_dir( $logs_dir ) ) {
            self::remove_directory( $logs_dir );
        }
        
        // Clear temp directory
        $temp_dir = MONEY_QUIZ_PLUGIN_DIR . 'temp';
        if ( is_dir( $temp_dir ) ) {
            self::remove_directory( $temp_dir );
        }
    }
    
    /**
     * Remove transients
     */
    private static function remove_transients() {
        global $wpdb;
        
        // Delete all Money Quiz transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_money_quiz_%' 
             OR option_name LIKE '_transient_timeout_money_quiz_%' 
             OR option_name LIKE '_transient_mq_%' 
             OR option_name LIKE '_transient_timeout_mq_%'"
        );
    }
    
    /**
     * Remove uploaded files
     */
    private static function remove_uploaded_files() {
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $money_quiz_upload_dir = $upload_dir['basedir'] . '/money-quiz';
        
        if ( is_dir( $money_quiz_upload_dir ) ) {
            self::remove_directory( $money_quiz_upload_dir );
        }
    }
    
    /**
     * Remove directory recursively
     */
    private static function remove_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        
        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            
            if ( is_dir( $path ) ) {
                self::remove_directory( $path );
            } else {
                @unlink( $path );
            }
        }
        
        @rmdir( $dir );
    }
    
    /**
     * Log uninstall action
     */
    private static function log_uninstall( $type ) {
        // Try to log to error log
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                'Money Quiz uninstalled (%s removal) at %s',
                $type,
                current_time( 'mysql' )
            ) );
        }
        
        // Try to save a final log entry
        $log_file = WP_CONTENT_DIR . '/money-quiz-uninstall.log';
        $log_entry = sprintf(
            "[%s] Money Quiz uninstalled (%s removal) by user ID: %d\n",
            current_time( 'mysql' ),
            $type,
            get_current_user_id()
        );
        
        @file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
    }
}