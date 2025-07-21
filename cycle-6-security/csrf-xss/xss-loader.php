<?php
/**
 * XSS Protection Loader
 * 
 * @package MoneyQuiz\Security\XSS
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\XSS;

// Load all XSS components
require_once __DIR__ . '/xss-1-core-filters.php';
require_once __DIR__ . '/xss-2-html-sanitizer.php';
require_once __DIR__ . '/xss-3-js-sanitizer.php';
require_once __DIR__ . '/xss-4-css-sanitizer.php';
require_once __DIR__ . '/xss-5-output-encoding.php';

/**
 * XSS Protection Manager
 */
class XssProtection {
    
    private static $filters = [];
    
    /**
     * Initialize XSS protection
     */
    public static function init() {
        // Register filters
        self::registerFilters();
        
        // Add WordPress hooks
        self::addHooks();
        
        // Register helper functions
        self::registerHelpers();
    }
    
    /**
     * Register all filters
     */
    private static function registerFilters() {
        self::$filters['html'] = new HtmlFilter();
        self::$filters['javascript'] = new JavaScriptFilter();
        self::$filters['css'] = new CssFilter();
        self::$filters['url'] = new UrlFilter();
    }
    
    /**
     * Add WordPress hooks
     */
    private static function addHooks() {
        // Content filtering
        add_filter('money_quiz_content', [__CLASS__, 'filterContent'], 10, 2);
        add_filter('money_quiz_output', [__CLASS__, 'filterOutput'], 10, 2);
        
        // Script filtering
        add_filter('money_quiz_inline_script', [__CLASS__, 'filterScript']);
        add_filter('money_quiz_inline_style', [__CLASS__, 'filterStyle']);
    }
    
    /**
     * Filter content
     */
    public static function filterContent($content, $context = 'html') {
        $filter = self::getFilter($context);
        return $filter->filter($content, $context);
    }
    
    /**
     * Filter output
     */
    public static function filterOutput($output, $context = 'html') {
        return OutputEncoder::encode($output, $context);
    }
    
    /**
     * Filter script
     */
    public static function filterScript($script) {
        $filter = self::getFilter('javascript');
        return $filter->filter($script);
    }
    
    /**
     * Filter style
     */
    public static function filterStyle($style) {
        $filter = self::getFilter('css');
        return $filter->filter($style);
    }
    
    /**
     * Get filter instance
     */
    public static function getFilter($context) {
        return self::$filters[$context] ?? self::$filters['html'];
    }
    
    /**
     * Register helper functions
     */
    private static function registerHelpers() {
        if (!function_exists('money_quiz_esc')) {
            function money_quiz_esc($input, $context = 'html') {
                return OutputEncoder::encode($input, $context);
            }
        }
        
        if (!function_exists('money_quiz_filter_xss')) {
            function money_quiz_filter_xss($input, $context = 'html') {
                return XssProtection::filterContent($input, $context);
            }
        }
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [XssProtection::class, 'init']);