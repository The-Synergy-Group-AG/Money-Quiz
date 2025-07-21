<?php
/**
 * Validation Integration
 * 
 * Integrates all validation components for Money Quiz
 * 
 * @package MoneyQuiz\Security\Validation
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Validation;

class ValidationIntegration {
    
    /**
     * Initialize validation system
     */
    public static function init() {
        // Register validation hooks
        add_filter('money_quiz_validate_input', [__CLASS__, 'validate_input'], 10, 3);
        add_filter('money_quiz_validate_file', [__CLASS__, 'validate_file'], 10, 3);
        add_filter('money_quiz_validate_api', [__CLASS__, 'validate_api'], 10, 2);
        
        // Ajax handlers
        add_action('wp_ajax_money_quiz_validate', [__CLASS__, 'ajax_validate']);
        add_action('wp_ajax_nopriv_money_quiz_validate', [__CLASS__, 'ajax_validate']);
        
        // REST API validation
        add_filter('rest_pre_dispatch', [__CLASS__, 'rest_validate'], 10, 3);
    }
    
    /**
     * Unified input validation
     */
    public static function validate_input($value, $type, $options = []) {
        switch ($type) {
            case 'text':
                return InputSanitization::sanitize_text($value, $options);
            
            case 'email':
                return InputSanitization::sanitize_email($value);
            
            case 'url':
                return InputSanitization::sanitize_url($value);
            
            case 'number':
                return InputSanitization::sanitize_number($value, 
                    $options['number_type'] ?? 'int', $options);
            
            case 'array':
                return InputSanitization::sanitize_array($value, 
                    $options['callback'] ?? 'sanitize_text_field');
            
            case 'json':
                return InputSanitization::sanitize_json($value);
            
            case 'boolean':
                return InputSanitization::sanitize_boolean($value);
            
            case 'date':
                return InputSanitization::sanitize_date($value, 
                    $options['format'] ?? 'Y-m-d');
            
            case 'color':
                return InputSanitization::sanitize_hex_color($value);
            
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Validate file upload
     */
    public static function validate_file($file, $type, $options = []) {
        return FileUploadValidation::validate_upload($file, $type, $options);
    }
    
    /**
     * Validate API parameters
     */
    public static function validate_api($params, $rules = []) {
        return ApiParameterValidation::validate_parameters($params, $rules);
    }
    
    /**
     * Ajax validation handler
     */
    public static function ajax_validate() {
        // Verify nonce
        if (!check_ajax_referer('money_quiz_ajax', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $type = sanitize_text_field($_POST['validation_type'] ?? '');
        $value = $_POST['value'] ?? '';
        $options = $_POST['options'] ?? [];
        
        $result = self::validate_input($value, $type, $options);
        
        wp_send_json_success([
            'validated' => $result,
            'original' => $value,
            'type' => $type
        ]);
    }
    
    /**
     * REST API validation
     */
    public static function rest_validate($result, $server, $request) {
        // Skip if already errored
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Only validate Money Quiz endpoints
        $route = $request->get_route();
        if (strpos($route, '/money-quiz/') !== 0) {
            return $result;
        }
        
        // Get validation rules for this endpoint
        $rules = self::get_endpoint_rules($route, $request->get_method());
        if (empty($rules)) {
            return $result;
        }
        
        // Validate parameters
        $params = $request->get_params();
        $validation = ApiParameterValidation::validate_parameters($params, $rules);
        
        if (!$validation['valid']) {
            return ApiParameterValidation::create_error_response($validation['errors']);
        }
        
        // Replace params with validated data
        foreach ($validation['data'] as $key => $value) {
            $request->set_param($key, $value);
        }
        
        return $result;
    }
    
    /**
     * Get validation rules for endpoint
     */
    private static function get_endpoint_rules($route, $method) {
        $rules = [
            '/money-quiz/v1/quiz' => [
                'GET' => [
                    'page' => ['type' => 'integer', 'min' => 1],
                    'per_page' => ['type' => 'integer', 'min' => 1, 'max' => 100],
                    'search' => ['type' => 'string', 'max_length' => 100]
                ],
                'POST' => [
                    'title' => ['type' => 'string', 'required' => true, 'max_length' => 200],
                    'description' => ['type' => 'string', 'max_length' => 1000],
                    'status' => ['type' => 'enum', 'values' => ['draft', 'published']]
                ]
            ],
            '/money-quiz/v1/results' => [
                'POST' => [
                    'quiz_id' => ['type' => 'integer', 'required' => true, 'min' => 1],
                    'answers' => ['type' => 'array', 'required' => true],
                    'user_data' => ['type' => 'array']
                ]
            ]
        ];
        
        // Match route pattern
        foreach ($rules as $pattern => $methods) {
            if (preg_match('#^' . $pattern . '(/\d+)?$#', $route)) {
                return $methods[$method] ?? [];
            }
        }
        
        return [];
    }
    
    /**
     * Create validation helper functions
     */
    public static function create_helpers() {
        if (!function_exists('money_quiz_validate')) {
            function money_quiz_validate($value, $type, $options = []) {
                return apply_filters('money_quiz_validate_input', $value, $type, $options);
            }
        }
        
        if (!function_exists('money_quiz_validate_file')) {
            function money_quiz_validate_file($file, $type = 'image', $options = []) {
                return apply_filters('money_quiz_validate_file', $file, $type, $options);
            }
        }
        
        if (!function_exists('money_quiz_validate_api')) {
            function money_quiz_validate_api($params, $rules = []) {
                return apply_filters('money_quiz_validate_api', $params, $rules);
            }
        }
    }
}

// Initialize on load
add_action('init', ['MoneyQuiz\Security\Validation\ValidationIntegration', 'init']);
add_action('init', ['MoneyQuiz\Security\Validation\ValidationIntegration', 'create_helpers']);