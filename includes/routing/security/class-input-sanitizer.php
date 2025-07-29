<?php
/**
 * Input Sanitizer - Sanitizes all inputs for hybrid routing
 * 
 * @package MoneyQuiz
 * @subpackage Routing\Security
 * @since 1.5.0
 */

namespace MoneyQuiz\Routing\Security;

if (!defined('ABSPATH')) {
    exit;
}

class InputSanitizer {
    
    /**
     * Sanitization rules per action
     */
    private $rules = [
        'quiz_display' => [
            'quiz_id' => 'absint'
        ],
        'quiz_submit' => [
            'quiz_id' => 'absint',
            'answers' => 'array',
            'email' => 'email',
            'name' => 'text',
            'nonce' => 'text'
        ],
        'quiz_results' => [
            'result_id' => 'absint'
        ],
        'archetype_fetch' => [
            'archetype_id' => 'absint',
            'score' => 'float'
        ],
        'statistics_view' => [
            'date_from' => 'date',
            'date_to' => 'date',
            'quiz_id' => 'absint'
        ]
    ];
    
    /**
     * Sanitize input data based on action
     * 
     * @param string $action
     * @param array $data
     * @return array Sanitized data
     */
    public function sanitize($action, $data) {
        if (!is_array($data)) {
            return [];
        }
        
        // Get rules for this action
        $action_rules = $this->rules[$action] ?? [];
        
        // Apply general sanitization first
        $sanitized = $this->general_sanitize($data);
        
        // Apply specific rules
        foreach ($action_rules as $field => $rule) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = $this->apply_rule($sanitized[$field], $rule);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * General sanitization for all data
     * 
     * @param mixed $data
     * @return mixed
     */
    private function general_sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'general_sanitize'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace(chr(0), '', $data);
            
            // Strip tags and trim
            $data = wp_strip_all_tags($data);
            $data = trim($data);
        }
        
        return $data;
    }
    
    /**
     * Apply specific sanitization rule
     * 
     * @param mixed $value
     * @param string $rule
     * @return mixed
     */
    private function apply_rule($value, $rule) {
        switch ($rule) {
            case 'absint':
                return absint($value);
                
            case 'int':
                return intval($value);
                
            case 'float':
                return floatval($value);
                
            case 'email':
                return sanitize_email($value);
                
            case 'text':
                return sanitize_text_field($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'date':
                return $this->sanitize_date($value);
                
            case 'array':
                return is_array($value) ? $value : [];
                
            case 'bool':
                return (bool) $value;
                
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Sanitize date input
     * 
     * @param string $date
     * @return string
     */
    private function sanitize_date($date) {
        $date = sanitize_text_field($date);
        
        // Validate date format (Y-m-d)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsed = date_parse($date);
            if ($parsed['error_count'] === 0) {
                return $date;
            }
        }
        
        return '';
    }
    
    /**
     * Validate nonce for actions that require it
     * 
     * @param string $action
     * @param array $data
     * @return bool
     */
    public function validate_nonce($action, $data) {
        $nonce_actions = ['quiz_submit'];
        
        if (!in_array($action, $nonce_actions)) {
            return true;
        }
        
        $nonce = $data['nonce'] ?? '';
        return wp_verify_nonce($nonce, 'mq_' . $action);
    }
    
    /**
     * Check for potentially malicious patterns
     * 
     * @param mixed $data
     * @return bool True if safe, false if suspicious
     */
    public function is_safe($data) {
        $patterns = [
            // SQL injection patterns
            '/(\bunion\b.*\bselect\b|\bselect\b.*\bfrom\b|\binsert\b.*\binto\b|\bupdate\b.*\bset\b|\bdelete\b.*\bfrom\b)/i',
            // Script injection
            '/<script[^>]*>.*?<\/script>/is',
            // PHP injection
            '/<\?php|<\?=/i',
            // Command injection
            '/(\||;|`|&&|\$\()/i'
        ];
        
        $string = is_array($data) ? json_encode($data) : (string) $data;
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $string)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $action
     * @param string $identifier User ID or IP
     * @return bool True if allowed, false if rate limited
     */
    public function check_rate_limit($action, $identifier) {
        $limits = [
            'quiz_submit' => ['requests' => 10, 'window' => 3600], // 10 per hour
            'quiz_display' => ['requests' => 100, 'window' => 3600], // 100 per hour
            'statistics_view' => ['requests' => 50, 'window' => 3600] // 50 per hour
        ];
        
        if (!isset($limits[$action])) {
            return true;
        }
        
        $limit = $limits[$action];
        $key = 'mq_rate_' . $action . '_' . md5($identifier);
        
        $current = get_transient($key) ?: 0;
        
        if ($current >= $limit['requests']) {
            return false;
        }
        
        set_transient($key, $current + 1, $limit['window']);
        
        return true;
    }
}