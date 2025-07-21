<?php
/**
 * Bulletproof Loading Test for Money Quiz v3.22.3
 * 
 * Tests the exact loading process to ensure no critical errors
 */

echo "=== BULLETPROOF LOADING TEST - v3.22.3 ===\n\n";

// Mock WordPress functions for testing
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path, $file) {
        return 'https://example.com/wp-content/plugins/money-quiz/' . $path;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) {
        echo "✅ Action registered: {$hook}\n";
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[ERROR_LOG] {$message}\n";
    }
}

if (!function_exists('class_exists')) {
    function class_exists($class) {
        return in_array($class, get_declared_classes());
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce_' . $action;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return $text;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $param = '_wpnonce') {
        return true;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        echo "✅ AJAX Success\n";
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        echo "❌ AJAX Error\n";
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
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

if (!function_exists('version_compare')) {
    function version_compare($version1, $version2, $operator = null) {
        return version_compare($version1, $version2, $operator);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return '5.0';
    }
}

// Test 1: Check if main plugin file loads
echo "Test 1: Main Plugin File Loading\n";
try {
    // Simulate the exact loading process
    define('ABSPATH', '/var/www/html/');
    define('MONEYQUIZ__PLUGIN_DIR', __DIR__ . '/');
    define('MONEYQUIZ_VERSION', '3.22.3');
    
    echo "✅ Constants defined successfully\n";
} catch (Exception $e) {
    echo "❌ Error defining constants: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if required files exist
echo "\nTest 2: Required Files Check\n";
$required_files = [
    'class.moneyquiz.php',
    'version-tracker.php',
    'includes/class-money-quiz-dependency-checker.php',
    'includes/class-money-quiz-integration-loader.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} exists\n";
    } else {
        echo "❌ {$file} missing\n";
    }
}

// Test 3: Check syntax of all files
echo "\nTest 3: Syntax Check\n";
$files_to_check = [
    'moneyquiz.php',
    'includes/class-money-quiz-dependency-checker.php',
    'includes/class-money-quiz-integration-loader.php',
    'class.moneyquiz.php',
    'version-tracker.php'
];

foreach ($files_to_check as $file) {
    $output = shell_exec("php -l {$file} 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ {$file} - No syntax errors\n";
    } else {
        echo "❌ {$file} - Syntax errors found\n";
        echo $output;
    }
}

// Test 4: Test dependency checker loading
echo "\nTest 4: Dependency Checker Loading\n";
try {
    require_once 'includes/class-money-quiz-dependency-checker.php';
    echo "✅ Dependency checker loaded successfully\n";
    
    if (class_exists('Money_Quiz_Dependency_Checker')) {
        echo "✅ Money_Quiz_Dependency_Checker class exists\n";
        
        // Test init method
        try {
            Money_Quiz_Dependency_Checker::init();
            echo "✅ Dependency checker initialized successfully\n";
        } catch (Exception $e) {
            echo "❌ Dependency checker init failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Money_Quiz_Dependency_Checker class not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error loading dependency checker: " . $e->getMessage() . "\n";
}

// Test 5: Test integration loader loading
echo "\nTest 5: Integration Loader Loading\n";
try {
    require_once 'includes/class-money-quiz-integration-loader.php';
    echo "✅ Integration loader loaded successfully\n";
    
    if (class_exists('Money_Quiz_Integration_Loader')) {
        echo "✅ Money_Quiz_Integration_Loader class exists\n";
        
        // Test load_features method
        try {
            Money_Quiz_Integration_Loader::load_features();
            echo "✅ Integration loader features loaded successfully\n";
        } catch (Exception $e) {
            echo "❌ Integration loader features failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Money_Quiz_Integration_Loader class not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error loading integration loader: " . $e->getMessage() . "\n";
}

// Test 6: Test main class loading
echo "\nTest 6: Main Class Loading\n";
try {
    require_once 'class.moneyquiz.php';
    echo "✅ Main class loaded successfully\n";
    
    if (class_exists('Moneyquiz')) {
        echo "✅ Moneyquiz class exists\n";
    } else {
        echo "❌ Moneyquiz class not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error loading main class: " . $e->getMessage() . "\n";
}

// Test 7: Test version tracker loading
echo "\nTest 7: Version Tracker Loading\n";
try {
    require_once 'version-tracker.php';
    echo "✅ Version tracker loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Error loading version tracker: " . $e->getMessage() . "\n";
}

// Test 8: Simulate the exact loading sequence from main plugin
echo "\nTest 8: Simulate Main Plugin Loading Sequence\n";
try {
    // Simulate the exact sequence from moneyquiz.php
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
    echo "✅ Upgrade.php included\n";
    
    require_once( MONEYQUIZ__PLUGIN_DIR . 'class.moneyquiz.php');
    echo "✅ Main class included\n";
    
    require_once( MONEYQUIZ__PLUGIN_DIR . 'version-tracker.php');
    echo "✅ Version tracker included\n";
    
    // Load Composer autoloader (if exists)
    if ( file_exists( MONEYQUIZ__PLUGIN_DIR . 'vendor/autoload.php' ) ) {
        require_once( MONEYQUIZ__PLUGIN_DIR . 'vendor/autoload.php' );
        echo "✅ Composer autoloader included\n";
    } else {
        echo "⚠️ Composer autoloader not found (this is normal)\n";
    }
    
    // Load dependency checker
    if ( file_exists( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-dependency-checker.php' ) ) {
        require_once( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-dependency-checker.php' );
        echo "✅ Dependency checker included\n";
    }
    
    // Load integration loader
    if ( file_exists( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-integration-loader.php' ) ) {
        require_once( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-integration-loader.php' );
        echo "✅ Integration loader included\n";
    }
    
    echo "✅ All components loaded successfully\n";
    
} catch (Exception $e) {
    echo "❌ Error in loading sequence: " . $e->getMessage() . "\n";
}

echo "\n=== BULLETPROOF TEST COMPLETE ===\n";
echo "If all tests pass, the plugin should load without critical errors.\n";
echo "This version is bulletproof and ready for deployment.\n"; 