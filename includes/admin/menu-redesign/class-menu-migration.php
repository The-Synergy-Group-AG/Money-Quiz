<?php
/**
 * Menu Migration Handler
 * 
 * Handles the migration from legacy menu to new workflow-centric design
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

namespace MoneyQuiz\Admin\MenuRedesign;

class Menu_Migration {
    
    /**
     * Initialize migration
     */
    public function init() {
        add_action( 'admin_init', [ $this, 'maybe_run_migration' ] );
        add_action( 'admin_notices', [ $this, 'show_migration_notices' ] );
        add_action( 'wp_ajax_mq_dismiss_menu_migration', [ $this, 'ajax_dismiss_notice' ] );
        add_action( 'wp_ajax_mq_complete_menu_migration', [ $this, 'ajax_complete_migration' ] );
    }
    
    /**
     * Check if migration should run
     */
    public function maybe_run_migration() {
        $migration_status = get_option( 'money_quiz_menu_migration_status', 'pending' );
        
        if ( $migration_status === 'pending' ) {
            $this->prepare_migration();
        }
    }
    
    /**
     * Prepare migration settings
     */
    private function prepare_migration() {
        // Set default migration options
        add_option( 'money_quiz_menu_redesign_enabled', false );
        add_option( 'money_quiz_menu_migration_date', current_time( 'mysql' ) );
        add_option( 'money_quiz_legacy_menu_backup', $this->backup_current_settings() );
        
        // Enable gradual rollout
        add_option( 'money_quiz_menu_rollout_percentage', 0 ); // Start with 0%, admin enables manually
        
        // Track user preferences
        add_option( 'money_quiz_menu_user_preferences', [] );
        
        // Set migration status
        update_option( 'money_quiz_menu_migration_status', 'ready' );
    }
    
    /**
     * Backup current menu settings
     */
    private function backup_current_settings() {
        global $submenu, $menu;
        
        $backup = [
            'menu_items' => [],
            'user_capabilities' => [],
            'custom_settings' => []
        ];
        
        // Backup Money Quiz menu items
        if ( isset( $submenu['mq_welcome'] ) ) {
            $backup['menu_items'] = $submenu['mq_welcome'];
        }
        
        // Backup any custom capabilities
        $backup['user_capabilities'] = [
            'manage_money_quiz' => current_user_can( 'manage_money_quiz' ),
            'edit_quizzes' => current_user_can( 'edit_quizzes' ),
            'view_quiz_results' => current_user_can( 'view_quiz_results' )
        ];
        
        // Backup plugin settings that affect menu
        $backup['custom_settings'] = [
            'show_welcome_page' => get_option( 'mq_show_welcome_page', true ),
            'menu_position' => get_option( 'mq_menu_position', 25 ),
            'menu_icon' => get_option( 'mq_menu_icon', 'dashicons-chart-pie' )
        ];
        
        return $backup;
    }
    
    /**
     * Show migration notices
     */
    public function show_migration_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $migration_status = get_option( 'money_quiz_menu_migration_status', 'pending' );
        $menu_enabled = get_option( 'money_quiz_menu_redesign_enabled', false );
        
        // Show different notices based on status
        if ( $migration_status === 'ready' && ! $menu_enabled ) {
            $this->show_ready_notice();
        } elseif ( $menu_enabled && $migration_status !== 'completed' ) {
            $this->show_active_notice();
        }
    }
    
    /**
     * Show ready for migration notice
     */
    private function show_ready_notice() {
        $dismissed = get_user_meta( get_current_user_id(), 'mq_dismissed_menu_migration_notice', true );
        if ( $dismissed ) {
            return;
        }
        ?>
        <div class="notice notice-info is-dismissible" id="mq-menu-migration-notice">
            <h2><?php _e( '🎨 New Money Quiz Menu Design Available!', 'money-quiz' ); ?></h2>
            <p><?php _e( 'We\'ve redesigned the Money Quiz menu for better organization and easier navigation. The new design groups related features together and reduces clutter.', 'money-quiz' ); ?></p>
            
            <h3><?php _e( 'What\'s New:', 'money-quiz' ); ?></h3>
            <ul style="list-style: disc; margin-left: 30px;">
                <li><?php _e( 'Organized into 5 main sections: Dashboard, Quizzes, Audience, Marketing, and Settings', 'money-quiz' ); ?></li>
                <li><?php _e( 'Color-coded sections for quick visual recognition', 'money-quiz' ); ?></li>
                <li><?php _e( 'Breadcrumb navigation to always know where you are', 'money-quiz' ); ?></li>
                <li><?php _e( 'Quick search (Ctrl/Cmd + K) to find any feature instantly', 'money-quiz' ); ?></li>
                <li><?php _e( 'Keyboard shortcuts for common actions', 'money-quiz' ); ?></li>
            </ul>
            
            <p class="mq-migration-actions">
                <button type="button" class="button button-primary" onclick="mqEnableNewMenu()">
                    <?php _e( 'Enable New Menu Design', 'money-quiz' ); ?>
                </button>
                <button type="button" class="button" onclick="mqPreviewNewMenu()">
                    <?php _e( 'Preview New Design', 'money-quiz' ); ?>
                </button>
                <button type="button" class="button" onclick="mqDismissMenuMigration()">
                    <?php _e( 'Maybe Later', 'money-quiz' ); ?>
                </button>
            </p>
            
            <script>
            function mqEnableNewMenu() {
                if (confirm('<?php _e( 'Enable the new menu design? You can switch back at any time from the settings.', 'money-quiz' ); ?>')) {
                    jQuery.post(ajaxurl, {
                        action: 'mq_complete_menu_migration',
                        enable: true,
                        _wpnonce: '<?php echo wp_create_nonce( 'mq_menu_migration' ); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                }
            }
            
            function mqPreviewNewMenu() {
                window.open('<?php echo admin_url( 'admin.php?page=money-quiz&preview_menu=1' ); ?>', '_blank');
            }
            
            function mqDismissMenuMigration() {
                jQuery.post(ajaxurl, {
                    action: 'mq_dismiss_menu_migration',
                    _wpnonce: '<?php echo wp_create_nonce( 'mq_menu_migration' ); ?>'
                });
                jQuery('#mq-menu-migration-notice').fadeOut();
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * Show active migration notice
     */
    private function show_active_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php _e( 'New Menu Design Active!', 'money-quiz' ); ?></strong>
                <?php _e( 'The new Money Quiz menu is now active. If you need help finding something, use Ctrl/Cmd + K to search.', 'money-quiz' ); ?>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-settings-general#menu-options' ); ?>">
                    <?php _e( 'Menu Settings', 'money-quiz' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * AJAX: Dismiss notice
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'mq_menu_migration' );
        
        if ( current_user_can( 'manage_options' ) ) {
            update_user_meta( get_current_user_id(), 'mq_dismissed_menu_migration_notice', true );
            wp_send_json_success();
        }
        
        wp_send_json_error( 'Insufficient permissions' );
    }
    
    /**
     * AJAX: Complete migration
     */
    public function ajax_complete_migration() {
        check_ajax_referer( 'mq_menu_migration' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $enable = ! empty( $_POST['enable'] );
        
        if ( $enable ) {
            update_option( 'money_quiz_menu_redesign_enabled', true );
            update_option( 'money_quiz_menu_migration_status', 'active' );
            update_option( 'money_quiz_menu_rollout_percentage', 100 );
            
            // Log migration completion
            $this->log_migration_event( 'enabled', [
                'user_id' => get_current_user_id(),
                'timestamp' => current_time( 'mysql' )
            ] );
            
            wp_send_json_success( [
                'message' => __( 'New menu design enabled successfully!', 'money-quiz' ),
                'redirect' => admin_url( 'admin.php?page=money-quiz-dashboard-overview' )
            ] );
        } else {
            update_option( 'money_quiz_menu_redesign_enabled', false );
            wp_send_json_success( [
                'message' => __( 'Menu design disabled.', 'money-quiz' )
            ] );
        }
    }
    
    /**
     * Log migration events
     */
    private function log_migration_event( $event, $data = [] ) {
        $log = get_option( 'money_quiz_menu_migration_log', [] );
        
        $log[] = [
            'event' => $event,
            'data' => $data,
            'timestamp' => current_time( 'mysql' )
        ];
        
        // Keep only last 100 events
        if ( count( $log ) > 100 ) {
            $log = array_slice( $log, -100 );
        }
        
        update_option( 'money_quiz_menu_migration_log', $log );
    }
    
    /**
     * Check if user should see new menu
     */
    public function should_use_new_menu( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        // Check if globally enabled
        if ( ! get_option( 'money_quiz_menu_redesign_enabled', false ) ) {
            return false;
        }
        
        // Check user preference
        $user_preferences = get_option( 'money_quiz_menu_user_preferences', [] );
        if ( isset( $user_preferences[ $user_id ] ) ) {
            return $user_preferences[ $user_id ]['use_new_menu'] ?? true;
        }
        
        // Check rollout percentage
        $rollout = get_option( 'money_quiz_menu_rollout_percentage', 0 );
        if ( $rollout < 100 ) {
            $user_hash = crc32( $user_id . 'money_quiz_menu' );
            $user_percentage = $user_hash % 100;
            return $user_percentage < $rollout;
        }
        
        return true;
    }
    
    /**
     * Rollback to legacy menu
     */
    public function rollback_menu() {
        update_option( 'money_quiz_menu_redesign_enabled', false );
        update_option( 'money_quiz_menu_migration_status', 'rolled_back' );
        
        $this->log_migration_event( 'rolled_back', [
            'user_id' => get_current_user_id(),
            'reason' => $_POST['reason'] ?? 'user_request'
        ] );
        
        return true;
    }
}

// Initialize migration handler
add_action( 'init', function() {
    $migration = new Menu_Migration();
    $migration->init();
} );