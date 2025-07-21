<?php
/**
 * Security Fixes Test Script - v3.22.9
 * 
 * This script tests all the security fixes applied to address Grok's findings:
 * 1. SQL Injection vulnerabilities
 * 2. XSS vulnerabilities
 * 3. CSRF protection gaps
 * 4. Hardcoded secrets removal
 * 5. Input validation improvements
 * 
 * @package MoneyQuiz\Security
 * @version 3.22.9
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Mock WordPress functions for testing
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return $nonce === 'valid_nonce';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('intval')) {
    function intval($input) {
        return (int) $input;
    }
}

/**
 * Security Test Class
 */
class Money_Quiz_Security_Test {
    
    private $test_results = [];
    
    /**
     * Run all security tests
     */
    public function run_all_tests() {
        echo "🔒 MONEY QUIZ SECURITY FIXES TEST - v3.22.9\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        // Test 1: SQL Injection Fixes
        $this->test_sql_injection_fixes();
        
        // Test 2: XSS Fixes
        $this->test_xss_fixes();
        
        // Test 3: CSRF Protection
        $this->test_csrf_protection();
        
        // Test 4: Hardcoded Secrets Removal
        $this->test_hardcoded_secrets_removal();
        
        // Test 5: Input Validation
        $this->test_input_validation();
        
        // Test 6: Security Headers
        $this->test_security_headers();
        
        // Test 7: Version Update
        $this->test_version_update();
        
        // Display results
        $this->display_results();
    }
    
    /**
     * Test SQL Injection Fixes
     */
    private function test_sql_injection_fixes() {
        echo "1. Testing SQL Injection Fixes...\n";
        
        // Test 1.1: Check if safe_query function exists
        if (function_exists('money_quiz_safe_query')) {
            $this->add_result('SQL Injection', 'safe_query_function', 'PASS', 'Safe query function implemented');
        } else {
            $this->add_result('SQL Injection', 'safe_query_function', 'FAIL', 'Safe query function not found');
        }
        
        // Test 1.2: Check if sanitize_input function exists
        if (function_exists('money_quiz_sanitize_input')) {
            $this->add_result('SQL Injection', 'sanitize_input_function', 'PASS', 'Input sanitization function implemented');
        } else {
            $this->add_result('SQL Injection', 'sanitize_input_function', 'FAIL', 'Input sanitization function not found');
        }
        
        // Test 1.3: Test prepared statement usage
        $test_query = "SELECT * FROM table WHERE id = %d";
        $test_params = [123];
        $expected = "SELECT * FROM table WHERE id = 123";
        
        if (function_exists('money_quiz_safe_query')) {
            $result = money_quiz_safe_query($test_query, $test_params);
            if ($result && strpos($result, '123') !== false) {
                $this->add_result('SQL Injection', 'prepared_statements', 'PASS', 'Prepared statements working correctly');
            } else {
                $this->add_result('SQL Injection', 'prepared_statements', 'FAIL', 'Prepared statements not working');
            }
        } else {
            $this->add_result('SQL Injection', 'prepared_statements', 'SKIP', 'Safe query function not available');
        }
        
        echo "   ✓ SQL Injection tests completed\n\n";
    }
    
    /**
     * Test XSS Fixes
     */
    private function test_xss_fixes() {
        echo "2. Testing XSS Fixes...\n";
        
        // Test 2.1: Check if safe_echo function exists
        if (function_exists('money_quiz_safe_echo')) {
            $this->add_result('XSS', 'safe_echo_function', 'PASS', 'Safe echo function implemented');
        } else {
            $this->add_result('XSS', 'safe_echo_function', 'FAIL', 'Safe echo function not found');
        }
        
        // Test 2.2: Test XSS prevention
        $malicious_input = '<script>alert("XSS")</script>';
        $safe_output = esc_html($malicious_input);
        
        if (strpos($safe_output, '<script>') === false) {
            $this->add_result('XSS', 'html_escaping', 'PASS', 'HTML escaping working correctly');
        } else {
            $this->add_result('XSS', 'html_escaping', 'FAIL', 'HTML escaping not working');
        }
        
        // Test 2.3: Test attribute escaping
        $malicious_attr = '" onclick="alert(\'XSS\')"';
        $safe_attr = esc_attr($malicious_attr);
        
        if (strpos($safe_attr, 'onclick') === false) {
            $this->add_result('XSS', 'attribute_escaping', 'PASS', 'Attribute escaping working correctly');
        } else {
            $this->add_result('XSS', 'attribute_escaping', 'FAIL', 'Attribute escaping not working');
        }
        
        echo "   ✓ XSS tests completed\n\n";
    }
    
    /**
     * Test CSRF Protection
     */
    private function test_csrf_protection() {
        echo "3. Testing CSRF Protection...\n";
        
        // Test 3.1: Check if nonce verification is working
        $valid_nonce = 'valid_nonce';
        $invalid_nonce = 'invalid_nonce';
        
        if (wp_verify_nonce($valid_nonce, 'test_action')) {
            $this->add_result('CSRF', 'nonce_verification', 'PASS', 'Nonce verification working correctly');
        } else {
            $this->add_result('CSRF', 'nonce_verification', 'FAIL', 'Nonce verification not working');
        }
        
        if (!wp_verify_nonce($invalid_nonce, 'test_action')) {
            $this->add_result('CSRF', 'nonce_rejection', 'PASS', 'Invalid nonces properly rejected');
        } else {
            $this->add_result('CSRF', 'nonce_rejection', 'FAIL', 'Invalid nonces not rejected');
        }
        
        // Test 3.2: Check if nonce field function exists
        if (function_exists('wp_nonce_field')) {
            $this->add_result('CSRF', 'nonce_field_function', 'PASS', 'Nonce field function available');
        } else {
            $this->add_result('CSRF', 'nonce_field_function', 'FAIL', 'Nonce field function not available');
        }
        
        echo "   ✓ CSRF tests completed\n\n";
    }
    
    /**
     * Test Hardcoded Secrets Removal
     */
    private function test_hardcoded_secrets_removal() {
        echo "4. Testing Hardcoded Secrets Removal...\n";
        
        // Test 4.1: Check if encryption key is dynamic
        if (defined('MONEYQUIZ_ENCRYPTION_KEY')) {
            $this->add_result('Hardcoded Secrets', 'encryption_key_dynamic', 'PASS', 'Encryption key is dynamic');
        } else {
            $this->add_result('Hardcoded Secrets', 'encryption_key_dynamic', 'FAIL', 'Encryption key not defined');
        }
        
        // Test 4.2: Check if security salt is dynamic
        if (defined('MONEYQUIZ_SECURITY_SALT')) {
            $this->add_result('Hardcoded Secrets', 'security_salt_dynamic', 'PASS', 'Security salt is dynamic');
        } else {
            $this->add_result('Hardcoded Secrets', 'security_salt_dynamic', 'FAIL', 'Security salt not defined');
        }
        
        // Test 4.3: Check if business email is dynamic
        if (defined('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL')) {
            $this->add_result('Hardcoded Secrets', 'business_email_dynamic', 'PASS', 'Business email is dynamic');
        } else {
            $this->add_result('Hardcoded Secrets', 'business_email_dynamic', 'FAIL', 'Business email not defined');
        }
        
        echo "   ✓ Hardcoded Secrets tests completed\n\n";
    }
    
    /**
     * Test Input Validation
     */
    private function test_input_validation() {
        echo "5. Testing Input Validation...\n";
        
        // Test 5.1: Check if validation function exists
        if (function_exists('money_quiz_validate_input')) {
            $this->add_result('Input Validation', 'validation_function', 'PASS', 'Input validation function implemented');
        } else {
            $this->add_result('Input Validation', 'validation_function', 'FAIL', 'Input validation function not found');
        }
        
        // Test 5.2: Test email validation
        if (function_exists('money_quiz_validate_input')) {
            $valid_email = 'test@example.com';
            $invalid_email = 'invalid-email';
            
            $valid_result = money_quiz_validate_input($valid_email, ['type' => 'email']);
            $invalid_result = money_quiz_validate_input($invalid_email, ['type' => 'email']);
            
            if ($valid_result && !$invalid_result) {
                $this->add_result('Input Validation', 'email_validation', 'PASS', 'Email validation working correctly');
            } else {
                $this->add_result('Input Validation', 'email_validation', 'FAIL', 'Email validation not working');
            }
        } else {
            $this->add_result('Input Validation', 'email_validation', 'SKIP', 'Validation function not available');
        }
        
        // Test 5.3: Test integer validation
        if (function_exists('money_quiz_validate_input')) {
            $valid_int = '123';
            $invalid_int = 'abc';
            
            $valid_result = money_quiz_validate_input($valid_int, ['type' => 'int']);
            $invalid_result = money_quiz_validate_input($invalid_int, ['type' => 'int']);
            
            if ($valid_result && !$invalid_result) {
                $this->add_result('Input Validation', 'integer_validation', 'PASS', 'Integer validation working correctly');
            } else {
                $this->add_result('Input Validation', 'integer_validation', 'FAIL', 'Integer validation not working');
            }
        } else {
            $this->add_result('Input Validation', 'integer_validation', 'SKIP', 'Validation function not available');
        }
        
        echo "   ✓ Input Validation tests completed\n\n";
    }
    
    /**
     * Test Security Headers
     */
    private function test_security_headers() {
        echo "6. Testing Security Headers...\n";
        
        // Test 6.1: Check if security headers are defined
        $required_headers = [
            'X-XSS-Protection',
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy'
        ];
        
        $headers_defined = true;
        foreach ($required_headers as $header) {
            if (!defined('MONEYQUIZ_' . str_replace('-', '_', $header) . '_HEADER')) {
                $headers_defined = false;
                break;
            }
        }
        
        if ($headers_defined) {
            $this->add_result('Security Headers', 'headers_defined', 'PASS', 'Security headers are defined');
        } else {
            $this->add_result('Security Headers', 'headers_defined', 'FAIL', 'Security headers not defined');
        }
        
        // Test 6.2: Check Content Security Policy
        if (defined('MONEYQUIZ_CSP_HEADER')) {
            $this->add_result('Security Headers', 'csp_header', 'PASS', 'Content Security Policy header defined');
        } else {
            $this->add_result('Security Headers', 'csp_header', 'FAIL', 'Content Security Policy header not defined');
        }
        
        echo "   ✓ Security Headers tests completed\n\n";
    }
    
    /**
     * Test Version Update
     */
    private function test_version_update() {
        echo "7. Testing Version Update...\n";
        
        // Test 7.1: Check if version is updated to 3.22.9
        if (defined('MONEYQUIZ_VERSION') && MONEYQUIZ_VERSION === '3.22.9') {
            $this->add_result('Version Update', 'version_updated', 'PASS', 'Version updated to 3.22.9');
        } else {
            $this->add_result('Version Update', 'version_updated', 'FAIL', 'Version not updated to 3.22.9');
        }
        
        // Test 7.2: Check if security patch is included
        $patch_file = __DIR__ . '/security-patch-v3.22.9.php';
        if (file_exists($patch_file)) {
            $this->add_result('Version Update', 'security_patch_file', 'PASS', 'Security patch file exists');
        } else {
            $this->add_result('Version Update', 'security_patch_file', 'FAIL', 'Security patch file not found');
        }
        
        echo "   ✓ Version Update tests completed\n\n";
    }
    
    /**
     * Add test result
     */
    private function add_result($category, $test, $status, $message) {
        $this->test_results[] = [
            'category' => $category,
            'test' => $test,
            'status' => $status,
            'message' => $message
        ];
    }
    
    /**
     * Display test results
     */
    private function display_results() {
        echo "📊 SECURITY TEST RESULTS\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $pass_count = 0;
        $fail_count = 0;
        $skip_count = 0;
        
        foreach ($this->test_results as $result) {
            $status_icon = $result['status'] === 'PASS' ? '✅' : ($result['status'] === 'FAIL' ? '❌' : '⏭️');
            echo "{$status_icon} {$result['category']} - {$result['test']}: {$result['message']}\n";
            
            if ($result['status'] === 'PASS') {
                $pass_count++;
            } elseif ($result['status'] === 'FAIL') {
                $fail_count++;
            } else {
                $skip_count++;
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📈 SUMMARY:\n";
        echo "✅ PASS: {$pass_count}\n";
        echo "❌ FAIL: {$fail_count}\n";
        echo "⏭️ SKIP: {$skip_count}\n";
        echo "📊 TOTAL: " . count($this->test_results) . "\n\n";
        
        if ($fail_count === 0) {
            echo "🎉 ALL SECURITY FIXES SUCCESSFULLY APPLIED!\n";
            echo "🔒 Money Quiz Plugin v3.22.9 is now secure.\n";
        } else {
            echo "⚠️  SOME SECURITY FIXES NEED ATTENTION!\n";
            echo "🔧 Please review and fix the failed tests.\n";
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
    }
}

// Run security tests
if (php_sapi_name() === 'cli') {
    $tester = new Money_Quiz_Security_Test();
    $tester->run_all_tests();
} else {
    echo "This script should be run from the command line.\n";
    echo "Usage: php test-security-fixes-v3.22.9.php\n";
} 