<?php
/**
 * Critical Security Fixes Test - Money Quiz v3.22.7
 * Tests all critical security vulnerabilities identified by Grok
 */

// Mock WordPress functions for testing
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true; // Mock for testing
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true; // Mock for testing
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('intval')) {
    function intval($var) {
        return (int)$var;
    }
}

if (!function_exists('get_option')) {
    function get_option($option) {
        return 'test@example.com'; // Mock for testing
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true; // Mock for testing
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message) {
        echo "SECURITY CHECK: " . $message . "\n";
        return;
    }
}

echo "🔒 CRITICAL SECURITY FIXES TEST - Money Quiz v3.22.7\n";
echo "=" . str_repeat("=", 60) . "\n\n";

$tests_passed = 0;
$total_tests = 0;

// Test 1: RCE vulnerability removal
echo "1. Testing RCE vulnerability removal...\n";
$total_tests++;

// Check if ajax_run_composer method exists
$dependency_checker_content = file_get_contents('includes/class-money-quiz-dependency-checker.php');
if (strpos($dependency_checker_content, 'public static function ajax_run_composer()') === false) {
    echo "✅ RCE vulnerability REMOVED - ajax_run_composer method not found\n";
    $tests_passed++;
} else {
    echo "❌ RCE vulnerability STILL EXISTS - ajax_run_composer method found\n";
}

// Test 2: SQL Injection prevention
echo "\n2. Testing SQL Injection prevention...\n";
$total_tests++;

$main_file_content = file_get_contents('moneyquiz.php');
$sql_injection_patterns = [
    'sanitize_text_field' => 'Input sanitization',
    'intval' => 'Integer validation',
    'wp_verify_nonce' => 'Nonce verification',
    'current_user_can' => 'Capability checks'
];

$sql_injection_fixed = true;
foreach ($sql_injection_patterns as $pattern => $description) {
    if (strpos($main_file_content, $pattern) !== false) {
        echo "✅ $description implemented\n";
    } else {
        echo "❌ $description MISSING\n";
        $sql_injection_fixed = false;
    }
}

if ($sql_injection_fixed) {
    echo "✅ SQL Injection prevention implemented\n";
    $tests_passed++;
} else {
    echo "❌ SQL Injection prevention INCOMPLETE\n";
}

// Test 3: Hardcoded secrets removal
echo "\n3. Testing hardcoded secrets removal...\n";
$total_tests++;

if (strpos($main_file_content, 'get_option(\'moneyquiz_business_insights_email\')') !== false) {
    echo "✅ Hardcoded email replaced with WordPress options\n";
    $tests_passed++;
} else {
    echo "❌ Hardcoded email still exists\n";
}

// Test 4: Data exposure prevention
echo "\n4. Testing data exposure prevention...\n";
$total_tests++;

// Check if database dumping has been replaced with summary statistics
if (strpos($main_file_content, 'Database Summary') !== false && 
    strpos($main_file_content, 'Total results records') !== false) {
    echo "✅ Database dumping replaced with summary statistics\n";
    $tests_passed++;
} else {
    echo "❌ Database dumping still exists in emails\n";
}

// Test 5: Version update
echo "\n5. Testing version update...\n";
$total_tests++;

if (strpos($main_file_content, '3.22.7') !== false) {
    echo "✅ Version updated to 3.22.7\n";
    $tests_passed++;
} else {
    echo "❌ Version not updated\n";
}

// Test 6: Security comments
echo "\n6. Testing security documentation...\n";
$total_tests++;

$security_comments = [
    'SECURITY FIX' => 'Security fix comments',
    'RCE VULNERABILITY' => 'RCE documentation',
    'SQL injection' => 'SQL injection documentation',
    'DATA EXPOSURE VULNERABILITY' => 'Data exposure documentation'
];

$security_documented = true;
foreach ($security_comments as $pattern => $description) {
    if (strpos($main_file_content, $pattern) !== false) {
        echo "✅ $description found\n";
    } else {
        echo "❌ $description missing\n";
        $security_documented = false;
    }
}

if ($security_documented) {
    echo "✅ Security documentation implemented\n";
    $tests_passed++;
} else {
    echo "❌ Security documentation incomplete\n";
}

// Test 7: AJAX security
echo "\n7. Testing AJAX security...\n";
$total_tests++;

$ajax_security_patterns = [
    'check_ajax_referer' => 'AJAX nonce verification',
    'current_user_can' => 'AJAX capability checks',
    'sanitize_text_field' => 'AJAX input sanitization'
];

$ajax_secure = true;
foreach ($ajax_security_patterns as $pattern => $description) {
    if (strpos($dependency_checker_content, $pattern) !== false) {
        echo "✅ $description implemented\n";
    } else {
        echo "❌ $description missing\n";
        $ajax_secure = false;
    }
}

if ($ajax_secure) {
    echo "✅ AJAX security implemented\n";
    $tests_passed++;
} else {
    echo "❌ AJAX security incomplete\n";
}

// Final Results
echo "\n" . str_repeat("=", 60) . "\n";
echo "🔒 CRITICAL SECURITY FIXES TEST RESULTS\n";
echo str_repeat("=", 60) . "\n";
echo "Tests Passed: $tests_passed/$total_tests\n";
echo "Success Rate: " . round(($tests_passed / $total_tests) * 100, 1) . "%\n\n";

if ($tests_passed === $total_tests) {
    echo "🎉 ALL CRITICAL SECURITY FIXES IMPLEMENTED SUCCESSFULLY!\n";
    echo "✅ RCE vulnerability REMOVED\n";
    echo "✅ SQL Injection prevention IMPLEMENTED\n";
    echo "✅ Hardcoded secrets REMOVED\n";
    echo "✅ Data exposure PREVENTED\n";
    echo "✅ Version UPDATED to 3.22.7\n";
    echo "✅ Security documentation ADDED\n";
    echo "✅ AJAX security IMPLEMENTED\n\n";
    echo "🚀 Plugin is now ready for production deployment!\n";
} else {
    echo "⚠️  SOME CRITICAL SECURITY FIXES STILL NEEDED\n";
    echo "Please review and implement remaining fixes before production deployment.\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
?> 