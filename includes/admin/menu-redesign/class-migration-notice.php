<?php
/**
 * Migration Notice System
 * 
 * Handles user notifications about menu changes and provides guidance
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MoneyQuiz_Migration_Notice {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Notice types
     */
    const NOTICE_REDIRECT = 'menu_redirect';
    const NOTICE_NEW_FEATURE = 'new_feature';
    const NOTICE_ONBOARDING = 'menu_onboarding';
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_notices', [ $this, 'display_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mq_dismiss_migration_notice', [ $this, 'ajax_dismiss_notice' ] );
        add_action( 'wp_ajax_mq_complete_onboarding', [ $this, 'ajax_complete_onboarding' ] );
        
        // Check for redirects
        add_action( 'admin_init', [ $this, 'check_redirect_notice' ] );
    }
    
    /**
     * Check if user was redirected and should see notice
     */
    public function check_redirect_notice() {
        if ( isset( $_GET['mq_redirected'] ) && $_GET['mq_redirected'] === '1' ) {
            $this->add_notice( self::NOTICE_REDIRECT, [
                'from' => sanitize_text_field( $_GET['from'] ?? '' ),
                'to' => sanitize_text_field( $_GET['to'] ?? '' )
            ] );
        }
    }
    
    /**
     * Add a notice for the current user
     */
    public function add_notice( $type, $data = [] ) {
        $user_id = get_current_user_id();
        $notices = get_user_meta( $user_id, 'mq_migration_notices', true );
        
        if ( ! is_array( $notices ) ) {
            $notices = [];
        }
        
        $notices[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
            'dismissed' => false
        ];
        
        update_user_meta( $user_id, 'mq_migration_notices', $notices );
    }
    
    /**
     * Display notices
     */
    public function display_notices() {
        $user_id = get_current_user_id();
        $notices = get_user_meta( $user_id, 'mq_migration_notices', true );
        
        if ( ! is_array( $notices ) || empty( $notices ) ) {
            return;
        }
        
        // Check if user should see onboarding
        if ( $this->should_show_onboarding() ) {
            $this->display_onboarding_notice();
            return;
        }
        
        // Display other notices
        foreach ( $notices as $index => $notice ) {
            if ( ! $notice['dismissed'] ) {
                $this->display_single_notice( $notice, $index );
            }
        }
    }
    
    /**
     * Display single notice
     */
    private function display_single_notice( $notice, $index ) {
        switch ( $notice['type'] ) {
            case self::NOTICE_REDIRECT:
                $this->display_redirect_notice( $notice['data'], $index );
                break;
                
            case self::NOTICE_NEW_FEATURE:
                $this->display_feature_notice( $notice['data'], $index );
                break;
        }
    }
    
    /**
     * Display redirect notice
     */
    private function display_redirect_notice( $data, $index ) {
        $old_page = $this->get_page_name( $data['from'] );
        $new_page = $this->get_page_name( $data['to'] );
        ?>
        <div class="notice notice-info is-dismissible mq-migration-notice" data-notice-index="<?php echo $index; ?>">
            <div class="mq-notice-content">
                <h3><?php _e( '📍 Menu Location Changed', 'money-quiz' ); ?></h3>
                <p>
                    <?php 
                    printf( 
                        __( 'The page "%s" has moved to a new location: <strong>%s</strong>', 'money-quiz' ),
                        esc_html( $old_page ),
                        esc_html( $new_page )
                    ); 
                    ?>
                </p>
                <p>
                    <?php _e( 'The Money Quiz menu has been reorganized for better workflow and easier navigation.', 'money-quiz' ); ?>
                    <a href="#" class="mq-show-menu-guide"><?php _e( 'View new menu structure', 'money-quiz' ); ?></a>
                </p>
                <div class="mq-notice-actions">
                    <button type="button" class="button button-primary mq-dismiss-notice">
                        <?php _e( 'Got it!', 'money-quiz' ); ?>
                    </button>
                    <button type="button" class="button mq-start-tour">
                        <?php _e( 'Take a quick tour', 'money-quiz' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display feature notice
     */
    private function display_feature_notice( $data, $index ) {
        ?>
        <div class="notice notice-success is-dismissible mq-migration-notice" data-notice-index="<?php echo $index; ?>">
            <div class="mq-notice-content">
                <h3><?php _e( '✨ New Features Available', 'money-quiz' ); ?></h3>
                <p><?php echo esc_html( $data['message'] ); ?></p>
                <?php if ( ! empty( $data['features'] ) ) : ?>
                    <ul>
                        <?php foreach ( $data['features'] as $feature ) : ?>
                            <li><?php echo esc_html( $feature ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="mq-notice-actions">
                    <a href="<?php echo esc_url( $data['learn_more_url'] ?? '#' ); ?>" class="button button-primary">
                        <?php _e( 'Learn More', 'money-quiz' ); ?>
                    </a>
                    <button type="button" class="button mq-dismiss-notice">
                        <?php _e( 'Dismiss', 'money-quiz' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display onboarding notice
     */
    private function display_onboarding_notice() {
        ?>
        <div class="notice notice-info mq-onboarding-notice">
            <div class="mq-onboarding-header">
                <h2><?php _e( '🎉 Welcome to the New Money Quiz Menu!', 'money-quiz' ); ?></h2>
                <p><?php _e( "We've reorganized the menu to make your workflow smoother and more intuitive.", 'money-quiz' ); ?></p>
            </div>
            
            <div class="mq-onboarding-content">
                <div class="mq-menu-sections">
                    <div class="mq-section">
                        <div class="mq-section-icon">📊</div>
                        <h3><?php _e( 'Dashboard', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'Quick overview, stats, and recent activity', 'money-quiz' ); ?></p>
                    </div>
                    
                    <div class="mq-section">
                        <div class="mq-section-icon">📋</div>
                        <h3><?php _e( 'Quizzes', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'Create and manage quizzes, questions, and archetypes', 'money-quiz' ); ?></p>
                    </div>
                    
                    <div class="mq-section">
                        <div class="mq-section-icon">👥</div>
                        <h3><?php _e( 'Audience', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'View results, manage leads, and send campaigns', 'money-quiz' ); ?></p>
                    </div>
                    
                    <div class="mq-section">
                        <div class="mq-section-icon">📢</div>
                        <h3><?php _e( 'Marketing', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'CTAs, pop-ups, and conversion tools', 'money-quiz' ); ?></p>
                    </div>
                    
                    <div class="mq-section">
                        <div class="mq-section-icon">⚙️</div>
                        <h3><?php _e( 'Settings', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'Configuration, integrations, and email setup', 'money-quiz' ); ?></p>
                    </div>
                </div>
                
                <div class="mq-onboarding-features">
                    <h3><?php _e( 'New Features:', 'money-quiz' ); ?></h3>
                    <ul>
                        <li><?php _e( '🔍 Global search - Press Ctrl/Cmd + K', 'money-quiz' ); ?></li>
                        <li><?php _e( '⚡ Quick actions throughout the interface', 'money-quiz' ); ?></li>
                        <li><?php _e( '📈 Enhanced dashboard with real-time stats', 'money-quiz' ); ?></li>
                        <li><?php _e( '🎨 Improved UI with better organization', 'money-quiz' ); ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="mq-onboarding-actions">
                <button type="button" class="button button-primary button-hero mq-start-tour">
                    <?php _e( 'Take a Quick Tour', 'money-quiz' ); ?>
                </button>
                <button type="button" class="button button-hero mq-complete-onboarding">
                    <?php _e( 'I\'ll Explore on My Own', 'money-quiz' ); ?>
                </button>
                <br>
                <label style="margin-top: 10px; display: inline-block;">
                    <input type="checkbox" id="mq-disable-old-menu" />
                    <?php _e( 'Hide the old menu items', 'money-quiz' ); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if should show onboarding
     */
    private function should_show_onboarding() {
        $user_id = get_current_user_id();
        $completed = get_user_meta( $user_id, 'mq_menu_onboarding_completed', true );
        
        if ( $completed ) {
            return false;
        }
        
        // Show if menu was just enabled
        $menu_enabled = get_option( 'money_quiz_menu_redesign_enabled' );
        $first_enable_time = get_option( 'money_quiz_menu_first_enabled_time' );
        
        if ( $menu_enabled && $first_enable_time ) {
            // Show onboarding within first 7 days of enabling
            return ( time() - $first_enable_time ) < ( 7 * DAY_IN_SECONDS );
        }
        
        return false;
    }
    
    /**
     * Get page name from slug
     */
    private function get_page_name( $slug ) {
        $pages = [
            'money_quiz' => __( 'Dashboard', 'money-quiz' ),
            'money_quiz_question' => __( 'Questions', 'money-quiz' ),
            'money_quiz_archetypes' => __( 'Archetypes', 'money-quiz' ),
            'money_quiz_leads' => __( 'Leads', 'money-quiz' ),
            'money_quiz_results' => __( 'Results', 'money-quiz' ),
            'money_quiz_settings' => __( 'Settings', 'money-quiz' ),
            'money_quiz_integration' => __( 'Integrations', 'money-quiz' ),
            // Add more mappings as needed
        ];
        
        return $pages[ $slug ] ?? $slug;
    }
    
    /**
     * AJAX handler to dismiss notice
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'mq_admin_nonce', 'nonce' );
        
        $index = intval( $_POST['index'] );
        $user_id = get_current_user_id();
        
        $notices = get_user_meta( $user_id, 'mq_migration_notices', true );
        
        if ( is_array( $notices ) && isset( $notices[ $index ] ) ) {
            $notices[ $index ]['dismissed'] = true;
            update_user_meta( $user_id, 'mq_migration_notices', $notices );
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler to complete onboarding
     */
    public function ajax_complete_onboarding() {
        check_ajax_referer( 'mq_admin_nonce', 'nonce' );
        
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'mq_menu_onboarding_completed', time() );
        
        // Hide old menu if requested
        if ( isset( $_POST['hide_old_menu'] ) && $_POST['hide_old_menu'] === 'true' ) {
            update_user_meta( $user_id, 'mq_hide_legacy_menu', true );
        }
        
        wp_send_json_success();
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        wp_enqueue_style( 
            'mq-migration-notice', 
            MONEY_QUIZ_PLUGIN_URL . 'assets/css/migration-notice.css',
            [],
            MONEY_QUIZ_VERSION
        );
        
        wp_enqueue_script(
            'mq-migration-notice',
            MONEY_QUIZ_PLUGIN_URL . 'assets/js/migration-notice.js',
            ['jquery'],
            MONEY_QUIZ_VERSION,
            true
        );
        
        wp_localize_script( 'mq-migration-notice', 'mq_migration', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mq_admin_nonce' ),
            'tour_url' => admin_url( 'admin.php?page=money-quiz-tour' )
        ] );
    }
    
    /**
     * Show feature announcement
     */
    public function announce_new_feature( $feature_name, $features = [] ) {
        $this->add_notice( self::NOTICE_NEW_FEATURE, [
            'message' => sprintf( __( 'New in Money Quiz: %s', 'money-quiz' ), $feature_name ),
            'features' => $features,
            'learn_more_url' => admin_url( 'admin.php?page=money-quiz-whats-new' )
        ] );
    }
}

// Initialize
MoneyQuiz_Migration_Notice::get_instance();