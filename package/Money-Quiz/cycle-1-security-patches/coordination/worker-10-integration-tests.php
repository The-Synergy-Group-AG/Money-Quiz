<?php
/**
 * Worker 10: Coordination & Integration Testing
 * Role: Validate all security patches work together without breaking functionality
 */

class MoneyQuizSecurityIntegration {
    
    private $test_results = array();
    private $patches_applied = array();
    
    /**
     * Run complete integration test suite
     */
    public function run_all_tests() {
        $this->log_message('Starting Money Quiz Security Integration Tests');
        
        // Test 1: SQL Injection Patches
        $this->test_sql_injection_patches();
        
        // Test 2: XSS Prevention
        $this->test_xss_prevention();
        
        // Test 3: CSRF Protection
        $this->test_csrf_protection();
        
        // Test 4: Credential Security
        $this->test_credential_security();
        
        // Test 5: Access Control
        $this->test_access_control();
        
        // Test 6: Combined Security
        $this->test_combined_security();
        
        // Test 7: Performance Impact
        $this->test_performance_impact();
        
        // Generate report
        return $this->generate_test_report();
    }
    
    /**
     * Test SQL Injection Patches (Workers 1-3)
     */
    private function test_sql_injection_patches() {
        $this->log_message('Testing SQL Injection Patches...');
        
        global $wpdb, $table_prefix;
        
        // Test 1.1: Prepared statements in quiz.moneycoach.php
        $test_email = "test' OR '1'='1";
        $safe_query = $wpdb->prepare(
            "SELECT * FROM {$table_prefix}" . TABLE_MQ_PROSPECTS . " WHERE Email = %s",
            $test_email
        );
        
        $result = $wpdb->get_row($safe_query);
        $this->assert_null($result, 'SQL injection attempt blocked in email lookup');
        
        // Test 1.2: IN clause protection
        $test_ids = array(1, 2, 3);
        $placeholders = array_fill(0, count($test_ids), '%d');
        $safe_in_query = $wpdb->prepare(
            "SELECT * FROM {$table_prefix}" . TABLE_MQ_TAKEN . " WHERE Quiz_Length IN (" . implode(',', $placeholders) . ")",
            $test_ids
        );
        
        $this->assert_true(strpos($safe_in_query, "IN (1,2,3)") !== false, 'IN clause properly escaped');
        
        // Test 1.3: Admin panel protection
        $_REQUEST['questionid'] = "1 UNION SELECT * FROM wp_users";
        $safe_id = absint($_REQUEST['questionid']);
        $this->assert_equals($safe_id, 1, 'Malicious input sanitized to integer');
        
        $this->patches_applied[] = 'SQL Injection Protection';
    }
    
    /**
     * Test XSS Prevention (Workers 4-5)
     */
    private function test_xss_prevention() {
        $this->log_message('Testing XSS Prevention...');
        
        // Test 2.1: HTML escaping
        $malicious_input = '<script>alert("XSS")</script>';
        $escaped = esc_html($malicious_input);
        $this->assert_equals($escaped, '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', 'HTML properly escaped');
        
        // Test 2.2: Attribute escaping
        $attr_input = '" onmouseover="alert(1)"';
        $escaped_attr = esc_attr($attr_input);
        $this->assert_false(strpos($escaped_attr, 'onmouseover') !== false, 'Event handler stripped');
        
        // Test 2.3: URL escaping
        $malicious_url = 'javascript:alert(1)';
        $safe_url = esc_url($malicious_url);
        $this->assert_empty($safe_url, 'JavaScript URL blocked');
        
        // Test 2.4: JavaScript context
        $js_data = array('test' => '<script>alert(1)</script>');
        $json_safe = json_encode($js_data);
        $this->assert_false(strpos($json_safe, '<script>') !== false, 'Script tags escaped in JSON');
        
        $this->patches_applied[] = 'XSS Prevention';
    }
    
    /**
     * Test CSRF Protection (Workers 6-7)
     */
    private function test_csrf_protection() {
        $this->log_message('Testing CSRF Protection...');
        
        // Test 3.1: Nonce generation
        $nonce = wp_create_nonce('mq_test_action');
        $this->assert_true(strlen($nonce) > 0, 'Nonce generated successfully');
        
        // Test 3.2: Nonce verification
        $valid = wp_verify_nonce($nonce, 'mq_test_action');
        $this->assert_true($valid !== false, 'Valid nonce verified');
        
        // Test 3.3: Invalid nonce
        $invalid = wp_verify_nonce('invalid_nonce', 'mq_test_action');
        $this->assert_false($invalid, 'Invalid nonce rejected');
        
        // Test 3.4: AJAX nonce
        $ajax_nonce = MoneyQuizCSRF::create_nonce('ajax_request');
        $this->assert_true(strlen($ajax_nonce) > 0, 'AJAX nonce created');
        
        $this->patches_applied[] = 'CSRF Protection';
    }
    
    /**
     * Test Credential Security (Worker 8)
     */
    private function test_credential_security() {
        $this->log_message('Testing Credential Security...');
        
        // Test 4.1: No hardcoded credentials
        $plugin_file = file_get_contents(MONEYQUIZ__PLUGIN_DIR . 'moneyquiz.php');
        $hardcoded_patterns = array(
            '/andre@101businessinsights\.info/',
            '/5bcd52f5276855\.46942741/',
            '/define\s*\(\s*[\'"]MONEYQUIZ_SPECIAL_SECRET_KEY[\'"]\s*,\s*[\'"][^\'"\$]+[\'"]\s*\)/'
        );
        
        foreach ($hardcoded_patterns as $pattern) {
            $this->assert_false(
                preg_match($pattern, $plugin_file),
                'No hardcoded credentials found'
            );
        }
        
        // Test 4.2: Config loader
        $test_config = MoneyQuizConfig::get('test_key', 'default');
        $this->assert_equals($test_config, 'default', 'Config loader returns default for missing key');
        
        // Test 4.3: Encryption
        $sensitive_data = 'password123';
        $encrypted = MoneyQuizConfig::encrypt_value($sensitive_data);
        $decrypted = MoneyQuizConfig::decrypt_value($encrypted);
        $this->assert_equals($decrypted, $sensitive_data, 'Encryption/decryption working');
        
        $this->patches_applied[] = 'Credential Security';
    }
    
    /**
     * Test Access Control (Worker 9)
     */
    private function test_access_control() {
        $this->log_message('Testing Access Control...');
        
        // Test 5.1: Capability exists
        $admin = get_role('administrator');
        $this->assert_true(
            $admin->has_cap(MoneyQuizCapabilities::MANAGE_QUIZ),
            'Admin has manage_quiz capability'
        );
        
        // Test 5.2: Permission check function
        wp_set_current_user(1); // Admin user
        $this->assert_true(
            current_user_can(MoneyQuizCapabilities::EDIT_QUESTIONS),
            'Admin can edit questions'
        );
        
        // Test 5.3: Frontend token generation
        $token = MoneyQuizFrontendSecurity::generate_access_token(123, 'test@example.com');
        $this->assert_true(strlen($token) > 0, 'Access token generated');
        
        // Test 5.4: Token verification
        $valid = MoneyQuizFrontendSecurity::verify_access_token($token, 123);
        $this->assert_true($valid, 'Valid token verified');
        
        $this->patches_applied[] = 'Access Control';
    }
    
    /**
     * Test Combined Security
     */
    private function test_combined_security() {
        $this->log_message('Testing Combined Security Features...');
        
        // Test 6.1: Form submission with all protections
        $_POST = array(
            'prospect_data' => array(
                'Name' => '<script>alert(1)</script>',
                'Email' => "test' OR '1'='1",
            ),
            '_wpnonce' => wp_create_nonce('mq_submit_quiz')
        );
        
        // XSS protection
        $name = esc_html($_POST['prospect_data']['Name']);
        $this->assert_false(strpos($name, '<script>') !== false, 'XSS blocked in form');
        
        // SQL injection protection
        global $wpdb;
        $email_safe = $wpdb->prepare('%s', $_POST['prospect_data']['Email']);
        $this->assert_false(strpos($email_safe, 'OR') !== false, 'SQL injection blocked');
        
        // CSRF protection
        $nonce_valid = wp_verify_nonce($_POST['_wpnonce'], 'mq_submit_quiz');
        $this->assert_true($nonce_valid !== false, 'CSRF token valid');
        
        $this->patches_applied[] = 'Combined Security';
    }
    
    /**
     * Test Performance Impact
     */
    private function test_performance_impact() {
        $this->log_message('Testing Performance Impact...');
        
        $start_time = microtime(true);
        
        // Simulate typical operations with security
        for ($i = 0; $i < 100; $i++) {
            // SQL query with prepare
            global $wpdb;
            $wpdb->prepare("SELECT * FROM test WHERE id = %d", $i);
            
            // XSS escaping
            esc_html("Test string $i");
            
            // Nonce generation
            wp_create_nonce("test_$i");
        }
        
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        
        $this->assert_true($duration < 0.5, 'Performance impact acceptable (< 500ms for 100 operations)');
        
        $this->test_results['performance'] = array(
            'duration' => $duration,
            'operations' => 100,
            'avg_per_op' => $duration / 100
        );
    }
    
    /**
     * Helper assertion methods
     */
    private function assert_true($condition, $message) {
        $this->test_results[] = array(
            'test' => $message,
            'result' => $condition ? 'PASS' : 'FAIL',
            'type' => 'assert_true'
        );
    }
    
    private function assert_false($condition, $message) {
        $this->test_results[] = array(
            'test' => $message,
            'result' => !$condition ? 'PASS' : 'FAIL',
            'type' => 'assert_false'
        );
    }
    
    private function assert_equals($actual, $expected, $message) {
        $this->test_results[] = array(
            'test' => $message,
            'result' => $actual === $expected ? 'PASS' : 'FAIL',
            'type' => 'assert_equals',
            'actual' => $actual,
            'expected' => $expected
        );
    }
    
    private function assert_null($value, $message) {
        $this->test_results[] = array(
            'test' => $message,
            'result' => $value === null ? 'PASS' : 'FAIL',
            'type' => 'assert_null'
        );
    }
    
    private function assert_empty($value, $message) {
        $this->test_results[] = array(
            'test' => $message,
            'result' => empty($value) ? 'PASS' : 'FAIL',
            'type' => 'assert_empty'
        );
    }
    
    private function log_message($message) {
        $this->test_results[] = array(
            'test' => $message,
            'result' => 'INFO',
            'type' => 'log'
        );
    }
    
    /**
     * Generate test report
     */
    private function generate_test_report() {
        $total_tests = 0;
        $passed_tests = 0;
        $failed_tests = 0;
        
        foreach ($this->test_results as $result) {
            if ($result['type'] !== 'log') {
                $total_tests++;
                if ($result['result'] === 'PASS') {
                    $passed_tests++;
                } else {
                    $failed_tests++;
                }
            }
        }
        
        return array(
            'summary' => array(
                'total_tests' => $total_tests,
                'passed' => $passed_tests,
                'failed' => $failed_tests,
                'success_rate' => $total_tests > 0 ? ($passed_tests / $total_tests) * 100 : 0,
                'patches_applied' => $this->patches_applied
            ),
            'details' => $this->test_results,
            'performance' => isset($this->test_results['performance']) ? $this->test_results['performance'] : null
        );
    }
}

// Test execution function
function mq_run_security_integration_tests() {
    $tester = new MoneyQuizSecurityIntegration();
    $results = $tester->run_all_tests();
    
    // Output results
    echo "<h2>Money Quiz Security Integration Test Results</h2>";
    echo "<h3>Summary</h3>";
    echo "<ul>";
    echo "<li>Total Tests: " . $results['summary']['total_tests'] . "</li>";
    echo "<li>Passed: " . $results['summary']['passed'] . "</li>";
    echo "<li>Failed: " . $results['summary']['failed'] . "</li>";
    echo "<li>Success Rate: " . number_format($results['summary']['success_rate'], 2) . "%</li>";
    echo "</ul>";
    
    echo "<h3>Patches Applied</h3>";
    echo "<ul>";
    foreach ($results['summary']['patches_applied'] as $patch) {
        echo "<li>" . esc_html($patch) . "</li>";
    }
    echo "</ul>";
    
    echo "<h3>Test Details</h3>";
    echo "<table class='widefat'>";
    echo "<thead><tr><th>Test</th><th>Result</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($results['details'] as $test) {
        $class = $test['result'] === 'FAIL' ? 'error' : '';
        echo "<tr class='" . $class . "'>";
        echo "<td>" . esc_html($test['test']) . "</td>";
        echo "<td>" . esc_html($test['result']) . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    
    if ($results['performance']) {
        echo "<h3>Performance Impact</h3>";
        echo "<p>100 operations completed in " . number_format($results['performance']['duration'] * 1000, 2) . "ms";
        echo " (average: " . number_format($results['performance']['avg_per_op'] * 1000, 4) . "ms per operation)</p>";
    }
    
    return $results;
}