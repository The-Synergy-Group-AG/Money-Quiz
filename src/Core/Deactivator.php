<?php
/**
 * Plugin Deactivator
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

/**
 * Fired during plugin deactivation
 * 
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Deactivator {
    
    /**
     * Execute deactivation tasks
     * 
     * @return void
     */
    public function deactivate(): void {
        // Unschedule cron events
        $this->unschedule_events();
        
        // Clear any cached data
        $this->clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        do_action( 'money_quiz_deactivated' );
    }
    
    /**
     * Unschedule cron events
     * 
     * @return void
     */
    private function unschedule_events(): void {
        $events = [
            'money_quiz_daily_cleanup',
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
     * Clear plugin cache
     * 
     * @return void
     */
    private function clear_cache(): void {
        // Clear transients
        global $wpdb;
        
        $wpdb->query( 
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_money_quiz_%',
                '_transient_timeout_money_quiz_%'
            )
        );
        
        // Clear object cache
        wp_cache_flush();
        
        // Clear any file-based cache
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/money-quiz/cache';
        
        if ( is_dir( $cache_dir ) ) {
            $this->clear_directory( $cache_dir );
        }
    }
    
    /**
     * Clear a directory
     * 
     * @param string $dir Directory path
     * @return void
     */
    private function clear_directory( string $dir ): void {
        $files = glob( $dir . '/*' );
        
        foreach ( $files as $file ) {
            if ( is_file( $file ) && basename( $file ) !== 'index.php' ) {
                unlink( $file );
            }
        }
    }
}