<?php
/**
 * Plugin Activator
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

use MoneyQuiz\Database\Migrator;

/**
 * Fired during plugin activation
 * 
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {
    
    /**
     * @var Migrator Database migrator
     */
    private Migrator $migrator;
    
    /**
     * Constructor
     * 
     * @param Migrator $migrator Database migrator
     */
    public function __construct( Migrator $migrator ) {
        $this->migrator = $migrator;
    }
    
    /**
     * Execute activation tasks
     * 
     * @return void
     */
    public function activate(): void {
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
            wp_die( 
                esc_html__( 'Money Quiz requires PHP 7.4 or higher.', 'money-quiz' ),
                esc_html__( 'Plugin Activation Error', 'money-quiz' ),
                [ 'back_link' => true ]
            );
        }
        
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
            wp_die( 
                esc_html__( 'Money Quiz requires WordPress 5.8 or higher.', 'money-quiz' ),
                esc_html__( 'Plugin Activation Error', 'money-quiz' ),
                [ 'back_link' => true ]
            );
        }
        
        // Run database migrations
        $this->migrator->migrate();
        
        // Create necessary directories
        $this->create_directories();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        $this->schedule_events();
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        do_action( 'money_quiz_activated' );
    }
    
    /**
     * Create necessary directories
     * 
     * @return void
     */
    private function create_directories(): void {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/money-quiz';
        
        $directories = [
            $plugin_upload_dir,
            $plugin_upload_dir . '/cache',
            $plugin_upload_dir . '/logs',
            $plugin_upload_dir . '/temp',
        ];
        
        foreach ( $directories as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
                
                // Add index.php for security
                $index_file = $dir . '/index.php';
                if ( ! file_exists( $index_file ) ) {
                    file_put_contents( $index_file, '<?php // Silence is golden' );
                }
                
                // Add .htaccess for extra security
                $htaccess_file = $dir . '/.htaccess';
                if ( ! file_exists( $htaccess_file ) ) {
                    file_put_contents( $htaccess_file, 'deny from all' );
                }
            }
        }
    }
    
    /**
     * Set default plugin options
     * 
     * @return void
     */
    private function set_default_options(): void {
        $defaults = [
            'money_quiz_version' => MONEY_QUIZ_VERSION,
            'money_quiz_db_version' => $this->migrator->get_current_version(),
            'money_quiz_activated' => current_time( 'mysql' ),
            'money_quiz_settings' => [
                'email_notifications' => true,
                'save_quiz_results' => true,
                'enable_recaptcha' => false,
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
                'quiz_time_limit' => 0,
                'results_expiry_days' => 90,
                'enable_debug_mode' => false,
            ],
            'money_quiz_email_settings' => [
                'from_name' => get_bloginfo( 'name' ),
                'from_email' => get_option( 'admin_email' ),
                'admin_notification' => true,
                'user_notification' => true,
                'notification_subject' => __( 'Your Money Quiz Results', 'money-quiz' ),
            ],
        ];
        
        foreach ( $defaults as $option => $value ) {
            if ( false === get_option( $option ) ) {
                add_option( $option, $value );
            }
        }
    }
    
    /**
     * Schedule cron events
     * 
     * @return void
     */
    private function schedule_events(): void {
        // Schedule daily cleanup
        if ( ! wp_next_scheduled( 'money_quiz_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_daily_cleanup' );
        }
        
        // Schedule weekly report
        if ( ! wp_next_scheduled( 'money_quiz_weekly_report' ) ) {
            wp_schedule_event( time(), 'weekly', 'money_quiz_weekly_report' );
        }
    }
}