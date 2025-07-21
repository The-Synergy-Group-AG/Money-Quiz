<?php
/**
 * Test script to verify MoneyQuiz plugin loading
 * 
 * This script simulates WordPress environment and tests plugin loading
 */

// Simulate WordPress environment
define('ABSPATH', '/tmp/');
define('WP_PLUGIN_DIR', '/tmp/plugins/');
define('WP_PLUGIN_URL', 'http://localhost/plugins/');

// Mock WordPress functions
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path, $file) {
        return 'http://localhost/plugins/' . basename(dirname($file)) . '/' . $path;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock function - do nothing
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // Mock function - do nothing
        return true;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // Mock function - do nothing
        return true;
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback) {
        // Mock function - do nothing
        return true;
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'http://localhost';
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $list = 'enqueued') {
        return false;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = false, $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = false, $deps = array(), $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        return true;
    }
}

if (!function_exists('did_action')) {
    function did_action($hook_name) {
        return false;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

// Mock WordPress upgrade functions
if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        // Mock function - do nothing
        return true;
    }
}

// Create a mock upgrade.php file
$mock_upgrade_content = '<?php
if (!function_exists("dbDelta")) {
    function dbDelta($sql) {
        return true;
    }
}
';

// Create the mock directory structure
$mock_dir = '/tmp/wp-admin/includes/';
if (!is_dir($mock_dir)) {
    mkdir($mock_dir, 0755, true);
}

// Write the mock upgrade.php file
file_put_contents('/tmp/wp-admin/includes/upgrade.php', $mock_upgrade_content);

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "ERROR: " . $message . "\n";
    }
}

if (!function_exists('file_exists')) {
    function file_exists($file) {
        return \file_exists($file);
    }
}

// Test plugin loading
echo "Testing MoneyQuiz plugin loading...\n";

try {
    // Include the main plugin file
    require_once 'moneyquiz.php';
    echo "✓ Main plugin file loaded successfully\n";
    
    // Test if core classes are available
    if (class_exists('Moneyquiz')) {
        echo "✓ Core Moneyquiz class loaded\n";
    } else {
        echo "✗ Core Moneyquiz class not found\n";
    }
    
    if (class_exists('Money_Quiz_Integration_Loader')) {
        echo "✓ Integration Loader class loaded\n";
    } else {
        echo "✗ Integration Loader class not found\n";
    }
    
    if (class_exists('Money_Quiz_Service_Container')) {
        echo "✓ Service Container class loaded\n";
    } else {
        echo "✗ Service Container class not found\n";
    }
    
    if (class_exists('Money_Quiz_Hooks_Registry')) {
        echo "✓ Hooks Registry class loaded\n";
    } else {
        echo "✗ Hooks Registry class not found\n";
    }
    
    echo "\nPlugin loading test completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Plugin loading failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "✗ Fatal error during plugin loading: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 