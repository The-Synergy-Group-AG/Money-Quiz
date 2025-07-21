<?php
/**
 * PHPUnit bootstrap file for Money Quiz tests
 */

// Define test constants
define('MONEYQUIZ_TESTING', true);
define('WP_DEBUG', true);

// Mock WordPress functions that don't exist in testing
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text);
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars($text);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs(intval($value));
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($data) {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $data[$k] = esc_sql($v);
                } else {
                    $data[$k] = addslashes($v);
                }
            }
        } else {
            $data = addslashes($data);
        }
        return $data;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        if (preg_match('/^javascript:/i', $url)) {
            return '';
        }
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return str_replace(array("\r", "\n", '<', '>', '&', '"', "'"), 
                          array('\r', '\n', '\x3c', '\x3e', '\x26', '\x22', '\x27'), 
                          $text);
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($content, $allowed_html) {
        // Simple mock implementation
        return strip_tags($content, '<' . implode('><', array_keys($allowed_html)) . '>');
    }
}

if (!function_exists('current_time')) {
    function current_time($format) {
        if ($format === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'http://example.com' . $path;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl() {
        return false;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'nonce_' . md5($action);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        $options = array(
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s'
        );
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration) {
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '') {
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        throw new Exception($message);
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = null) {
        return date($format, $timestamp ?: time());
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'test_salt_' . $scheme;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        // Mock implementation
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        // Mock implementation
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->add($code, $message, $data);
            }
        }
        
        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return !empty($codes) ? $codes[0] : '';
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }
    }
}

// Mock wpdb class
if (!class_exists('wpdb')) {
    class wpdb {
        public $last_error = '';
        public $last_query = '';
        public $insert_id = 0;
        
        public function prepare($query, ...$args) {
            return vsprintf($query, $args);
        }
        
        public function get_results($query, $output = OBJECT) {
            return array();
        }
        
        public function get_row($query, $output = OBJECT, $y = 0) {
            return null;
        }
        
        public function get_var($query = null, $x = 0, $y = 0) {
            return null;
        }
        
        public function query($query) {
            $this->last_query = $query;
            return 0;
        }
        
        public function insert($table, $data, $format = null) {
            $this->insert_id = rand(1, 1000);
            return 1;
        }
        
        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }
        
        public function delete($table, $where, $where_format = null) {
            return 1;
        }
        
        public function suppress_errors($suppress = true) {
            return true;
        }
        
        public function show_errors($show = true) {
            return true;
        }
        
        public function hide_errors() {
            return true;
        }
    }
}

// Define constants
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}

if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '.example.com');
}

// Include the actual plugin files
$plugin_root = dirname(dirname(dirname(__DIR__)));
$cycle_root = dirname(__DIR__);

// Include Cycle 1 patches
require_once $cycle_root . '/../cycle-1-security-patches/sql-injection/worker-1-quiz-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/sql-injection/worker-2-class-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/sql-injection/worker-3-ajax-admin-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/xss-prevention/worker-4-frontend-xss-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/xss-prevention/worker-5-admin-xss-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/csrf-protection/worker-6-form-csrf-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/csrf-protection/worker-7-ajax-csrf-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/credential-security/worker-8-credential-patches.php';
require_once $cycle_root . '/../cycle-1-security-patches/access-control/worker-9-access-control-patches.php';

// Include Cycle 2 patches
require_once $cycle_root . '/error-handling/worker-1-frontend-error-handling.php';
require_once $cycle_root . '/error-handling/worker-2-admin-error-handling.php';
require_once $cycle_root . '/bug-fixes/worker-3-critical-bugs.php';
require_once $cycle_root . '/bug-fixes/worker-4-warning-fixes.php';
require_once $cycle_root . '/bug-fixes/worker-5-deprecation-fixes.php';
require_once $cycle_root . '/input-validation/worker-6-frontend-validation.php';
require_once $cycle_root . '/input-validation/worker-7-admin-validation.php';

// Define table constants
define('TABLE_MQ_PROSPECTS', 'mq_prospects');
define('TABLE_MQ_TAKEN', 'mq_taken');
define('TABLE_MQ_RESULTS', 'mq_results');
define('TABLE_MQ_MASTER', 'mq_master');
define('TABLE_MQ_ARCHETYPES', 'mq_archetypes');

// Autoloader for test classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});