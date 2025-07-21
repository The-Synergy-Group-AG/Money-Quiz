<?php
/**
 * Money Quiz Configuration Class
 * 
 * @package MoneyQuiz\Config
 * @version 3.22.9
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * Configuration Management Class
 */
class Money_Quiz_Config {
    private static $config = [];
    
    public static function get($key, $default = null) {
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        return $default;
    }
    
    public static function set($key, $value) {
        self::$config[$key] = $value;
    }
    
    public static function load() {
        self::$config = get_option('money_quiz_config', []);
    }
    
    public static function save() {
        update_option('money_quiz_config', self::$config);
    }
    
    public static function delete($key) {
        if (isset(self::$config[$key])) {
            unset(self::$config[$key]);
        }
    }
    
    public static function get_all() {
        return self::$config;
    }
    
    public static function reset() {
        self::$config = [];
        self::save();
    }
} 