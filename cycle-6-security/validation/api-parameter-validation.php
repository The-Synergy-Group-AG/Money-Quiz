<?php
/**
 * API Parameter Validation
 * 
 * Validates API request parameters for security
 * 
 * @package MoneyQuiz\Security\Validation
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Validation;

class ApiParameterValidation {
    
    /**
     * Validation rules for common parameters
     */
    private static $validation_rules = [
        'id' => ['type' => 'integer', 'min' => 1],
        'page' => ['type' => 'integer', 'min' => 1, 'max' => 1000],
        'per_page' => ['type' => 'integer', 'min' => 1, 'max' => 100],
        'order' => ['type' => 'enum', 'values' => ['asc', 'desc', 'ASC', 'DESC']],
        'orderby' => ['type' => 'string', 'pattern' => '/^[a-zA-Z_]+$/'],
        'search' => ['type' => 'string', 'max_length' => 100],
        'status' => ['type' => 'enum', 'values' => ['active', 'inactive', 'pending', 'deleted']],
        'date_from' => ['type' => 'date', 'format' => 'Y-m-d'],
        'date_to' => ['type' => 'date', 'format' => 'Y-m-d']
    ];
    
    /**
     * Validate API parameters
     */
    public static function validate_parameters($params, $rules = []) {
        $errors = [];
        $validated = [];
        
        // Merge with default rules
        $rules = array_merge(self::$validation_rules, $rules);
        
        foreach ($rules as $param => $rule) {
            if (!isset($params[$param])) {
                if (!empty($rule['required'])) {
                    $errors[$param] = 'Required parameter missing';
                }
                continue;
            }
            
            $value = $params[$param];
            $validation_result = self::validate_parameter($value, $rule);
            
            if ($validation_result['valid']) {
                $validated[$param] = $validation_result['value'];
            } else {
                $errors[$param] = $validation_result['error'];
            }
        }
        
        // Check for unexpected parameters
        $expected_params = array_keys($rules);
        $provided_params = array_keys($params);
        $unexpected = array_diff($provided_params, $expected_params);
        
        if (!empty($unexpected)) {
            foreach ($unexpected as $param) {
                $errors[$param] = 'Unexpected parameter';
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        return ['valid' => true, 'data' => $validated];
    }
    
    /**
     * Validate single parameter
     */
    private static function validate_parameter($value, $rule) {
        $type = $rule['type'] ?? 'string';
        
        switch ($type) {
            case 'integer':
                return self::validate_integer($value, $rule);
            case 'string':
                return self::validate_string($value, $rule);
            case 'boolean':
                return self::validate_boolean($value);
            case 'array':
                return self::validate_array($value, $rule);
            case 'enum':
                return self::validate_enum($value, $rule);
            case 'date':
                return self::validate_date($value, $rule);
            case 'email':
                return self::validate_email($value);
            case 'url':
                return self::validate_url($value);
            default:
                return ['valid' => false, 'error' => 'Unknown validation type'];
        }
    }
    
    /**
     * Validate integer parameter
     */
    private static function validate_integer($value, $rule) {
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => 'Must be a number'];
        }
        
        $value = intval($value);
        
        if (isset($rule['min']) && $value < $rule['min']) {
            return ['valid' => false, 'error' => "Must be at least {$rule['min']}"];
        }
        
        if (isset($rule['max']) && $value > $rule['max']) {
            return ['valid' => false, 'error' => "Must be at most {$rule['max']}"];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate string parameter
     */
    private static function validate_string($value, $rule) {
        if (!is_string($value)) {
            return ['valid' => false, 'error' => 'Must be a string'];
        }
        
        // Sanitize
        $value = sanitize_text_field($value);
        
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            return ['valid' => false, 'error' => "Must be at least {$rule['min_length']} characters"];
        }
        
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            return ['valid' => false, 'error' => "Must be at most {$rule['max_length']} characters"];
        }
        
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
            return ['valid' => false, 'error' => 'Invalid format'];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate boolean parameter
     */
    private static function validate_boolean($value) {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        
        if ($value === null) {
            return ['valid' => false, 'error' => 'Must be a boolean'];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate array parameter
     */
    private static function validate_array($value, $rule) {
        if (!is_array($value)) {
            return ['valid' => false, 'error' => 'Must be an array'];
        }
        
        if (isset($rule['min_items']) && count($value) < $rule['min_items']) {
            return ['valid' => false, 'error' => "Must have at least {$rule['min_items']} items"];
        }
        
        if (isset($rule['max_items']) && count($value) > $rule['max_items']) {
            return ['valid' => false, 'error' => "Must have at most {$rule['max_items']} items"];
        }
        
        // Validate each item if item rule is provided
        if (isset($rule['items'])) {
            $validated_items = [];
            foreach ($value as $item) {
                $item_result = self::validate_parameter($item, $rule['items']);
                if (!$item_result['valid']) {
                    return ['valid' => false, 'error' => 'Invalid array item: ' . $item_result['error']];
                }
                $validated_items[] = $item_result['value'];
            }
            $value = $validated_items;
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate enum parameter
     */
    private static function validate_enum($value, $rule) {
        if (!isset($rule['values']) || !is_array($rule['values'])) {
            return ['valid' => false, 'error' => 'Invalid enum configuration'];
        }
        
        if (!in_array($value, $rule['values'], true)) {
            return ['valid' => false, 'error' => 'Invalid value. Must be one of: ' . implode(', ', $rule['values'])];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate date parameter
     */
    private static function validate_date($value, $rule) {
        $format = $rule['format'] ?? 'Y-m-d';
        $datetime = \DateTime::createFromFormat($format, $value);
        
        if (!$datetime || $datetime->format($format) !== $value) {
            return ['valid' => false, 'error' => "Invalid date format. Expected: {$format}"];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate email parameter
     */
    private static function validate_email($value) {
        $value = sanitize_email($value);
        
        if (!is_email($value)) {
            return ['valid' => false, 'error' => 'Invalid email address'];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate URL parameter
     */
    private static function validate_url($value) {
        $value = esc_url_raw($value);
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL'];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Create validation error response
     */
    public static function create_error_response($errors) {
        return new \WP_REST_Response([
            'code' => 'invalid_parameters',
            'message' => 'Validation failed',
            'data' => [
                'status' => 400,
                'params' => $errors
            ]
        ], 400);
    }
}