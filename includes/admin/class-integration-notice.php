<?php
/**
 * Integration Notice Handler
 * 
 * Shows helpful admin notices for integration setup
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Admin;

class Integration_Notice {
    
    /**
     * Initialize notices
     */
    public function init() {
        add_action( 'admin_notices', [ $this, 'show_setup_notice' ] );
        add_action( 'wp_ajax_mq_dismiss_integration_notice', [ $this, 'ajax_dismiss_notice' ] );
    }
    
    /**
     * Show setup notice
     */
    public function show_setup_notice() {
        // Only show to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Check if already dismissed
        if ( get_option( 'money_quiz_integration_notice_dismissed' ) ) {
            return;
        }
        
        // Check if integration is already configured
        $rollout = get_option( 'money_quiz_modern_rollout', 0 );
        if ( $rollout > 0 ) {
            return;
        }
        
        // Only show on Money Quiz pages or dashboard
        $screen = get_current_screen();
        if ( ! $screen || ( 
            strpos( $screen->id, 'moneyquiz' ) === false && 
            $screen->id !== 'dashboard' 
        ) ) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible" id="mq-integration-notice">
            <h2><?php _e( '🚀 Money Quiz Security Integration Ready!', 'money-quiz' ); ?></h2>
            <p>
                <?php _e( 'The Money Quiz plugin now includes advanced security features and performance improvements. These are currently inactive and need to be enabled.', 'money-quiz' ); ?>
            </p>
            
            <h3><?php _e( 'What\'s New:', 'money-quiz' ); ?></h3>
            <ul style="list-style: disc; margin-left: 30px;">
                <li><?php _e( 'SQL injection protection for all database queries', 'money-quiz' ); ?></li>
                <li><?php _e( 'Automatic input sanitization and XSS prevention', 'money-quiz' ); ?></li>
                <li><?php _e( 'CSRF token protection on all forms', 'money-quiz' ); ?></li>
                <li><?php _e( 'Enhanced error logging and monitoring', 'money-quiz' ); ?></li>
                <li><?php _e( 'Performance optimization and caching', 'money-quiz' ); ?></li>
            </ul>
            
            <h3><?php _e( 'Quick Setup (3 Steps):', 'money-quiz' ); ?></h3>
            <ol style="list-style: decimal; margin-left: 30px;">
                <li>
                    <strong><?php _e( 'Enable Error Logging', 'money-quiz' ); ?></strong><br>
                    <?php _e( 'Add to your wp-config.php:', 'money-quiz' ); ?>
                    <pre style="background: #f0f0f0; padding: 10px; margin: 10px 0;">define( 'MONEY_QUIZ_ERROR_LOGGING', true );</pre>
                </li>
                <li>
                    <strong><?php _e( 'Configure Integration', 'money-quiz' ); ?></strong><br>
                    <?php _e( 'Visit the new Integration settings page to enable features.', 'money-quiz' ); ?>
                </li>
                <li>
                    <strong><?php _e( 'Test Your Site', 'money-quiz' ); ?></strong><br>
                    <?php _e( 'The system starts with only 10% of requests using new features for safety.', 'money-quiz' ); ?>
                </li>
            </ol>
            
            <p class="submit">
                <a href="<?php echo admin_url( 'admin.php?page=moneyquiz-integration' ); ?>" class="button button-primary">
                    <?php _e( 'Configure Integration Now', 'money-quiz' ); ?>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=moneyquiz-integration#help' ); ?>" class="button">
                    <?php _e( 'View Documentation', 'money-quiz' ); ?>
                </a>
                <button type="button" class="button" onclick="mqDismissIntegrationNotice()">
                    <?php _e( 'Dismiss', 'money-quiz' ); ?>
                </button>
            </p>
            
            <script>
            function mqDismissIntegrationNotice() {
                jQuery.post(ajaxurl, {
                    action: 'mq_dismiss_integration_notice',
                    _wpnonce: '<?php echo wp_create_nonce( 'mq_dismiss_notice' ); ?>'
                });
                jQuery('#mq-integration-notice').fadeOut();
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX: Dismiss notice
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'mq_dismiss_notice' );
        
        if ( current_user_can( 'manage_options' ) ) {
            update_option( 'money_quiz_integration_notice_dismissed', true );
        }
        
        wp_die();
    }
}

// Initialize
add_action( 'init', function() {
    $notice = new Integration_Notice();
    $notice->init();
} );