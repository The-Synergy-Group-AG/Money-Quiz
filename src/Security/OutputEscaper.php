<?php
/**
 * Output Escaper
 *
 * Context-aware output escaping to prevent XSS.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Output escaper class.
 *
 * @since 7.0.0
 */
class OutputEscaper {
    
    /**
     * Escape HTML output.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    public static function html(string $text): string {
        return esc_html($text);
    }
    
    /**
     * Escape attribute output.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    public static function attr(string $text): string {
        return esc_attr($text);
    }
    
    /**
     * Escape URL output.
     *
     * @param string $url URL to escape.
     * @return string Escaped URL.
     */
    public static function url(string $url): string {
        return esc_url($url);
    }
    
    /**
     * Escape JavaScript output.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    public static function js(string $text): string {
        return esc_js($text);
    }
    
    /**
     * Escape textarea content.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    public static function textarea(string $text): string {
        return esc_textarea($text);
    }
    
    /**
     * Escape SQL identifiers.
     *
     * @param string $identifier SQL identifier.
     * @return string Escaped identifier.
     */
    public static function sql_identifier(string $identifier): string {
        // Remove all non-alphanumeric characters except underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }
    
    /**
     * Escape for use in JSON.
     *
     * @param mixed $data Data to encode.
     * @return string JSON string.
     */
    public static function json($data): string {
        return wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * Escape for inline CSS.
     *
     * @param string $css CSS to escape.
     * @return string Escaped CSS.
     */
    public static function css(string $css): string {
        // Remove any potential CSS injection
        $css = preg_replace('/[<>]/', '', $css);
        $css = str_replace(['\\', '"', "'"], '', $css);
        return $css;
    }
    
    /**
     * Escape array of attributes.
     *
     * @param array $attrs Attributes array.
     * @return string Escaped attributes string.
     */
    public static function attrs(array $attrs): string {
        $output = '';
        foreach ($attrs as $key => $value) {
            $key = self::attr($key);
            
            if (is_bool($value)) {
                if ($value) {
                    $output .= " $key";
                }
            } else {
                $value = self::attr($value);
                $output .= " $key=\"$value\"";
            }
        }
        return $output;
    }
    
    /**
     * Escape and format HTML with allowed tags.
     *
     * @param string $html    HTML to escape.
     * @param array  $allowed Allowed tags.
     * @return string Escaped HTML.
     */
    public static function rich_text(string $html, array $allowed = []): string {
        if (empty($allowed)) {
            $allowed = [
                'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
                'br' => [],
                'em' => [],
                'strong' => [],
                'b' => [],
                'i' => [],
                'p' => ['class' => []],
                'span' => ['class' => []],
                'div' => ['class' => []],
                'ul' => ['class' => []],
                'ol' => ['class' => []],
                'li' => [],
                'blockquote' => ['cite' => []],
                'code' => [],
                'pre' => [],
            ];
        }
        
        return wp_kses($html, $allowed);
    }
    
    /**
     * Escape for XML output.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    public static function xml(string $text): string {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Context-aware automatic escaping.
     *
     * @param mixed  $value   Value to escape.
     * @param string $context Output context.
     * @return string Escaped value.
     */
    public static function auto($value, string $context = 'html'): string {
        $value = (string) $value;
        
        switch ($context) {
            case 'html':
                return self::html($value);
                
            case 'attr':
            case 'attribute':
                return self::attr($value);
                
            case 'url':
                return self::url($value);
                
            case 'js':
            case 'javascript':
                return self::js($value);
                
            case 'css':
                return self::css($value);
                
            case 'json':
                return self::json($value);
                
            case 'xml':
                return self::xml($value);
                
            case 'textarea':
                return self::textarea($value);
                
            case 'rich':
            case 'rich_text':
                return self::rich_text($value);
                
            default:
                return self::html($value);
        }
    }
}