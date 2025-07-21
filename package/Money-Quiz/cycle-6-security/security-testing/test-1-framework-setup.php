<?php
/**
 * Security Test Framework Setup
 * 
 * @package MoneyQuiz\Security\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Testing;

use PHPUnit\Framework\TestCase;

/**
 * Base Security Test Class
 */
abstract class SecurityTestCase extends TestCase {
    
    protected $original_user;
    protected $test_users = [];
    protected $test_data = [];
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Store original user
        $this->original_user = wp_get_current_user();
        
        // Create test users
        $this->createTestUsers();
        
        // Setup test data
        $this->setupTestData();
        
        // Enable security features
        $this->enableSecurityFeatures();
    }
    
    /**
     * Teardown test environment
     */
    protected function tearDown(): void {
        // Restore original user
        wp_set_current_user($this->original_user->ID);
        
        // Clean up test users
        $this->cleanupTestUsers();
        
        // Clean up test data
        $this->cleanupTestData();
        
        parent::tearDown();
    }
    
    /**
     * Create test users
     */
    protected function createTestUsers() {
        $this->test_users['admin'] = $this->createTestUser('administrator');
        $this->test_users['editor'] = $this->createTestUser('editor');
        $this->test_users['subscriber'] = $this->createTestUser('subscriber');
        $this->test_users['attacker'] = $this->createTestUser('subscriber', [
            'user_login' => 'attacker_' . wp_generate_password(8, false),
            'display_name' => '<script>alert("XSS")</script>'
        ]);
    }
    
    /**
     * Create test user
     */
    protected function createTestUser($role, $args = []) {
        $defaults = [
            'user_login' => 'test_' . $role . '_' . wp_generate_password(8, false),
            'user_email' => 'test_' . $role . '_' . wp_generate_password(8, false) . '@example.com',
            'user_pass' => wp_generate_password(16),
            'role' => $role
        ];
        
        $user_data = wp_parse_args($args, $defaults);
        $user_id = wp_insert_user($user_data);
        
        if (!is_wp_error($user_id)) {
            return get_user_by('id', $user_id);
        }
        
        return null;
    }
    
    /**
     * Setup test data
     */
    protected function setupTestData() {
        // Create test quiz
        $this->test_data['quiz'] = [
            'title' => 'Test Security Quiz',
            'description' => 'Security test quiz',
            'questions' => [
                ['text' => 'Question 1', 'type' => 'multiple'],
                ['text' => 'Question 2', 'type' => 'single']
            ]
        ];
        
        // Create malicious data
        $this->test_data['malicious'] = [
            'xss' => '<script>alert("XSS")</script>',
            'sql' => "' OR '1'='1",
            'path_traversal' => '../../../etc/passwd',
            'null_byte' => "file.php\x00.txt",
            'command' => '; ls -la',
            'xxe' => '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
        ];
    }
    
    /**
     * Enable security features
     */
    protected function enableSecurityFeatures() {
        // Enable all security modules
        add_filter('money_quiz_csrf_enabled', '__return_true');
        add_filter('money_quiz_xss_protection', '__return_true');
        add_filter('money_quiz_sql_protection', '__return_true');
        add_filter('money_quiz_rate_limiting', '__return_true');
    }
    
    /**
     * Clean up test users
     */
    protected function cleanupTestUsers() {
        foreach ($this->test_users as $user) {
            if ($user && $user->ID) {
                wp_delete_user($user->ID);
            }
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Clean up any created test data
        global $wpdb;
        
        // Delete test quiz data
        $wpdb->query("DELETE FROM {$wpdb->prefix}money_quiz_quizzes WHERE title LIKE 'Test Security%'");
    }
    
    /**
     * Assert request is blocked
     */
    protected function assertRequestBlocked($response) {
        $this->assertTrue(
            is_wp_error($response) || 
            (is_array($response) && $response['response']['code'] >= 400),
            'Request should be blocked'
        );
    }
    
    /**
     * Assert no XSS in output
     */
    protected function assertNoXss($output) {
        $xss_patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',
            '/javascript\s*:/i'
        ];
        
        foreach ($xss_patterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $output,
                'Output contains potential XSS'
            );
        }
    }
    
    /**
     * Assert proper escaping
     */
    protected function assertProperlyEscaped($input, $output) {
        $escaped = esc_html($input);
        $this->assertStringContainsString(
            $escaped,
            $output,
            'Output not properly escaped'
        );
    }
    
    /**
     * Simulate attack request
     */
    protected function simulateAttack($type, $payload) {
        switch ($type) {
            case 'xss':
                $_GET['input'] = $payload;
                $_POST['input'] = $payload;
                break;
            
            case 'sql':
                $_GET['id'] = $payload;
                $_POST['search'] = $payload;
                break;
            
            case 'csrf':
                // Remove CSRF token
                unset($_POST['money_quiz_csrf_token']);
                break;
            
            case 'path_traversal':
                $_GET['file'] = $payload;
                break;
        }
    }
    
    /**
     * Get security headers
     */
    protected function getSecurityHeaders() {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * Assert security headers present
     */
    protected function assertSecurityHeadersPresent() {
        $required_headers = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection'
        ];
        
        $headers = $this->getSecurityHeaders();
        
        foreach ($required_headers as $header) {
            $this->assertArrayHasKey(
                $header,
                $headers,
                "Security header {$header} is missing"
            );
        }
    }
}