<?php
/**
 * Main Money Quiz Plugin Class
 * 
 * @package MoneyQuiz\Core
 * @version 3.22.9
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * Main Money Quiz Plugin Class
 */
class Money_Quiz_Plugin {
    private static $instance = null;
    private $version;
    private $plugin_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->version = MONEYQUIZ_VERSION;
        $this->plugin_name = 'money-quiz';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
    }
    
    public function init() {
        // Plugin initialization
        load_plugin_textdomain('moneyquiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function admin_init() {
        // Admin initialization
        if (current_user_can('manage_options')) {
            // Admin-specific initialization
        }
    }
    
    public function get_version() {
        return $this->version;
    }
    
    public function get_plugin_name() {
        return $this->plugin_name;
    }
} 