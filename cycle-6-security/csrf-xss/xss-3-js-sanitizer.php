<?php
/**
 * XSS JavaScript Sanitizer
 * 
 * @package MoneyQuiz\Security\XSS
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\XSS;

/**
 * JavaScript Filter Implementation
 */
class JavaScriptFilter extends BaseXssFilter {
    
    /**
     * JavaScript escape map
     */
    private $js_escape_map = [
        '\\' => '\\\\',
        '/' => '\\/',
        '"' => '\\"',
        "'" => "\\'",
        "\n" => '\\n',
        "\r" => '\\r',
        "\t" => '\\t',
        "\x08" => '\\b',
        "\x0C" => '\\f',
        '<' => '\\x3C',
        '>' => '\\x3E',
        '&' => '\\x26',
        '=' => '\\x3D',
        '-' => '\\x2D',
        ';' => '\\x3B',
        '+' => '\\x2B',
        '(' => '\\x28',
        ')' => '\\x29',
        '%' => '\\x25'
    ];
    
    /**
     * Filter JavaScript input
     */
    public function filter($input, $context = 'javascript') {
        if (!is_string($input)) {
            return '';
        }
        
        // Remove all HTML tags
        $input = strip_tags($input);
        
        // Escape for JavaScript context
        return $this->escapeJavaScript($input);
    }
    
    /**
     * Escape for JavaScript context
     */
    public function escape($input, $context = 'javascript') {
        return $this->escapeJavaScript($input);
    }
    
    /**
     * Validate JavaScript input
     */
    public function validate($input) {
        // Check for dangerous JavaScript patterns
        $dangerous_patterns = [
            '/eval\s*\(/i',
            '/new\s+Function\s*\(/i',
            '/setTimeout\s*\(/i',
            '/setInterval\s*\(/i',
            '/\.innerHTML\s*=/i',
            '/\.outerHTML\s*=/i',
            '/document\.write/i',
            '/document\.writeln/i',
            '/window\.location/i',
            '/document\.location/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Escape string for JavaScript
     */
    private function escapeJavaScript($input) {
        // First, encode using strtr for known escapes
        $escaped = strtr($input, $this->js_escape_map);
        
        // Then handle Unicode characters
        $escaped = preg_replace_callback('/[^\x20-\x7E]/', function($matches) {
            $char = $matches[0];
            $hex = bin2hex($char);
            
            // Handle UTF-8 multibyte characters
            if (strlen($hex) <= 4) {
                return '\\u' . str_pad($hex, 4, '0', STR_PAD_LEFT);
            } else {
                // For characters outside BMP, use surrogate pairs
                $codepoint = mb_ord($char, 'UTF-8');
                if ($codepoint > 0xFFFF) {
                    $high = floor(($codepoint - 0x10000) / 0x400) + 0xD800;
                    $low = (($codepoint - 0x10000) % 0x400) + 0xDC00;
                    return sprintf('\\u%04X\\u%04X', $high, $low);
                }
                return '\\u' . str_pad(dechex($codepoint), 4, '0', STR_PAD_LEFT);
            }
        }, $escaped);
        
        return $escaped;
    }
    
    /**
     * Create safe JavaScript variable
     */
    public function createSafeVariable($name, $value) {
        // Validate variable name
        if (!preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid JavaScript variable name');
        }
        
        // Escape the value
        $escaped_value = $this->escape($value);
        
        return "var {$name} = \"{$escaped_value}\";";
    }
}