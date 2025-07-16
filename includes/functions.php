<?php
/**
 * Global functions for Money Quiz plugin
 *
 * @package MoneyQuiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Money Quiz service from container
 * 
 * @param string $service Service name
 * @return mixed|null Service instance or null if not found
 */
if (!function_exists('money_quiz_service')) {
    function money_quiz_service($service) {
        if (class_exists('Money_Quiz_Service_Container')) {
            return Money_Quiz_Service_Container::getInstance()->get($service);
        }
        return null;
    }
}

/**
 * Log Money Quiz debug information
 * 
 * @param mixed $data Data to log
 * @param string $context Context for the log entry
 */
if (!function_exists('money_quiz_log')) {
    function money_quiz_log($data, $context = 'general') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Money Quiz [' . $context . ']: ' . print_r($data, true));
        }
    }
}

/**
 * Get Money Quiz option with default fallback
 * 
 * @param string $option Option name
 * @param mixed $default Default value
 * @return mixed Option value
 */
if (!function_exists('money_quiz_get_option')) {
    function money_quiz_get_option($option, $default = false) {
        return get_option('money_quiz_' . $option, $default);
    }
}

/**
 * Set Money Quiz option
 * 
 * @param string $option Option name
 * @param mixed $value Option value
 * @return bool Success
 */
if (!function_exists('money_quiz_set_option')) {
    function money_quiz_set_option($option, $value) {
        return update_option('money_quiz_' . $option, $value);
    }
}

/**
 * Check if Money Quiz feature is enabled
 * 
 * @param string $feature Feature name
 * @return bool
 */
if (!function_exists('money_quiz_is_feature_enabled')) {
    function money_quiz_is_feature_enabled($feature) {
        $enabled_features = money_quiz_get_option('enabled_features', array());
        return in_array($feature, $enabled_features, true);
    }
}

/**
 * Get Money Quiz plugin URL
 * 
 * @param string $path Optional path to append
 * @return string Plugin URL
 */
if (!function_exists('money_quiz_plugin_url')) {
    function money_quiz_plugin_url($path = '') {
        $url = plugins_url('', dirname(__FILE__));
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        return $url;
    }
}

/**
 * Get Money Quiz plugin path
 * 
 * @param string $path Optional path to append
 * @return string Plugin path
 */
if (!function_exists('money_quiz_plugin_path')) {
    function money_quiz_plugin_path($path = '') {
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        if ($path) {
            $plugin_path .= ltrim($path, '/');
        }
        return $plugin_path;
    }
}