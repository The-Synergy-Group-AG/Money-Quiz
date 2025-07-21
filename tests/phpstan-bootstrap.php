<?php
/**
 * PHPStan Bootstrap File
 * 
 * Defines WordPress constants and functions for static analysis
 */

// WordPress constants
define('ABSPATH', '/tmp/wordpress/');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('WP_CONTENT_URL', 'http://example.com/wp-content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
define('WPINC', 'wp-includes');

// Money Quiz specific constants
define('MONEYQUIZ_VERSION', '3.3.0');
define('MONEYQUIZ_PLUGIN_DIR', WP_PLUGIN_DIR . '/money-quiz');
define('MONEYQUIZ_PLUGIN_URL', WP_PLUGIN_URL . '/money-quiz');

// Database table constants
define('TABLE_MQ_PROSPECTS', 'mq_prospects');
define('TABLE_MQ_TAKEN', 'mq_taken');
define('TABLE_MQ_RESULTS', 'mq_results');
define('TABLE_MQ_MASTER', 'mq_master');
define('TABLE_MQ_ARCHETYPES', 'mq_archetypes');
define('TABLE_MQ_BLACKLIST', 'mq_blacklist');
define('TABLE_MQ_ACTIVITYLOG', 'mq_activitylog');
define('TABLE_MQ_CTA', 'mq_cta');
define('TABLE_MQ_VERSION', 'mq_version');
define('TABLE_MQ_MAILINGS', 'mq_mailings');
define('TABLE_MQ_MAILING_RUNS', 'mq_mailing_runs');
define('TABLE_MQ_MAILING_MESSAGES', 'mq_mailing_messages');
define('TABLE_MQ_QUESTIONS', 'mq_questions');
define('TABLE_MQ_ANSWERS', 'mq_answers');

// WordPress database class stub
class wpdb {
    public $prefix = 'wp_';
    public $last_error = '';
    public $last_query = '';
    public $insert_id = 0;
    
    public function prepare($query, ...$args) {
        return vsprintf($query, $args);
    }
    
    public function get_results($query, $output = OBJECT) {
        return [];
    }
    
    public function get_row($query, $output = OBJECT, $y = 0) {
        return null;
    }
    
    public function get_var($query = null, $x = 0, $y = 0) {
        return null;
    }
    
    public function query($query) {
        return 0;
    }
    
    public function insert($table, $data, $format = null) {
        return 1;
    }
    
    public function update($table, $data, $where, $format = null, $where_format = null) {
        return 1;
    }
    
    public function delete($table, $where, $where_format = null) {
        return 1;
    }
}

// Global WordPress database object
$wpdb = new wpdb();

// Common WordPress functions that might be used
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $field = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce($action) . '" />';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'nonce_' . md5($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        return true;
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

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function, $priority = 10, $accepted_args = 1) {
        // Stub
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        // Stub
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function, $priority = 10, $accepted_args = 1) {
        // Stub
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        die($message);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'http://example.com/wp-admin/' . $path;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) {
        return 'http://example.com/' . $path;
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') {
        return 'http://example.com/wp-content/plugins/' . $path;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        // Stub
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        // Stub
    }
}

// Define WordPress constants used in conditions
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', false);
}

if (!defined('DOING_CRON')) {
    define('DOING_CRON', false);
}

if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', false);
}

// Include the actual plugin constants and basic setup
if (file_exists(__DIR__ . '/../moneyquiz.php')) {
    // Don't execute the plugin, just parse it for constants
    $plugin_content = file_get_contents(__DIR__ . '/../moneyquiz.php');
    
    // Extract and define any additional constants
    preg_match_all("/define\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]?([^'\")]+)['\"]?\s*\)/", $plugin_content, $matches);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $i => $constant) {
            if (!defined($constant) && strpos($constant, 'MONEYQUIZ_') === 0) {
                define($constant, $matches[2][$i]);
            }
        }
    }
}