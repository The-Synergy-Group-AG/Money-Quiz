<?php
/**
 * XSS HTML Sanitizer
 * 
 * @package MoneyQuiz\Security\XSS
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\XSS;

/**
 * HTML Filter Implementation
 */
class HtmlFilter extends BaseXssFilter {
    
    public function __construct() {
        $this->allowed_tags = [
            'p', 'br', 'strong', 'em', 'u', 'i', 'b',
            'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'a', 'img', 'span', 'div'
        ];
        
        $this->allowed_attributes = [
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'span' => ['class', 'style'],
            'div' => ['class', 'style']
        ];
    }
    
    /**
     * Filter HTML input
     */
    public function filter($input, $context = 'html') {
        if (!is_string($input)) {
            return '';
        }
        
        // Remove dangerous patterns
        foreach ($this->getDangerousPatterns() as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        // Use WordPress kses for additional filtering
        $allowed_html = $this->getAllowedHtml();
        $filtered = wp_kses($input, $allowed_html);
        
        // Additional encoding for special characters
        $filtered = $this->encodeSpecialChars($filtered);
        
        return $filtered;
    }
    
    /**
     * Escape HTML output
     */
    public function escape($input, $context = 'html') {
        switch ($context) {
            case self::HTML:
                return esc_html($input);
            case self::ATTRIBUTE:
                return esc_attr($input);
            case self::HTML_ATTR:
                return esc_attr($input);
            default:
                return esc_html($input);
        }
    }
    
    /**
     * Validate HTML input
     */
    public function validate($input) {
        // Check for common XSS patterns
        $dangerous_patterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+=/i',
            '/<iframe/i',
            '/<object/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get allowed HTML for wp_kses
     */
    private function getAllowedHtml() {
        $allowed = [];
        
        foreach ($this->allowed_tags as $tag) {
            if (isset($this->allowed_attributes[$tag])) {
                $attrs = [];
                foreach ($this->allowed_attributes[$tag] as $attr) {
                    $attrs[$attr] = true;
                }
                $allowed[$tag] = $attrs;
            } else {
                $allowed[$tag] = [];
            }
        }
        
        return $allowed;
    }
    
    /**
     * Encode special characters
     */
    private function encodeSpecialChars($input) {
        $chars = [
            '\x00', '\x01', '\x02', '\x03', '\x04',
            '\x05', '\x06', '\x07', '\x08', '\x0B',
            '\x0C', '\x0E', '\x0F'
        ];
        
        return str_replace($chars, '', $input);
    }
}