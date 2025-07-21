<?php
/**
 * Basic Input Sanitization Functions
 * 
 * Core sanitization utilities for the Money Quiz plugin
 * 
 * @package MoneyQuiz\Security\Validation
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Validation;

class InputSanitization {
    
    /**
     * Sanitize text input
     */
    public static function sanitize_text($input, $options = []) {
        $options = wp_parse_args($options, [
            'max_length' => 255,
            'allow_html' => false,
            'preserve_newlines' => false,
            'trim' => true
        ]);
        
        // Convert to string
        $input = (string) $input;
        
        // Trim if requested
        if ($options['trim']) {
            $input = trim($input);
        }
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        // Handle HTML
        if (!$options['allow_html']) {
            $input = wp_strip_all_tags($input);
        } else {
            $input = wp_kses_post($input);
        }
        
        // Handle newlines
        if (!$options['preserve_newlines']) {
            $input = preg_replace('/[\r\n]+/', ' ', $input);
        }
        
        // Enforce max length
        if ($options['max_length'] > 0) {
            $input = substr($input, 0, $options['max_length']);
        }
        
        return $input;
    }
    
    /**
     * Sanitize email address
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            return '';
        }
        
        return $email;
    }
    
    /**
     * Sanitize URL
     */
    public static function sanitize_url($url, $protocols = null) {
        if ($protocols === null) {
            $protocols = ['http', 'https'];
        }
        
        $url = esc_url_raw($url, $protocols);
        
        // Additional validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        return $url;
    }
    
    /**
     * Sanitize numeric input
     */
    public static function sanitize_number($input, $type = 'int', $options = []) {
        $options = wp_parse_args($options, [
            'min' => null,
            'max' => null,
            'default' => 0
        ]);
        
        switch ($type) {
            case 'int':
                $value = intval($input);
                break;
            case 'float':
                $value = floatval($input);
                break;
            case 'positive':
                $value = abs(intval($input));
                break;
            default:
                $value = $options['default'];
        }
        
        // Apply min/max constraints
        if ($options['min'] !== null && $value < $options['min']) {
            $value = $options['min'];
        }
        if ($options['max'] !== null && $value > $options['max']) {
            $value = $options['max'];
        }
        
        return $value;
    }
    
    /**
     * Sanitize array input
     */
    public static function sanitize_array($array, $callback = 'sanitize_text_field') {
        if (!is_array($array)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$sanitized_key] = self::sanitize_array($value, $callback);
            } else {
                if (is_callable($callback)) {
                    $sanitized[$sanitized_key] = call_user_func($callback, $value);
                } else {
                    $sanitized[$sanitized_key] = sanitize_text_field($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize JSON input
     */
    public static function sanitize_json($json) {
        if (!is_string($json)) {
            return '';
        }
        
        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        
        // Recursively sanitize the decoded data
        $sanitized = self::sanitize_array($decoded);
        
        return json_encode($sanitized);
    }
    
    /**
     * Sanitize boolean input
     */
    public static function sanitize_boolean($input) {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitize date input
     */
    public static function sanitize_date($date, $format = 'Y-m-d') {
        $datetime = \DateTime::createFromFormat($format, $date);
        
        if (!$datetime || $datetime->format($format) !== $date) {
            return '';
        }
        
        return $datetime->format($format);
    }
    
    /**
     * Sanitize hex color
     */
    public static function sanitize_hex_color($color) {
        if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            return $color;
        }
        
        if (preg_match('/^#[a-f0-9]{3}$/i', $color)) {
            return $color;
        }
        
        return '';
    }
}