<?php
/**
 * Safe Mode Configuration for Menu Testing
 * 
 * Provides a safe environment for testing the new menu without affecting production
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

namespace MoneyQuiz\Admin\MenuRedesign;

class Safe_Mode_Config {
    
    /**
     * Enable safe testing mode
     */
    public static function enable_safe_mode() {
        // Create test mode flag
        update_option( 'money_quiz_menu_test_mode', true );
        
        // Set conservative limits
        update_option( 'money_quiz_menu_test_config', [
            'enabled_for_roles' => ['administrator'], // Only admins can see new menu
            'enabled_users' => [ get_current_user_id() ], // Start with current user only
            'fallback_enabled' => true, // Always allow fallback to legacy
            'error_threshold' => 3, // Lower error threshold for safety
            'auto_disable_on_error' => true,
            'test_start_time' => current_time( 'mysql' ),
            'test_duration_hours' => 24, // Auto-disable after 24 hours
            'preserve_legacy_urls' => true,
            'log_all_access' => true
        ]);
        
        // Enable enhanced logging
        update_option( 'money_quiz_menu_debug_mode', true );
        
        // Clear any previous errors
        delete_transient( 'mq_menu_error_count' );
        delete_option( 'money_quiz_menu_emergency_disabled' );
        
        return true;
    }
    
    /**
     * Disable safe mode and restore normal operation
     */
    public static function disable_safe_mode() {
        delete_option( 'money_quiz_menu_test_mode' );
        delete_option( 'money_quiz_menu_test_config' );
        delete_option( 'money_quiz_menu_debug_mode' );
        
        return true;
    }
    
    /**
     * Check if in safe mode
     */
    public static function is_safe_mode() {
        if ( ! get_option( 'money_quiz_menu_test_mode' ) ) {
            return false;
        }
        
        $config = get_option( 'money_quiz_menu_test_config', [] );
        
        // Check if test period has expired
        if ( isset( $config['test_start_time'] ) && isset( $config['test_duration_hours'] ) ) {
            $test_end = strtotime( $config['test_start_time'] ) + ( $config['test_duration_hours'] * HOUR_IN_SECONDS );
            if ( time() > $test_end ) {
                self::disable_safe_mode();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if current user can use new menu in safe mode
     */
    public static function user_can_use_new_menu() {
        if ( ! self::is_safe_mode() ) {
            return get_option( 'money_quiz_menu_redesign_enabled', false );
        }
        
        $config = get_option( 'money_quiz_menu_test_config', [] );
        $user = wp_get_current_user();
        
        // Check role-based access
        if ( isset( $config['enabled_for_roles'] ) ) {
            $user_roles = (array) $user->roles;
            $allowed_roles = (array) $config['enabled_for_roles'];
            
            if ( ! array_intersect( $user_roles, $allowed_roles ) ) {
                return false;
            }
        }
        
        // Check specific user access
        if ( isset( $config['enabled_users'] ) ) {
            if ( ! in_array( $user->ID, $config['enabled_users'] ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Add user to safe mode testing
     */
    public static function add_test_user( $user_id ) {
        $config = get_option( 'money_quiz_menu_test_config', [] );
        
        if ( ! isset( $config['enabled_users'] ) ) {
            $config['enabled_users'] = [];
        }
        
        if ( ! in_array( $user_id, $config['enabled_users'] ) ) {
            $config['enabled_users'][] = $user_id;
            update_option( 'money_quiz_menu_test_config', $config );
        }
        
        return true;
    }
    
    /**
     * Get safe mode status
     */
    public static function get_status() {
        if ( ! self::is_safe_mode() ) {
            return [
                'active' => false,
                'message' => __( 'Safe mode is not active', 'money-quiz' )
            ];
        }
        
        $config = get_option( 'money_quiz_menu_test_config', [] );
        $test_start = strtotime( $config['test_start_time'] );
        $test_end = $test_start + ( $config['test_duration_hours'] * HOUR_IN_SECONDS );
        $time_remaining = $test_end - time();
        
        return [
            'active' => true,
            'config' => $config,
            'time_remaining' => $time_remaining,
            'hours_remaining' => round( $time_remaining / HOUR_IN_SECONDS, 1 ),
            'test_users' => count( $config['enabled_users'] ?? [] ),
            'errors_logged' => get_transient( 'mq_menu_error_count' ) ?: 0
        ];
    }
    
    /**
     * Create pre-launch checklist
     */
    public static function get_prelaunch_checklist() {
        $checklist = [];
        
        // 1. Database backup
        $last_backup = get_option( 'money_quiz_last_backup_date' );
        $checklist['database_backup'] = [
            'label' => __( 'Database backup created', 'money-quiz' ),
            'status' => $last_backup && ( time() - strtotime( $last_backup ) < DAY_IN_SECONDS ),
            'action' => __( 'Create a full database backup before proceeding', 'money-quiz' )
        ];
        
        // 2. Test mode enabled
        $checklist['test_mode'] = [
            'label' => __( 'Safe test mode enabled', 'money-quiz' ),
            'status' => self::is_safe_mode(),
            'action' => __( 'Enable safe mode for initial testing', 'money-quiz' )
        ];
        
        // 3. Functionality tests passed
        $test_results = Quiz_Functionality_Test::get_last_results();
        $checklist['functionality_tests'] = [
            'label' => __( 'All functionality tests passed', 'money-quiz' ),
            'status' => ! empty( $test_results ) && $test_results['summary']['failed'] === 0,
            'action' => __( 'Run functionality tests and ensure all pass', 'money-quiz' )
        ];
        
        // 4. Error monitoring active
        $checklist['error_monitoring'] = [
            'label' => __( 'Error monitoring configured', 'money-quiz' ),
            'status' => get_option( 'money_quiz_menu_debug_mode' ),
            'action' => __( 'Enable debug mode for error tracking', 'money-quiz' )
        ];
        
        // 5. Rollback plan ready
        $checklist['rollback_plan'] = [
            'label' => __( 'Rollback plan documented', 'money-quiz' ),
            'status' => get_option( 'money_quiz_rollback_plan_confirmed' ),
            'action' => __( 'Review and confirm rollback procedures', 'money-quiz' )
        ];
        
        // 6. Admin notification sent
        $checklist['admin_notified'] = [
            'label' => __( 'Administrators notified', 'money-quiz' ),
            'status' => get_option( 'money_quiz_admins_notified_date' ),
            'action' => __( 'Notify all administrators about the menu change', 'money-quiz' )
        ];
        
        return $checklist;
    }
}

// Hook into menu initialization
add_filter( 'money_quiz_menu_enabled', function( $enabled ) {
    // Override with safe mode check
    if ( Safe_Mode_Config::is_safe_mode() ) {
        return Safe_Mode_Config::user_can_use_new_menu();
    }
    
    return $enabled;
}, 10 );

// Add admin notice for safe mode
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $status = Safe_Mode_Config::get_status();
    
    if ( $status['active'] ) {
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php _e( 'Money Quiz Menu - Safe Mode Active', 'money-quiz' ); ?></strong><br>
                <?php printf( 
                    __( 'Testing new menu system. Time remaining: %s hours. Test users: %d. Errors: %d', 'money-quiz' ),
                    $status['hours_remaining'],
                    $status['test_users'],
                    $status['errors_logged']
                ); ?>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-settings-advanced#safe-mode' ); ?>">
                    <?php _e( 'Manage Safe Mode', 'money-quiz' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
});