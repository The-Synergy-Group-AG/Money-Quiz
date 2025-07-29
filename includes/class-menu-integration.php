<?php
/**
 * Menu Integration - Ensures menu redesign is loaded
 * 
 * @package MoneyQuiz
 * @subpackage Integration
 * @since 4.0.0
 */

namespace MoneyQuiz;

use MoneyQuiz\Admin\Menu_Redesign;

if (!defined('ABSPATH')) {
    exit;
}

class MenuIntegration {
    
    /**
     * Initialize menu integration
     */
    public static function init() {
        // Only load in admin area
        if (!is_admin()) {
            return;
        }
        
        // Load menu redesign class if it exists
        $menu_redesign_file = MONEY_QUIZ_PLUGIN_DIR . 'includes/admin/menu-redesign/class-menu-redesign.php';
        if (file_exists($menu_redesign_file)) {
            require_once $menu_redesign_file;
            
            // Initialize menu redesign
            add_action('init', function() {
                if (class_exists('MoneyQuiz\Admin\Menu_Redesign')) {
                    $menu_redesign = Menu_Redesign::get_instance();
                    $menu_redesign->init();
                }
            }, 5);
        }
        
        // Load isolated menu config if in isolated environment
        if (defined('MONEY_QUIZ_ISOLATED_ENV') && MONEY_QUIZ_ISOLATED_ENV) {
            $isolated_config = MONEY_QUIZ_PLUGIN_DIR . 'includes/admin/menu-redesign/isolated-menu-config.php';
            if (file_exists($isolated_config)) {
                require_once $isolated_config;
            }
        }
    }
}

// Initialize menu integration
add_action('plugins_loaded', ['MoneyQuiz\MenuIntegration', 'init'], 10);