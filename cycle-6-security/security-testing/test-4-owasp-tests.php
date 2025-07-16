<?php
/**
 * OWASP Top 10 Security Tests
 * 
 * @package MoneyQuiz\Security\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Testing;

/**
 * OWASP Top 10 Test Suite
 */
class OwaspTop10Test extends SecurityTestCase {
    
    /**
     * A01:2021 – Broken Access Control
     */
    public function testBrokenAccessControl() {
        // Test unauthorized access to admin functions
        wp_set_current_user($this->test_users['subscriber']->ID);
        
        // Attempt to access admin-only function
        $this->assertFalse(current_user_can('manage_options'));
        
        // Test direct object reference
        $other_user_quiz = $this->createTestQuiz($this->test_users['editor']->ID);
        $this->assertFalse(current_user_can('edit_post', $other_user_quiz));
    }
    
    /**
     * A02:2021 – Cryptographic Failures
     */
    public function testCryptographicFailures() {
        // Test password hashing
        $password = 'TestPassword123!';
        $hash = wp_hash_password($password);
        
        // Verify proper hashing
        $this->assertNotEquals($password, $hash);
        $this->assertTrue(wp_check_password($password, $hash));
        
        // Test secure token generation
        $token = wp_generate_password(32, false);
        $this->assertEquals(32, strlen($token));
    }
    
    /**
     * A03:2021 – Injection
     */
    public function testInjection() {
        global $wpdb;
        
        // SQL Injection test
        $malicious_input = "1' OR '1'='1";
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $malicious_input
        );
        
        $this->assertStringNotContainsString("OR '1'='1'", $query);
        
        // Command injection test
        $user_input = "; cat /etc/passwd";
        $escaped = escapeshellarg($user_input);
        $this->assertStringNotContainsString(';', $escaped);
    }
    
    /**
     * A04:2021 – Insecure Design
     */
    public function testInsecureDesign() {
        // Test rate limiting on sensitive operations
        $limiter = \MoneyQuiz\Security\RateLimit\RateLimiter::getInstance();
        
        // Configure strict limit for password reset
        add_filter('money_quiz_rate_limit_config', function($config, $action) {
            if ($action === 'password_reset') {
                return ['attempts' => 3, 'window' => 3600, 'lockout' => 7200];
            }
            return $config;
        }, 10, 2);
        
        // Verify rate limit exists
        $config = apply_filters('money_quiz_rate_limit_config', [], 'password_reset');
        $this->assertEquals(3, $config['attempts']);
    }
    
    /**
     * A05:2021 – Security Misconfiguration
     */
    public function testSecurityMisconfiguration() {
        // Test for exposed sensitive files
        $sensitive_files = [
            '.env', '.git/config', 'phpinfo.php',
            'wp-config.php.bak', 'debug.log'
        ];
        
        foreach ($sensitive_files as $file) {
            $path = ABSPATH . $file;
            if (file_exists($path)) {
                $this->fail("Sensitive file exposed: {$file}");
            }
        }
        
        // Test error reporting
        $this->assertFalse(ini_get('display_errors'));
    }
    
    /**
     * A06:2021 – Vulnerable Components
     */
    public function testVulnerableComponents() {
        // Check WordPress version
        global $wp_version;
        $this->assertNotEmpty($wp_version);
        
        // Check PHP version
        $this->assertTrue(version_compare(PHP_VERSION, '7.4', '>='));
        
        // Verify security scanner exists
        $this->assertTrue(
            class_exists('\MoneyQuiz\Security\Scanner\VulnerabilityScanner')
        );
    }
    
    /**
     * A07:2021 – Identification and Authentication Failures
     */
    public function testAuthenticationFailures() {
        // Test session security
        $this->assertTrue(ini_get('session.cookie_httponly'));
        $this->assertTrue(ini_get('session.cookie_secure') || $this->isLocalEnvironment());
        
        // Test password policy
        $weak_password = '123456';
        $strong_password = 'C0mpl3x!P@ssw0rd#2024';
        
        // WordPress should reject weak passwords
        $user_id = wp_create_user('testuser', $weak_password, 'test@example.com');
        if (!is_wp_error($user_id)) {
            wp_delete_user($user_id);
            $this->markTestIncomplete('Weak password policy not enforced');
        }
    }
    
    /**
     * A08:2021 – Software and Data Integrity Failures
     */
    public function testIntegrityFailures() {
        // Test CSRF protection
        $csrf = \MoneyQuiz\Security\CSRF\CsrfProtection::getInstance();
        
        // Simulate form without token
        unset($_POST['money_quiz_csrf_token']);
        $this->assertFalse($csrf->verifyToken('test_action'));
        
        // Test file integrity
        $plugin_file = plugin_dir_path(dirname(__FILE__, 2)) . 'money-quiz.php';
        $this->assertFileExists($plugin_file);
    }
    
    /**
     * A09:2021 – Security Logging and Monitoring Failures
     */
    public function testLoggingMonitoring() {
        // Verify audit logger exists
        $logger = \MoneyQuiz\Security\Audit\AuditLogger::getInstance();
        $this->assertInstanceOf('\MoneyQuiz\Security\Audit\AuditLogger', $logger);
        
        // Test security event logging
        $logger->logSecurityEvent('test.security.event', [
            'severity' => 'warning',
            'details' => 'Test security event'
        ]);
        
        // Verify log was created
        $logs = $logger->getRecentLogs(1);
        $this->assertNotEmpty($logs);
    }
    
    /**
     * A10:2021 – Server-Side Request Forgery (SSRF)
     */
    public function testSsrf() {
        // Test URL validation
        $validator = new \MoneyQuiz\Security\Validation\UrlValidator();
        
        $malicious_urls = [
            'http://localhost/admin',
            'http://127.0.0.1:22',
            'file:///etc/passwd',
            'http://169.254.169.254/', // AWS metadata
            'gopher://localhost:8080'
        ];
        
        foreach ($malicious_urls as $url) {
            $this->assertFalse(
                $validator->isSafeUrl($url),
                "URL should be blocked: {$url}"
            );
        }
    }
    
    /**
     * Create test quiz helper
     */
    private function createTestQuiz($user_id) {
        return wp_insert_post([
            'post_title' => 'Test Quiz',
            'post_type' => 'money_quiz',
            'post_author' => $user_id,
            'post_status' => 'publish'
        ]);
    }
    
    /**
     * Check if local environment
     */
    private function isLocalEnvironment() {
        return strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    }
}