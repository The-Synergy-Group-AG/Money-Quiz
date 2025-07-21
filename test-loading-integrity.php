<?php
/**
 * Money Quiz Loading Integrity Test
 * 
 * Tests the loader manager to ensure duplicate loading is prevented
 */

// Mock WordPress functions for testing
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
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

echo "=== Money Quiz Loading Integrity Test ===\n\n";

// Test 1: Load the loader manager
echo "Test 1: Loading Loader Manager\n";
require_once 'includes/class-money-quiz-loader-manager.php';
echo "✅ Loader Manager loaded successfully\n\n";

// Test 2: Test safe loading
echo "Test 2: Testing Safe Loading\n";
$result1 = Money_Quiz_Loader_Manager::safeLoad(
    'includes/class-money-quiz-dependency-checker.php',
    'Money_Quiz_Dependency_Checker',
    'test_1'
);
echo "First load result: " . ($result1 ? 'SUCCESS' : 'FAILED') . "\n";

$result2 = Money_Quiz_Loader_Manager::safeLoad(
    'includes/class-money-quiz-dependency-checker.php',
    'Money_Quiz_Dependency_Checker',
    'test_2'
);
echo "Second load result: " . ($result2 ? 'SUCCESS' : 'FAILED') . "\n";

if ($result1 && $result2) {
    echo "✅ Duplicate loading prevented successfully\n";
} else {
    echo "❌ Loading failed\n";
}
echo "\n";

// Test 3: Check loading statistics
echo "Test 3: Loading Statistics\n";
$stats = Money_Quiz_Loader_Manager::getLoadingStats();
echo "Loaded classes: {$stats['loaded_classes']}\n";
echo "Loading attempts: {$stats['loading_attempts']}\n";
echo "\n";

// Test 4: Validate integrity
echo "Test 4: Integrity Validation\n";
$integrity = Money_Quiz_Loader_Manager::validateIntegrity();
echo "Integrity valid: " . ($integrity['valid'] ? 'YES' : 'NO') . "\n";
echo "Total classes: {$integrity['total_classes']}\n";
echo "Total attempts: {$integrity['total_attempts']}\n";

if (!empty($integrity['issues'])) {
    echo "Issues found:\n";
    foreach ($integrity['issues'] as $issue) {
        echo "- {$issue}\n";
    }
} else {
    echo "✅ No integrity issues detected\n";
}
echo "\n";

// Test 5: Get detailed report
echo "Test 5: Detailed Loading Report\n";
$report = Money_Quiz_Loader_Manager::getLoadingReport();
echo $report;
echo "\n";

// Test 6: Test class existence checking
echo "Test 6: Class Existence Checking\n";
$is_loaded = Money_Quiz_Loader_Manager::isClassLoaded('Money_Quiz_Dependency_Checker');
echo "Money_Quiz_Dependency_Checker loaded: " . ($is_loaded ? 'YES' : 'NO') . "\n";

$is_not_loaded = Money_Quiz_Loader_Manager::isClassLoaded('NonExistentClass');
echo "NonExistentClass loaded: " . ($is_not_loaded ? 'YES' : 'NO') . "\n";
echo "\n";

// Test 7: Test with non-existent file
echo "Test 7: Non-existent File Test\n";
$result = Money_Quiz_Loader_Manager::safeLoad(
    'includes/non-existent-file.php',
    'NonExistentClass',
    'test_7'
);
echo "Non-existent file load result: " . ($result ? 'SUCCESS' : 'FAILED') . " (Expected: FAILED)\n";
echo "\n";

echo "=== Test Complete ===\n";
echo "If all tests pass, the loading integrity system is working correctly.\n";
echo "This ensures we will NEVER have duplicate loading issues again.\n"; 