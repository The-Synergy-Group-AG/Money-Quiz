<?php
/**
 * XSS Core Filters and Interfaces
 * 
 * @package MoneyQuiz\Security\XSS
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\XSS;

/**
 * XSS Filter Interface
 */
interface XssFilterInterface {
    public function filter($input, $context = 'html');
    public function escape($input, $context = 'html');
    public function validate($input);
}

/**
 * XSS Context Constants
 */
interface XssContext {
    const HTML = 'html';
    const ATTRIBUTE = 'attribute';
    const JAVASCRIPT = 'javascript';
    const CSS = 'css';
    const URL = 'url';
    const HTML_ATTR = 'html_attr';
}

/**
 * Base XSS Filter
 */
abstract class BaseXssFilter implements XssFilterInterface, XssContext {
    
    protected $encoding = 'UTF-8';
    protected $allowed_tags = [];
    protected $allowed_attributes = [];
    
    /**
     * Get filter for context
     */
    public static function getFilter($context = self::HTML) {
        switch ($context) {
            case self::HTML:
                return new HtmlFilter();
            case self::JAVASCRIPT:
                return new JavaScriptFilter();
            case self::CSS:
                return new CssFilter();
            case self::URL:
                return new UrlFilter();
            default:
                return new HtmlFilter();
        }
    }
    
    /**
     * Common dangerous patterns
     */
    protected function getDangerousPatterns() {
        return [
            '/<script[^>]*>.*?<\/script>/is',
            '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/data\s*:\s*text\/html/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<applet[^>]*>/i',
            '/<meta[^>]*>/i',
            '/<link[^>]*>/i',
            '/<style[^>]*>.*?<\/style>/is'
        ];
    }
}