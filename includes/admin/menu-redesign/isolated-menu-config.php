<?php
/**
 * Isolated Environment Menu Configuration
 * 
 * Simplifies the admin menu for single-user testing
 * 
 * @package MoneyQuiz
 * @subpackage Admin
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin\MenuRedesign;

if (!defined('ABSPATH')) {
    exit;
}

class IsolatedMenuConfig {
    
    /**
     * Initialize isolated menu configuration
     */
    public static function init() {
        if (!defined('MONEY_QUIZ_ISOLATED_ENV') || !MONEY_QUIZ_ISOLATED_ENV) {
            return;
        }
        
        // Hide placeholder menu items
        add_filter('mq_admin_menu_items', [__CLASS__, 'filter_menu_items'], 20);
        
        // Simplify menu structure
        add_filter('mq_menu_capability', [__CLASS__, 'simplify_capabilities']);
        
        // Remove unnecessary features
        add_action('admin_init', [__CLASS__, 'disable_unnecessary_features']);
    }
    
    /**
     * Filter menu items to remove placeholders
     */
    public static function filter_menu_items($menu_items) {
        // Remove placeholder items
        $remove_items = [
            'mq-quiz-templates',    // Not implemented
            'mq-landing-pages',     // Not implemented
            'mq-ab-testing',        // Not implemented
            'mq-email-campaigns',   // Overkill for isolated testing
            'mq-system-health'      // Not needed for isolated env
        ];
        
        foreach ($menu_items as $key => $item) {
            if (isset($item['menu_slug']) && in_array($item['menu_slug'], $remove_items)) {
                unset($menu_items[$key]);
            }
            
            // Remove from submenus
            if (isset($item['submenus'])) {
                foreach ($item['submenus'] as $sub_key => $submenu) {
                    if (in_array($submenu['menu_slug'], $remove_items)) {
                        unset($menu_items[$key]['submenus'][$sub_key]);
                    }
                }
            }
        }
        
        return $menu_items;
    }
    
    /**
     * Simplify capability requirements
     */
    public static function simplify_capabilities($capability) {
        // In isolated environment, just use manage_options
        return 'manage_options';
    }
    
    /**
     * Disable unnecessary features for isolated testing
     */
    public static function disable_unnecessary_features() {
        // Disable email tracking
        add_filter('mq_track_email_opens', '__return_false');
        add_filter('mq_track_email_clicks', '__return_false');
        
        // Disable IP tracking
        add_filter('mq_track_ip_address', '__return_false');
        add_filter('mq_track_user_agent', '__return_false');
        
        // Disable admin notifications
        add_filter('mq_send_admin_notifications', '__return_false');
        
        // Disable complex analytics
        add_filter('mq_enable_advanced_analytics', '__return_false');
        
        // Disable gradual rollout UI
        add_filter('mq_show_rollout_controls', '__return_false');
    }
}

// Initialize
add_action('init', [IsolatedMenuConfig::class, 'init']);