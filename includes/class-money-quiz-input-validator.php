<?php
/**
 * Input Validator for MoneyQuiz Plugin
 * 
 * Comprehensive input validation and sanitization
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Input_Validator {
    
    /**
     * Sanitize and validate text input
     * 
     * @param string $input The input to sanitize
     * @param int $max_length Maximum allowed length
     * @return string Sanitized input
     */
    public static function sanitize_text($input, $max_length = 255) {
        $sanitized = sanitize_text_field($input);
        
        if (strlen($sanitized) > $max_length) {
            $sanitized = substr($sanitized, 0, $max_length);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize and validate email input
     * 
     * @param string $input The email to validate
     * @return string|false Sanitized email or false if invalid
     */
    public static function sanitize_email($input) {
        $email = sanitize_email($input);
        
        if (!is_email($email)) {
            return false;
        }
        
        return $email;
    }
    
    /**
     * Sanitize and validate URL input
     * 
     * @param string $input The URL to validate
     * @return string|false Sanitized URL or false if invalid
     */
    public static function sanitize_url($input) {
        $url = esc_url_raw($input);
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return $url;
    }
    
    /**
     * Sanitize and validate integer input
     * 
     * @param mixed $input The input to validate
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return int|false Validated integer or false if invalid
     */
    public static function sanitize_int($input, $min = null, $max = null) {
        $int = intval($input);
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return $int;
    }
    
    /**
     * Sanitize and validate float input
     * 
     * @param mixed $input The input to validate
     * @param float $min Minimum allowed value
     * @param float $max Maximum allowed value
     * @return float|false Validated float or false if invalid
     */
    public static function sanitize_float($input, $min = null, $max = null) {
        $float = floatval($input);
        
        if ($min !== null && $float < $min) {
            return false;
        }
        
        if ($max !== null && $float > $max) {
            return false;
        }
        
        return $float;
    }
    
    /**
     * Sanitize and validate boolean input
     * 
     * @param mixed $input The input to validate
     * @return bool Validated boolean
     */
    public static function sanitize_bool($input) {
        if (is_bool($input)) {
            return $input;
        }
        
        if (is_string($input)) {
            $input = strtolower(trim($input));
            return in_array($input, ['true', '1', 'yes', 'on']);
        }
        
        return (bool) $input;
    }
    
    /**
     * Sanitize and validate array input
     * 
     * @param array $input The array to sanitize
     * @param callable $callback Callback function for each element
     * @return array Sanitized array
     */
    public static function sanitize_array($input, $callback = null) {
        if (!is_array($input)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if ($callback && is_callable($callback)) {
                $sanitized[$sanitized_key] = $callback($value);
            } else {
                $sanitized[$sanitized_key] = self::sanitize_text($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate and sanitize file upload
     * 
     * @param array $file The uploaded file array
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return array|false Sanitized file array or false if invalid
     */
    public static function sanitize_file($file, $allowed_types = [], $max_size = 5242880) {
        if (!is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return false;
        }
        
        // Check MIME type
        if (!empty($allowed_types)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                return false;
            }
        }
        
        return [
            'name' => sanitize_file_name($file['name']),
            'type' => sanitize_text_field($file['type']),
            'tmp_name' => $file['tmp_name'],
            'error' => intval($file['error']),
            'size' => intval($file['size'])
        ];
    }
    
    /**
     * Validate and sanitize SQL query parameters
     * 
     * @param mixed $input The input to sanitize
     * @param string $type The expected type (string, int, float)
     * @return mixed Sanitized input
     */
    public static function sanitize_sql_param($input, $type = 'string') {
        switch ($type) {
            case 'int':
                return self::sanitize_int($input);
            case 'float':
                return self::sanitize_float($input);
            case 'bool':
                return self::sanitize_bool($input);
            default:
                return self::sanitize_text($input);
        }
    }
    
    /**
     * Validate and sanitize HTML content
     * 
     * @param string $input The HTML content
     * @param array $allowed_tags Allowed HTML tags
     * @return string Sanitized HTML
     */
    public static function sanitize_html($input, $allowed_tags = []) {
        if (empty($allowed_tags)) {
            // Default allowed tags for basic formatting
            $allowed_tags = [
                'p' => [],
                'br' => [],
                'strong' => [],
                'em' => [],
                'u' => [],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'a' => ['href', 'target', 'rel'],
                'span' => ['class'],
                'div' => ['class']
            ];
        }
        
        return wp_kses($input, $allowed_tags);
    }
    
    /**
     * Validate and sanitize JSON input
     * 
     * @param string $input The JSON string
     * @return array|false Decoded and sanitized array or false if invalid
     */
    public static function sanitize_json($input) {
        $decoded = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return self::sanitize_array($decoded);
    }
    
    /**
     * Validate and sanitize date input
     * 
     * @param string $input The date string
     * @param string $format Expected date format
     * @return string|false Validated date or false if invalid
     */
    public static function sanitize_date($input, $format = 'Y-m-d') {
        $date = sanitize_text_field($input);
        
        $datetime = DateTime::createFromFormat($format, $date);
        
        if ($datetime === false) {
            return false;
        }
        
        return $datetime->format($format);
    }
    
    /**
     * Validate and sanitize phone number
     * 
     * @param string $input The phone number
     * @return string|false Sanitized phone number or false if invalid
     */
    public static function sanitize_phone($input) {
        $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $input);
        $phone = trim($phone);
        
        if (strlen($phone) < 7) {
            return false;
        }
        
        return $phone;
    }
    
    /**
     * Validate and sanitize postal code
     * 
     * @param string $input The postal code
     * @return string|false Sanitized postal code or false if invalid
     */
    public static function sanitize_postal_code($input) {
        $postal_code = preg_replace('/[^A-Za-z0-9\-\s]/', '', $input);
        $postal_code = trim($postal_code);
        
        if (strlen($postal_code) < 3) {
            return false;
        }
        
        return $postal_code;
    }
} 