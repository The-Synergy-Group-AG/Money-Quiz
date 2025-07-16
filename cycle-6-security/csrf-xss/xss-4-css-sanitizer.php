<?php
/**
 * XSS CSS Sanitizer
 * 
 * @package MoneyQuiz\Security\XSS
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\XSS;

/**
 * CSS Filter Implementation
 */
class CssFilter extends BaseXssFilter {
    
    /**
     * Allowed CSS properties
     */
    private $allowed_properties = [
        'color', 'background-color', 'font-size', 'font-weight',
        'font-style', 'font-family', 'text-decoration', 'text-align',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-width', 'border-color', 'border-style',
        'display', 'width', 'height', 'max-width', 'max-height',
        'min-width', 'min-height', 'line-height', 'vertical-align',
        'float', 'clear', 'position', 'top', 'right', 'bottom', 'left',
        'z-index', 'overflow', 'opacity', 'visibility'
    ];
    
    /**
     * Filter CSS input
     */
    public function filter($input, $context = 'css') {
        if (!is_string($input)) {
            return '';
        }
        
        // Remove comments
        $input = preg_replace('/\/\*.*?\*\//s', '', $input);
        
        // Remove @import and other dangerous at-rules
        $input = preg_replace('/@import[^;]+;/i', '', $input);
        $input = preg_replace('/@charset[^;]+;/i', '', $input);
        
        // Remove javascript: and expression()
        $input = preg_replace('/javascript\s*:/i', '', $input);
        $input = preg_replace('/expression\s*\(/i', '', $input);
        
        // Parse and filter CSS rules
        return $this->filterCssRules($input);
    }
    
    /**
     * Escape CSS output
     */
    public function escape($input, $context = 'css') {
        // Remove any non-alphanumeric characters except specific CSS chars
        $escaped = preg_replace('/[^a-zA-Z0-9\s\-\_\.\#\:\;\,\%\(\)]/i', '', $input);
        
        // Additional escaping for quotes
        $escaped = str_replace('"', '\\"', $escaped);
        $escaped = str_replace("'", "\\'", $escaped);
        
        return $escaped;
    }
    
    /**
     * Validate CSS input
     */
    public function validate($input) {
        // Check for dangerous patterns
        $dangerous_patterns = [
            '/javascript:/i',
            '/expression\s*\(/i',
            '/@import/i',
            '/behavior\s*:/i',
            '/-moz-binding/i',
            '/binding\s*:/i',
            '/<script/i',
            '/vbscript:/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Filter CSS rules
     */
    private function filterCssRules($css) {
        $filtered_rules = [];
        
        // Simple CSS parser
        preg_match_all('/([^{]+)\{([^}]+)\}/i', $css, $matches);
        
        foreach ($matches[0] as $index => $rule) {
            $selector = trim($matches[1][$index]);
            $properties = trim($matches[2][$index]);
            
            // Filter selector
            $selector = $this->filterSelector($selector);
            if (empty($selector)) {
                continue;
            }
            
            // Filter properties
            $filtered_properties = $this->filterProperties($properties);
            if (empty($filtered_properties)) {
                continue;
            }
            
            $filtered_rules[] = $selector . ' { ' . $filtered_properties . ' }';
        }
        
        return implode("\n", $filtered_rules);
    }
    
    /**
     * Filter CSS selector
     */
    private function filterSelector($selector) {
        // Remove any JavaScript or dangerous selectors
        $selector = preg_replace('/[<>\"\'\/]/', '', $selector);
        
        // Limit selector complexity
        if (substr_count($selector, ' ') > 10) {
            return '';
        }
        
        return $selector;
    }
    
    /**
     * Filter CSS properties
     */
    private function filterProperties($properties) {
        $filtered = [];
        $props = explode(';', $properties);
        
        foreach ($props as $prop) {
            if (strpos($prop, ':') === false) {
                continue;
            }
            
            list($name, $value) = explode(':', $prop, 2);
            $name = trim(strtolower($name));
            $value = trim($value);
            
            // Check if property is allowed
            if (in_array($name, $this->allowed_properties)) {
                // Validate property value
                if ($this->validatePropertyValue($name, $value)) {
                    $filtered[] = $name . ': ' . $value;
                }
            }
        }
        
        return implode('; ', $filtered);
    }
    
    /**
     * Validate property value
     */
    private function validatePropertyValue($property, $value) {
        // Remove any JavaScript
        if (preg_match('/javascript:|expression\s*\(|@import/i', $value)) {
            return false;
        }
        
        return true;
    }
}