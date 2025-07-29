<?php
/**
 * Legacy Input Sanitizer
 * 
 * Provides centralized input validation for legacy code
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Security;

class Legacy_Input_Sanitizer {
    
    /**
     * @var array Sanitization rules by field type
     */
    private $sanitization_rules = [
        'email' => 'sanitize_email',
        'text' => 'sanitize_text_field',
        'textarea' => 'sanitize_textarea_field',
        'number' => 'absint',
        'float' => 'floatval',
        'url' => 'esc_url_raw',
        'key' => 'sanitize_key',
        'title' => 'sanitize_title',
        'html' => 'wp_kses_post',
        'none' => null
    ];
    
    /**
     * @var array Known field mappings
     */
    private $field_types = [
        // User input fields
        'first_name' => 'text',
        'last_name' => 'text',
        'full_name' => 'text',
        'email' => 'email',
        'phone' => 'text',
        'company' => 'text',
        'message' => 'textarea',
        
        // Quiz fields
        'quiz_id' => 'number',
        'question_id' => 'number',
        'answer' => 'text',
        'score' => 'float',
        'archetype_id' => 'number',
        
        // Settings fields
        'mq_setting_*' => 'text',
        'mq_email_*' => 'email',
        'mq_number_*' => 'number',
        
        // Action fields
        'action' => 'key',
        'page' => 'key',
        'tab' => 'key',
        '_wpnonce' => 'none',
        '_wp_http_referer' => 'url'
    ];
    
    /**
     * Sanitize all inputs from a request
     * 
     * @param array $input Raw input array ($_POST, $_GET, etc)
     * @param array $custom_rules Optional custom sanitization rules
     * @return array Sanitized input
     */
    public function sanitize_request( $input, $custom_rules = [] ) {
        $sanitized = [];
        
        foreach ( $input as $key => $value ) {
            // Skip if empty
            if ( empty( $value ) && $value !== '0' ) {
                $sanitized[ $key ] = $value;
                continue;
            }
            
            // Handle arrays recursively
            if ( is_array( $value ) ) {
                $sanitized[ $key ] = $this->sanitize_request( $value, $custom_rules );
                continue;
            }
            
            // Apply sanitization
            $sanitized[ $key ] = $this->sanitize_field( $key, $value, $custom_rules );
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize a single field
     * 
     * @param string $field_name Field name
     * @param mixed  $value Field value
     * @param array  $custom_rules Custom rules
     * @return mixed Sanitized value
     */
    public function sanitize_field( $field_name, $value, $custom_rules = [] ) {
        // Check custom rules first
        if ( isset( $custom_rules[ $field_name ] ) ) {
            return $this->apply_sanitization( $value, $custom_rules[ $field_name ] );
        }
        
        // Check exact field match
        if ( isset( $this->field_types[ $field_name ] ) ) {
            $type = $this->field_types[ $field_name ];
            return $this->apply_sanitization( $value, $type );
        }
        
        // Check pattern matches
        foreach ( $this->field_types as $pattern => $type ) {
            if ( strpos( $pattern, '*' ) !== false ) {
                $regex = str_replace( '*', '.*', $pattern );
                if ( preg_match( "/^{$regex}$/", $field_name ) ) {
                    return $this->apply_sanitization( $value, $type );
                }
            }
        }
        
        // Default to text sanitization
        return sanitize_text_field( $value );
    }
    
    /**
     * Apply sanitization based on type
     * 
     * @param mixed  $value Value to sanitize
     * @param string $type Sanitization type
     * @return mixed Sanitized value
     */
    private function apply_sanitization( $value, $type ) {
        // Handle callback sanitization
        if ( is_callable( $type ) ) {
            return call_user_func( $type, $value );
        }
        
        // Handle predefined types
        if ( isset( $this->sanitization_rules[ $type ] ) ) {
            $function = $this->sanitization_rules[ $type ];
            if ( $function === null ) {
                return $value; // No sanitization
            }
            return call_user_func( $function, $value );
        }
        
        // Default to text field
        return sanitize_text_field( $value );
    }
    
    /**
     * Validate and sanitize quiz answers
     * 
     * @param array $answers Raw answer data
     * @return array Sanitized answers
     */
    public function sanitize_quiz_answers( $answers ) {
        $sanitized = [];
        
        foreach ( $answers as $question_id => $answer ) {
            $clean_id = absint( $question_id );
            if ( $clean_id > 0 ) {
                $sanitized[ $clean_id ] = sanitize_text_field( $answer );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate email with additional checks
     * 
     * @param string $email Email to validate
     * @return string|false Sanitized email or false if invalid
     */
    public function validate_email( $email ) {
        $email = sanitize_email( $email );
        
        if ( ! is_email( $email ) ) {
            return false;
        }
        
        // Additional validation
        if ( strlen( $email ) > 254 ) { // RFC 5321
            return false;
        }
        
        // Check for common test emails
        $blocked_patterns = [
            '/^test@test\.com$/i',
            '/^admin@admin\.com$/i',
            '/^user@example\.com$/i',
            '/^noreply@.*$/i'
        ];
        
        foreach ( $blocked_patterns as $pattern ) {
            if ( preg_match( $pattern, $email ) ) {
                return false;
            }
        }
        
        return $email;
    }
    
    /**
     * Escape output based on context
     * 
     * @param mixed  $value Value to escape
     * @param string $context Output context (html, attr, js, url)
     * @return string Escaped value
     */
    public function escape_output( $value, $context = 'html' ) {
        switch ( $context ) {
            case 'html':
                return esc_html( $value );
            case 'attr':
                return esc_attr( $value );
            case 'js':
                return esc_js( $value );
            case 'url':
                return esc_url( $value );
            case 'textarea':
                return esc_textarea( $value );
            default:
                return esc_html( $value );
        }
    }
    
    /**
     * Add field type mapping
     * 
     * @param string $field_name Field name or pattern
     * @param string $type Sanitization type
     */
    public function add_field_type( $field_name, $type ) {
        $this->field_types[ $field_name ] = $type;
    }
    
    /**
     * Get sanitization report for debugging
     * 
     * @param array $input Input to analyze
     * @return array Report of what would be sanitized
     */
    public function get_sanitization_report( $input ) {
        $report = [];
        
        foreach ( $input as $key => $value ) {
            if ( is_array( $value ) ) {
                $report[ $key ] = $this->get_sanitization_report( $value );
            } else {
                $type = 'text'; // default
                
                // Determine type
                if ( isset( $this->field_types[ $key ] ) ) {
                    $type = $this->field_types[ $key ];
                } else {
                    foreach ( $this->field_types as $pattern => $field_type ) {
                        if ( strpos( $pattern, '*' ) !== false ) {
                            $regex = str_replace( '*', '.*', $pattern );
                            if ( preg_match( "/^{$regex}$/", $key ) ) {
                                $type = $field_type;
                                break;
                            }
                        }
                    }
                }
                
                $report[ $key ] = [
                    'original' => $value,
                    'type' => $type,
                    'sanitized' => $this->sanitize_field( $key, $value ),
                    'changed' => $value !== $this->sanitize_field( $key, $value )
                ];
            }
        }
        
        return $report;
    }
}

// Global helper functions for easy legacy code updates
if ( ! function_exists( 'mq_sanitize_input' ) ) {
    function mq_sanitize_input( $input, $custom_rules = [] ) {
        static $sanitizer = null;
        if ( null === $sanitizer ) {
            $sanitizer = new Legacy_Input_Sanitizer();
        }
        return $sanitizer->sanitize_request( $input, $custom_rules );
    }
}

if ( ! function_exists( 'mq_sanitize_field' ) ) {
    function mq_sanitize_field( $field_name, $value ) {
        static $sanitizer = null;
        if ( null === $sanitizer ) {
            $sanitizer = new Legacy_Input_Sanitizer();
        }
        return $sanitizer->sanitize_field( $field_name, $value );
    }
}

if ( ! function_exists( 'mq_escape' ) ) {
    function mq_escape( $value, $context = 'html' ) {
        static $sanitizer = null;
        if ( null === $sanitizer ) {
            $sanitizer = new Legacy_Input_Sanitizer();
        }
        return $sanitizer->escape_output( $value, $context );
    }
}