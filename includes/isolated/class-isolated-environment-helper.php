<?php
/**
 * Isolated Environment Helper
 * 
 * Simplifies plugin functionality for single-user testing
 * 
 * @package MoneyQuiz
 * @subpackage Isolated
 * @since 4.0.0
 */

namespace MoneyQuiz\Isolated;

if (!defined('ABSPATH')) {
    exit;
}

class IsolatedEnvironmentHelper {
    
    /**
     * Initialize isolated environment modifications
     */
    public static function init() {
        if (!defined('MONEY_QUIZ_ISOLATED_ENV') || !MONEY_QUIZ_ISOLATED_ENV) {
            return;
        }
        
        // Simplify features
        self::simplify_email_system();
        self::disable_tracking();
        self::simplify_database_operations();
        self::streamline_ui();
        self::add_isolated_notices();
    }
    
    /**
     * Simplify email system for testing
     */
    private static function simplify_email_system() {
        // Use WordPress default email instead of complex campaign system
        add_filter('mq_use_simple_email', '__return_true');
        
        // Disable email campaigns
        add_filter('mq_enable_email_campaigns', '__return_false');
        
        // Use local mail (no external SMTP)
        add_filter('mq_force_local_email', '__return_true');
        
        // Log emails instead of sending (optional)
        if (defined('MQ_LOG_EMAILS') && MQ_LOG_EMAILS) {
            add_filter('wp_mail', function($args) {
                error_log('Money Quiz Email: ' . print_r($args, true));
                return $args;
            });
        }
    }
    
    /**
     * Disable unnecessary tracking
     */
    private static function disable_tracking() {
        // Disable all tracking
        $tracking_filters = [
            'mq_track_submissions',
            'mq_track_conversions',
            'mq_track_page_views',
            'mq_track_user_behavior',
            'mq_collect_analytics',
            'mq_store_ip_address',
            'mq_store_user_agent',
            'mq_track_referrers'
        ];
        
        foreach ($tracking_filters as $filter) {
            add_filter($filter, '__return_false');
        }
        
        // Clear IP from submissions
        add_filter('mq_submission_data', function($data) {
            unset($data['ip_address']);
            unset($data['user_agent']);
            unset($data['referrer']);
            return $data;
        });
    }
    
    /**
     * Simplify database operations
     */
    private static function simplify_database_operations() {
        // Skip legacy table checks
        add_filter('mq_check_legacy_tables', '__return_false');
        
        // Use modern tables only
        add_filter('mq_use_legacy_compatibility', '__return_false');
        
        // Disable complex migrations
        add_filter('mq_run_complex_migrations', '__return_false');
        
        // Reduce database optimization checks
        add_filter('mq_optimization_interval', function() {
            return DAY_IN_SECONDS * 30; // Monthly instead of daily
        });
    }
    
    /**
     * Streamline UI for single user
     */
    private static function streamline_ui() {
        // Remove bulk actions (not needed for single user)
        add_filter('mq_show_bulk_actions', '__return_false');
        
        // Simplify dashboard widgets
        add_action('wp_dashboard_setup', function() {
            remove_meta_box('mq_routing_status', 'dashboard', 'normal');
            remove_meta_box('mq_system_health', 'dashboard', 'normal');
        }, 20);
        
        // Hide user-specific features
        add_filter('mq_show_user_segments', '__return_false');
        add_filter('mq_show_user_roles', '__return_false');
        
        // Disable multi-site features
        add_filter('mq_multisite_features', '__return_false');
    }
    
    /**
     * Add isolated environment notices
     */
    private static function add_isolated_notices() {
        // Add admin bar indicator
        add_action('admin_bar_menu', function($wp_admin_bar) {
            $wp_admin_bar->add_node([
                'id' => 'mq-isolated-mode',
                'title' => '🔬 Isolated Mode',
                'href' => admin_url('admin.php?page=money-quiz-settings'),
                'meta' => [
                    'class' => 'mq-isolated-indicator',
                    'title' => 'Money Quiz is running in isolated testing mode'
                ]
            ]);
        }, 100);
        
        // Add settings notice
        add_action('admin_notices', function() {
            if (get_current_screen()->id === 'money-quiz_page_money-quiz-settings') {
                ?>
                <div class="notice notice-info">
                    <p><strong>Isolated Environment Mode Active</strong></p>
                    <p>The following features are disabled for testing:</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Email campaigns and tracking</li>
                        <li>User tracking and analytics</li>
                        <li>Legacy compatibility checks</li>
                        <li>Multi-user features</li>
                    </ul>
                </div>
                <?php
            }
        });
    }
    
    /**
     * Get isolated environment status
     */
    public static function get_status() {
        return [
            'mode' => 'isolated',
            'features_disabled' => [
                'email_campaigns',
                'user_tracking',
                'analytics',
                'legacy_compatibility',
                'multi_user',
                'bulk_operations'
            ],
            'routing' => '100% modern',
            'safety_features' => 'active'
        ];
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [IsolatedEnvironmentHelper::class, 'init'], 5);