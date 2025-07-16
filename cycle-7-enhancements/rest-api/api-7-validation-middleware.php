<?php
/**
 * REST API Validation Middleware
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Validation Middleware
 */
class ValidationMiddleware {
    
    /**
     * Validate request
     */
    public static function validate($request) {
        // Check content type for POST/PUT
        if (in_array($request->get_method(), ['POST', 'PUT'])) {
            $content_type = $request->get_content_type();
            
            if (!in_array($content_type['value'], ['application/json', 'multipart/form-data'])) {
                return new \WP_Error(
                    'invalid_content_type',
                    'Content-Type must be application/json or multipart/form-data',
                    ['status' => 400]
                );
            }
        }
        
        // Validate rate limiting
        if (function_exists('money_quiz_check_rate_limit')) {
            $rate_check = money_quiz_check_rate_limit($request);
            if (is_wp_error($rate_check)) {
                return $rate_check;
            }
        }
        
        // Validate request size
        $max_size = apply_filters('money_quiz_api_max_request_size', 2 * MB_IN_BYTES);
        if (strlen($request->get_body()) > $max_size) {
            return new \WP_Error(
                'request_too_large',
                'Request body too large',
                ['status' => 413]
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($data, $rules) {
        $sanitized = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                if (isset($rule['required']) && $rule['required']) {
                    return new \WP_Error(
                        'missing_required_field',
                        sprintf('Field "%s" is required', $field),
                        ['status' => 400]
                    );
                }
                continue;
            }
            
            $value = $data[$field];
            
            // Apply sanitization
            if (isset($rule['sanitize'])) {
                $value = call_user_func($rule['sanitize'], $value);
            }
            
            // Validate type
            if (isset($rule['type'])) {
                if (!self::validateType($value, $rule['type'])) {
                    return new \WP_Error(
                        'invalid_field_type',
                        sprintf('Field "%s" must be of type %s', $field, $rule['type']),
                        ['status' => 400]
                    );
                }
            }
            
            // Validate enum
            if (isset($rule['enum']) && !in_array($value, $rule['enum'])) {
                return new \WP_Error(
                    'invalid_enum_value',
                    sprintf('Field "%s" must be one of: %s', $field, implode(', ', $rule['enum'])),
                    ['status' => 400]
                );
            }
            
            // Validate min/max
            if (isset($rule['min']) && $value < $rule['min']) {
                return new \WP_Error(
                    'value_too_small',
                    sprintf('Field "%s" must be at least %s', $field, $rule['min']),
                    ['status' => 400]
                );
            }
            
            if (isset($rule['max']) && $value > $rule['max']) {
                return new \WP_Error(
                    'value_too_large',
                    sprintf('Field "%s" must be at most %s', $field, $rule['max']),
                    ['status' => 400]
                );
            }
            
            $sanitized[$field] = $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Validate data type
     */
    private static function validateType($value, $type) {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_numeric($value) && intval($value) == $value;
            case 'float':
                return is_numeric($value);
            case 'boolean':
                return is_bool($value) || in_array($value, ['0', '1', 'true', 'false']);
            case 'array':
                return is_array($value);
            case 'object':
                return is_object($value) || is_array($value);
            case 'email':
                return is_email($value);
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            default:
                return true;
        }
    }
    
    /**
     * Create validation rules
     */
    public static function rules() {
        return [
            'quiz' => [
                'title' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize' => 'sanitize_text_field',
                    'min' => 3,
                    'max' => 200
                ],
                'description' => [
                    'type' => 'string',
                    'sanitize' => 'wp_kses_post'
                ]
            ],
            'result' => [
                'quiz_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize' => 'absint'
                ],
                'answers' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ];
    }
}