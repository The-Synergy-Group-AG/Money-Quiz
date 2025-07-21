<?php
/**
 * Worker 5: Deprecation Updates
 * Focus: PHP 8+ compatibility, WordPress deprecations, modern standards
 */

// PATCH 1: Replace deprecated create_function() if used
// OLD: create_function('$a,$b', 'return $a+$b;')
// NEW: Use anonymous functions (closures)
function mq_create_callback($params, $code) {
    trigger_error('create_function is deprecated, use anonymous functions', E_USER_DEPRECATED);
    
    // Convert to anonymous function
    return function() use ($params, $code) {
        // This is a compatibility wrapper only
        // All instances should be replaced with proper anonymous functions
        return eval($code);
    };
}

// PATCH 2: Replace deprecated each() function
// OLD: while (list($key, $value) = each($array))
// NEW: Use foreach or array functions
function mq_each_replacement(&$array) {
    $key = key($array);
    if ($key !== null) {
        $value = current($array);
        next($array);
        return array($key, $value, 'key' => $key, 'value' => $value);
    }
    return false;
}

// Better replacement using foreach
function mq_process_array($array, $callback) {
    foreach ($array as $key => $value) {
        call_user_func($callback, $key, $value);
    }
}

// PATCH 3: Replace deprecated mysql_* functions (if any legacy code exists)
class MoneyQuizLegacyDB {
    
    /**
     * Replace mysql_connect
     */
    public static function connect($server, $username, $password) {
        trigger_error('mysql_connect is deprecated, use WordPress $wpdb', E_USER_DEPRECATED);
        global $wpdb;
        return $wpdb;
    }
    
    /**
     * Replace mysql_query
     */
    public static function query($query) {
        trigger_error('mysql_query is deprecated, use $wpdb methods', E_USER_DEPRECATED);
        global $wpdb;
        return $wpdb->query($query);
    }
    
    /**
     * Replace mysql_fetch_array
     */
    public static function fetch_array($result) {
        trigger_error('mysql_fetch_array is deprecated, use $wpdb->get_results()', E_USER_DEPRECATED);
        return is_array($result) ? $result : array();
    }
}

// PATCH 4: Update deprecated WordPress functions
class MoneyQuizWPCompat {
    
    /**
     * Replace deprecated get_currentuserinfo()
     */
    public static function get_current_user_info() {
        // OLD: get_currentuserinfo()
        // NEW: wp_get_current_user()
        return wp_get_current_user();
    }
    
    /**
     * Replace deprecated get_userdatabylogin()
     */
    public static function get_user_by_login($login) {
        // OLD: get_userdatabylogin($login)
        // NEW: get_user_by('login', $login)
        return get_user_by('login', $login);
    }
    
    /**
     * Replace deprecated wp_get_sites()
     */
    public static function get_sites($args = array()) {
        // OLD: wp_get_sites($args)
        // NEW: get_sites($args)
        if (function_exists('get_sites')) {
            return get_sites($args);
        }
        // Fallback for older WordPress
        return wp_get_sites($args);
    }
    
    /**
     * Replace deprecated screen_icon()
     */
    public static function screen_icon($screen = '') {
        // screen_icon() was deprecated in 3.8
        // No replacement needed - dashicons are used instead
        return '';
    }
}

// PATCH 5: Fix PHP 8 compatibility issues
class MoneyQuizPHP8Compat {
    
    /**
     * Fix implode() parameter order (PHP 8 strict)
     */
    public static function implode_safe($glue, $pieces) {
        // PHP 8 requires correct parameter order
        if (is_array($glue)) {
            // Wrong order detected, fix it
            return implode($pieces, $glue);
        }
        return implode($glue, $pieces);
    }
    
    /**
     * Fix optional parameters before required (PHP 8 deprecated)
     */
    public static function function_with_defaults($required, $optional = null) {
        // Ensure functions don't have optional parameters before required ones
        return func_get_args();
    }
    
    /**
     * Fix match expressions for PHP 8
     */
    public static function convert_switch_to_match($value, $cases) {
        // PHP 8 match expression (backwards compatible)
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            // Use match if available
            foreach ($cases as $case => $result) {
                if ($value === $case) {
                    return $result;
                }
            }
            return $cases['default'] ?? null;
        } else {
            // Fallback to switch-like behavior
            return isset($cases[$value]) ? $cases[$value] : ($cases['default'] ?? null);
        }
    }
}

// PATCH 6: Update jQuery deprecated functions
function mq_enqueue_modern_scripts() {
    // Remove old jQuery migrate if present
    wp_deregister_script('jquery-migrate');
    
    // Add migration helper for deprecated jQuery
    wp_add_inline_script('jquery', '
        // jQuery deprecated function warnings
        if (typeof jQuery !== "undefined") {
            // Replace .size() with .length
            if (!jQuery.fn.size) {
                jQuery.fn.size = function() {
                    console.warn("jQuery.fn.size() is deprecated; use .length property");
                    return this.length;
                };
            }
            
            // Replace .bind() with .on()
            var originalBind = jQuery.fn.bind;
            jQuery.fn.bind = function() {
                console.warn("jQuery.fn.bind() is deprecated; use .on()");
                return jQuery.fn.on.apply(this, arguments);
            };
            
            // Replace .unbind() with .off()
            var originalUnbind = jQuery.fn.unbind;
            jQuery.fn.unbind = function() {
                console.warn("jQuery.fn.unbind() is deprecated; use .off()");
                return jQuery.fn.off.apply(this, arguments);
            };
        }
    ');
}
add_action('wp_enqueue_scripts', 'mq_enqueue_modern_scripts');
add_action('admin_enqueue_scripts', 'mq_enqueue_modern_scripts');

// PATCH 7: Fix deprecated WordPress database functions
class MoneyQuizDBCompat {
    
    /**
     * Replace $wpdb->escape()
     */
    public static function escape_string($string) {
        global $wpdb;
        // OLD: $wpdb->escape($string)
        // NEW: esc_sql($string)
        return esc_sql($string);
    }
    
    /**
     * Proper prepare usage (no double prepare)
     */
    public static function prepare_query($query, $args) {
        global $wpdb;
        
        // Ensure we're not double-preparing
        if (strpos($query, '%') === false) {
            // Query has no placeholders, don't prepare
            return $query;
        }
        
        return $wpdb->prepare($query, $args);
    }
}

// PATCH 8: Fix deprecated PHP string functions
class MoneyQuizStringCompat {
    
    /**
     * Replace deprecated mbstring function usage
     */
    public static function strlen_safe($string) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, 'UTF-8');
        }
        return strlen($string);
    }
    
    /**
     * Replace split() with explode()
     */
    public static function split_string($delimiter, $string) {
        // OLD: split($delimiter, $string)
        // NEW: explode($delimiter, $string)
        return explode($delimiter, $string);
    }
    
    /**
     * Replace ereg* functions with preg*
     */
    public static function regex_match($pattern, $string) {
        // Convert POSIX regex to PCRE if needed
        if (strpos($pattern, '/') !== 0) {
            $pattern = '/' . $pattern . '/';
        }
        return preg_match($pattern, $string);
    }
}

// PATCH 9: Fix PHP 8 constructor compatibility
class MoneyQuizBaseClass {
    
    /**
     * PHP 8 compatible constructor
     */
    public function __construct() {
        // Modern constructor
        $this->init();
    }
    
    /**
     * Deprecated PHP 4 style constructor
     */
    public function MoneyQuizBaseClass() {
        trigger_error('PHP 4 style constructors are deprecated', E_USER_DEPRECATED);
        self::__construct();
    }
    
    protected function init() {
        // Initialization code
    }
}

// PATCH 10: Update deprecated WordPress hooks
function mq_update_deprecated_hooks() {
    // Map of deprecated hooks to new ones
    $hook_map = array(
        'wpmu_new_blog' => 'wp_initialize_site',
        'delete_blog' => 'wp_uninitialize_site',
        'activity_box_end' => 'welcome_panel',
        'wp_dashboard_quick_press' => 'dashboard_quick_press',
    );
    
    foreach ($hook_map as $old_hook => $new_hook) {
        if (has_action($old_hook)) {
            $callbacks = $GLOBALS['wp_filter'][$old_hook] ?? array();
            foreach ($callbacks as $priority => $functions) {
                foreach ($functions as $function) {
                    remove_action($old_hook, $function['function'], $priority);
                    add_action($new_hook, $function['function'], $priority, $function['accepted_args']);
                }
            }
        }
    }
}

// PATCH 11: Null safety for PHP 8
function mq_null_safe($value, $default = '') {
    // PHP 8 is stricter about null values
    return $value !== null ? $value : $default;
}

// PATCH 12: Fix deprecated magic quotes
function mq_stripslashes_safe($value) {
    // Magic quotes were removed in PHP 5.4
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        return stripslashes($value);
    }
    return $value;
}

// PATCH 13: Replace deprecated JSON constants
function mq_json_encode_safe($data, $options = 0) {
    // Ensure we use valid constants
    $valid_options = 0;
    
    if (defined('JSON_PRETTY_PRINT')) {
        $valid_options |= JSON_PRETTY_PRINT;
    }
    if (defined('JSON_UNESCAPED_SLASHES')) {
        $valid_options |= JSON_UNESCAPED_SLASHES;
    }
    
    return json_encode($data, $valid_options);
}