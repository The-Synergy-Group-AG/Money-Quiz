<?php
/**
 * Security Helper for Money Quiz
 * 
 * Provides standardized security functions for the plugin
 * 
 * @package MoneyQuiz
 * @since 4.0.1
 */

namespace MoneyQuiz\Security;

class SecurityHelper {
    
    /**
     * Verify nonce for form submission
     * 
     * @param string $action Nonce action name
     * @param string $nonce_name Nonce field name (default: '_wpnonce')
     * @return bool True if valid, dies with error if invalid
     */
    public static function verify_nonce($action, $nonce_name = '_wpnonce') {
        if (!isset($_REQUEST[$nonce_name]) || !wp_verify_nonce($_REQUEST[$nonce_name], $action)) {
            wp_die(__('Security check failed. Please try again.', 'money-quiz'), __('Security Error', 'money-quiz'), array(
                'response' => 403,
                'back_link' => true,
            ));
        }
        return true;
    }
    
    /**
     * Verify user capability
     * 
     * @param string $capability Required capability
     * @return bool True if capable, dies with error if not
     */
    public static function verify_capability($capability) {
        if (!current_user_can($capability)) {
            wp_die(__('You do not have permission to perform this action.', 'money-quiz'), __('Permission Error', 'money-quiz'), array(
                'response' => 403,
                'back_link' => true,
            ));
        }
        return true;
    }
    
    /**
     * Sanitize and validate request data
     * 
     * @param array $fields Array of field definitions ['field_name' => 'type']
     * @param array $source Source array ($_POST, $_GET, etc.)
     * @return array Sanitized data
     */
    public static function sanitize_request($fields, $source = null) {
        if ($source === null) {
            $source = $_REQUEST;
        }
        
        $sanitized = array();
        
        foreach ($fields as $field => $type) {
            if (!isset($source[$field])) {
                continue;
            }
            
            $value = $source[$field];
            
            switch ($type) {
                case 'text':
                    $sanitized[$field] = sanitize_text_field($value);
                    break;
                    
                case 'textarea':
                    $sanitized[$field] = sanitize_textarea_field($value);
                    break;
                    
                case 'email':
                    $sanitized[$field] = sanitize_email($value);
                    break;
                    
                case 'url':
                    $sanitized[$field] = esc_url_raw($value);
                    break;
                    
                case 'int':
                case 'number':
                    $sanitized[$field] = intval($value);
                    break;
                    
                case 'float':
                    $sanitized[$field] = floatval($value);
                    break;
                    
                case 'bool':
                case 'boolean':
                    $sanitized[$field] = (bool) $value;
                    break;
                    
                case 'array':
                    $sanitized[$field] = (array) $value;
                    break;
                    
                case 'html':
                    $sanitized[$field] = wp_kses_post($value);
                    break;
                    
                default:
                    $sanitized[$field] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Generate secure form fields
     * 
     * @param string $action Nonce action
     * @param string $nonce_name Nonce field name
     * @return string HTML for security fields
     */
    public static function get_security_fields($action, $nonce_name = '_wpnonce') {
        return wp_nonce_field($action, $nonce_name, true, false);
    }
    
    /**
     * Escape output based on context
     * 
     * @param mixed $value Value to escape
     * @param string $context Output context (html, attr, url, js, textarea)
     * @return string Escaped value
     */
    public static function escape_output($value, $context = 'html') {
        switch ($context) {
            case 'html':
                return esc_html($value);
                
            case 'attr':
                return esc_attr($value);
                
            case 'url':
                return esc_url($value);
                
            case 'js':
                return esc_js($value);
                
            case 'textarea':
                return esc_textarea($value);
                
            default:
                return esc_html($value);
        }
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    public static function is_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
    
    /**
     * Validate AJAX nonce
     * 
     * @param string $action Nonce action
     * @param string $nonce_name Nonce parameter name
     */
    public static function verify_ajax_nonce($action, $nonce_name = 'nonce') {
        if (!check_ajax_referer($action, $nonce_name, false)) {
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'money-quiz')
            ), 403);
        }
    }
}

// Legacy compatibility function
if (!function_exists('mq_verify_nonce')) {
    function mq_verify_nonce($action, $nonce_name = '_wpnonce') {
        return SecurityHelper::verify_nonce($action, $nonce_name);
    }
}

if (!function_exists('mq_sanitize_request')) {
    function mq_sanitize_request($fields, $source = null) {
        return SecurityHelper::sanitize_request($fields, $source);
    }
}

if (!function_exists('mq_escape')) {
    function mq_escape($value, $context = 'html') {
        return SecurityHelper::escape_output($value, $context);
    }
}