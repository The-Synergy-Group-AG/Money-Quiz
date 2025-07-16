<?php
/**
 * XSS Output Encoding
 * 
 * @package MoneyQuiz\Security\XSS
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\XSS;

/**
 * Output Encoding Helper
 */
class OutputEncoder implements XssContext {
    
    /**
     * Encode for HTML context
     */
    public static function html($input) {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Encode for HTML attribute context
     */
    public static function attribute($input) {
        $encoded = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Additional encoding for non-alphanumeric characters
        return preg_replace_callback('/[^a-zA-Z0-9\s\-\_\.]/', function($matches) {
            return '&#x' . bin2hex($matches[0]) . ';';
        }, $encoded);
    }
    
    /**
     * Encode for JavaScript string context
     */
    public static function js($input) {
        $filter = new JavaScriptFilter();
        return $filter->escape($input);
    }
    
    /**
     * Encode for CSS context
     */
    public static function css($input) {
        $filter = new CssFilter();
        return $filter->escape($input);
    }
    
    /**
     * Encode for URL context
     */
    public static function url($input) {
        return rawurlencode($input);
    }
    
    /**
     * Context-aware encoding
     */
    public static function encode($input, $context = self::HTML) {
        switch ($context) {
            case self::HTML:
                return self::html($input);
            case self::ATTRIBUTE:
            case self::HTML_ATTR:
                return self::attribute($input);
            case self::JAVASCRIPT:
                return self::js($input);
            case self::CSS:
                return self::css($input);
            case self::URL:
                return self::url($input);
            default:
                return self::html($input);
        }
    }
    
    /**
     * Create safe HTML output
     */
    public static function safeHtml($tag, $content, $attributes = []) {
        $safe_tag = preg_replace('/[^a-zA-Z0-9]/', '', $tag);
        $safe_content = self::html($content);
        
        $attr_string = '';
        foreach ($attributes as $name => $value) {
            $safe_name = preg_replace('/[^a-zA-Z0-9\-\_]/', '', $name);
            $safe_value = self::attribute($value);
            $attr_string .= " {$safe_name}=\"{$safe_value}\"";
        }
        
        return "<{$safe_tag}{$attr_string}>{$safe_content}</{$safe_tag}>";
    }
    
    /**
     * Create safe JSON output
     */
    public static function safeJson($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * WordPress integration helpers
     */
    public static function wp($input, $context = self::HTML) {
        switch ($context) {
            case self::HTML:
                return esc_html($input);
            case self::ATTRIBUTE:
                return esc_attr($input);
            case self::JAVASCRIPT:
                return esc_js($input);
            case self::URL:
                return esc_url($input);
            default:
                return esc_html($input);
        }
    }
}

/**
 * URL Filter Implementation
 */
class UrlFilter extends BaseXssFilter {
    
    private $allowed_protocols = ['http', 'https', 'mailto'];
    
    public function filter($input, $context = 'url') {
        $url = trim($input);
        
        // Parse URL
        $parsed = parse_url($url);
        if ($parsed === false) {
            return '';
        }
        
        // Check protocol
        if (isset($parsed['scheme']) && !in_array($parsed['scheme'], $this->allowed_protocols)) {
            return '';
        }
        
        // Rebuild safe URL
        return $this->rebuildUrl($parsed);
    }
    
    public function escape($input, $context = 'url') {
        return esc_url($input, $this->allowed_protocols);
    }
    
    public function validate($input) {
        $url = trim($input);
        
        // Check for dangerous protocols
        if (preg_match('/^(javascript|vbscript|data):/i', $url)) {
            return false;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    private function rebuildUrl($parts) {
        $url = '';
        
        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }
        
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        
        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }
        
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        
        return $url;
    }
}