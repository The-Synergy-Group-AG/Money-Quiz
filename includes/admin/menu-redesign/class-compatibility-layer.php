<?php
/**
 * Menu Redesign Compatibility Layer
 * 
 * Ensures backward compatibility and safe operation of the new menu system
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

namespace MoneyQuiz\Admin\MenuRedesign;

class Compatibility_Layer {
    
    /**
     * Initialize compatibility features
     */
    public function init() {
        // Add compatibility hooks
        add_action( 'admin_init', [ $this, 'verify_critical_functionality' ] );
        add_action( 'admin_init', [ $this, 'setup_legacy_aliases' ] );
        add_filter( 'money_quiz_menu_enabled', [ $this, 'check_safe_to_enable' ] );
        
        // Emergency disable
        add_action( 'admin_init', [ $this, 'check_emergency_disable' ] );
        
        // Monitoring
        add_action( 'admin_init', [ $this, 'monitor_menu_access' ] );
        add_action( 'wp_loaded', [ $this, 'verify_quiz_functionality' ] );
    }
    
    /**
     * Verify critical functionality is working
     */
    public function verify_critical_functionality() {
        // Check if we're on a Money Quiz page
        if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'money-quiz' ) === false ) {
            return;
        }
        
        global $wpdb;
        $critical_checks = [];
        
        // Check database tables exist
        $required_tables = [
            'mq_master',
            'mq_questions', 
            'mq_archetypes',
            'mq_prospects',
            'mq_taken'
        ];
        
        foreach ( $required_tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
            $critical_checks['table_' . $table] = $exists;
            
            if ( ! $exists ) {
                $this->log_critical_error( 'Missing database table: ' . $table );
            }
        }
        
        // Check if quiz submission endpoint is accessible
        $critical_checks['quiz_endpoint'] = $this->check_quiz_endpoint();
        
        // Check if essential options exist
        $critical_options = [
            'mq_company_title',
            'mq_email_subject',
            'mq_email_sender'
        ];
        
        foreach ( $critical_options as $option ) {
            $critical_checks['option_' . $option] = get_option( $option ) !== false;
        }
        
        // If any critical check fails, disable new menu
        if ( in_array( false, $critical_checks, true ) ) {
            $this->emergency_disable( 'Critical functionality check failed' );
        }
    }
    
    /**
     * Setup legacy URL aliases
     */
    public function setup_legacy_aliases() {
        if ( ! isset( $_GET['page'] ) ) {
            return;
        }
        
        $page = sanitize_text_field( $_GET['page'] );
        
        // Map of legacy pages that might be called directly
        $direct_access_pages = [
            'mq_welcome' => 'money-quiz-dashboard-overview',
            'moneyquiz' => 'money-quiz-dashboard-overview',
            'mq_quiz' => 'money-quiz-quizzes-all',
            'mq_questions' => 'money-quiz-quizzes-questions',
            'mq_prospects' => 'money-quiz-audience-prospects'
        ];
        
        // Handle direct legacy page access
        if ( isset( $direct_access_pages[ $page ] ) && get_option( 'money_quiz_menu_redesign_enabled' ) ) {
            // Log the access for monitoring
            $this->log_legacy_access( $page );
            
            // Don't redirect - allow legacy page to load but show notice
            add_action( 'admin_notices', function() use ( $page, $direct_access_pages ) {
                $new_url = admin_url( 'admin.php?page=' . $direct_access_pages[ $page ] );
                ?>
                <div class="notice notice-warning">
                    <p>
                        <?php printf( 
                            __( 'This page has moved. Please update your bookmarks to use the new URL: <a href="%s">%s</a>', 'money-quiz' ),
                            esc_url( $new_url ),
                            esc_html( $new_url )
                        ); ?>
                    </p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Check if it's safe to enable new menu
     */
    public function check_safe_to_enable( $enabled ) {
        // Don't override if explicitly disabled
        if ( ! $enabled ) {
            return false;
        }
        
        // Check for force legacy constant
        if ( defined( 'MONEY_QUIZ_FORCE_LEGACY_MENU' ) && MONEY_QUIZ_FORCE_LEGACY_MENU ) {
            return false;
        }
        
        // Check for active quiz sessions
        if ( $this->has_active_quiz_sessions() ) {
            $this->log_safety_check( 'Active quiz sessions detected, deferring menu switch' );
            return false;
        }
        
        // Check for ongoing email campaigns
        if ( $this->has_active_campaigns() ) {
            $this->log_safety_check( 'Active email campaigns detected, deferring menu switch' );
            return false;
        }
        
        return $enabled;
    }
    
    /**
     * Check for emergency disable trigger
     */
    public function check_emergency_disable() {
        // Admin URL parameter override
        if ( current_user_can( 'manage_options' ) && isset( $_GET['mq_force_legacy_menu'] ) ) {
            $this->emergency_disable( 'Manual override via URL parameter' );
            wp_safe_redirect( remove_query_arg( 'mq_force_legacy_menu' ) );
            exit;
        }
        
        // Check error threshold
        $error_count = get_transient( 'mq_menu_error_count' );
        if ( $error_count > 10 ) {
            $this->emergency_disable( 'Error threshold exceeded' );
        }
    }
    
    /**
     * Emergency disable new menu
     */
    private function emergency_disable( $reason ) {
        update_option( 'money_quiz_menu_redesign_enabled', false );
        update_option( 'money_quiz_menu_emergency_disabled', true );
        
        // Log the event
        $this->log_critical_error( 'Emergency menu disable: ' . $reason );
        
        // Notify admin
        set_transient( 'mq_menu_emergency_notice', $reason, DAY_IN_SECONDS );
    }
    
    /**
     * Monitor menu access patterns
     */
    public function monitor_menu_access() {
        if ( ! isset( $_GET['page'] ) ) {
            return;
        }
        
        $page = sanitize_text_field( $_GET['page'] );
        
        // Track page access
        $access_log = get_option( 'mq_menu_access_log', [] );
        $today = date( 'Y-m-d' );
        
        if ( ! isset( $access_log[ $today ] ) ) {
            $access_log[ $today ] = [];
        }
        
        if ( ! isset( $access_log[ $today ][ $page ] ) ) {
            $access_log[ $today ][ $page ] = 0;
        }
        
        $access_log[ $today ][ $page ]++;
        
        // Keep only last 7 days
        $access_log = array_slice( $access_log, -7, 7, true );
        
        update_option( 'mq_menu_access_log', $access_log );
    }
    
    /**
     * Verify quiz functionality is working
     */
    public function verify_quiz_functionality() {
        // Only check on quiz pages
        if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'quiz' ) === false ) {
            return;
        }
        
        // Verify AJAX endpoints
        $ajax_actions = [
            'mq_save_quiz',
            'mq_get_quiz_data',
            'mq_submit_quiz',
            'mq_export_leads'
        ];
        
        foreach ( $ajax_actions as $action ) {
            if ( ! has_action( 'wp_ajax_' . $action ) ) {
                $this->log_critical_error( 'Missing AJAX action: ' . $action );
            }
        }
    }
    
    /**
     * Check if quiz submission endpoint is accessible
     */
    private function check_quiz_endpoint() {
        // Check if the quiz submission handler exists
        return function_exists( 'moneyquiz_quiz_submit' ) || 
               has_action( 'wp_ajax_nopriv_mq_submit_quiz' );
    }
    
    /**
     * Check for active quiz sessions
     */
    private function has_active_quiz_sessions() {
        global $wpdb;
        
        // Check for recent quiz starts (within last hour)
        $recent_starts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mq_taken 
             WHERE date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        return $recent_starts > 0;
    }
    
    /**
     * Check for active email campaigns
     */
    private function has_active_campaigns() {
        global $wpdb;
        
        // Check for campaigns in sending state
        $active_campaigns = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mq_email_campaigns 
             WHERE status = 'sending'"
        );
        
        return $active_campaigns > 0;
    }
    
    /**
     * Log legacy access
     */
    private function log_legacy_access( $page ) {
        $log = get_option( 'mq_legacy_access_log', [] );
        $log[] = [
            'page' => $page,
            'user' => get_current_user_id(),
            'time' => current_time( 'mysql' ),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        // Keep last 100 entries
        $log = array_slice( $log, -100 );
        update_option( 'mq_legacy_access_log', $log );
    }
    
    /**
     * Log safety check
     */
    private function log_safety_check( $message ) {
        $log = get_option( 'mq_safety_check_log', [] );
        $log[] = [
            'message' => $message,
            'time' => current_time( 'mysql' )
        ];
        
        // Keep last 50 entries
        $log = array_slice( $log, -50 );
        update_option( 'mq_safety_check_log', $log );
    }
    
    /**
     * Log critical errors
     */
    private function log_critical_error( $message ) {
        $log = get_option( 'money_quiz_error_log', [] );
        $log[] = [
            'type' => 'critical',
            'message' => $message,
            'time' => current_time( 'mysql' ),
            'trace' => wp_debug_backtrace_summary()
        ];
        
        update_option( 'money_quiz_error_log', $log );
        
        // Increment error counter
        $count = get_transient( 'mq_menu_error_count' ) ?: 0;
        set_transient( 'mq_menu_error_count', $count + 1, HOUR_IN_SECONDS );
        
        // Send alert if critical
        if ( $count > 5 ) {
            $this->send_admin_alert( $message );
        }
    }
    
    /**
     * Send admin alert
     */
    private function send_admin_alert( $message ) {
        $admin_email = get_option( 'admin_email' );
        $subject = __( '[Money Quiz] Critical Menu Error', 'money-quiz' );
        $body = sprintf(
            __( "A critical error has been detected in the Money Quiz menu system:\n\n%s\n\nThe menu system may have been automatically disabled for safety.", 'money-quiz' ),
            $message
        );
        
        wp_mail( $admin_email, $subject, $body );
    }
}

// Initialize compatibility layer
add_action( 'init', function() {
    if ( get_option( 'money_quiz_menu_redesign_enabled' ) || get_option( 'money_quiz_enable_menu_redesign' ) ) {
        $compatibility = new Compatibility_Layer();
        $compatibility->init();
    }
});