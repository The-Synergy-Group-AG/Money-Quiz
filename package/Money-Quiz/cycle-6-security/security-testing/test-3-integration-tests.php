<?php
/**
 * Security Integration Tests
 * 
 * @package MoneyQuiz\Security\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Testing;

/**
 * Integration Tests for Security Components
 */
class SecurityIntegrationTest extends SecurityTestCase {
    
    /**
     * Test CSRF + Form submission
     */
    public function testCsrfFormIntegration() {
        // Set current user
        wp_set_current_user($this->test_users['editor']->ID);
        
        // Get CSRF token
        $csrf = \MoneyQuiz\Security\CSRF\CsrfProtection::getInstance();
        $token = $csrf->generateToken('quiz_submit');
        
        // Simulate form submission with token
        $_POST = [
            'money_quiz_csrf_token' => $token,
            'quiz_id' => 1,
            'answers' => ['q1' => 'a', 'q2' => 'b']
        ];
        
        // Verify token validation passes
        $this->assertTrue($csrf->verifyToken('quiz_submit'));
        
        // Verify token is consumed (one-time use)
        $this->assertFalse($csrf->verifyToken('quiz_submit'));
    }
    
    /**
     * Test XSS + Output filtering
     */
    public function testXssOutputIntegration() {
        // Create quiz with XSS attempt
        $malicious_title = '<script>alert("XSS")</script>Quiz';
        
        // Simulate saving quiz
        $sanitized = \MoneyQuiz\Security\XSS\XssProtection::getInstance()
            ->filterInput($malicious_title);
        
        // Verify XSS is removed
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('Quiz', $sanitized);
        
        // Test output escaping
        $output = \MoneyQuiz\Security\XSS\OutputEscaping::escape($malicious_title);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
    
    /**
     * Test SQL + Query building
     */
    public function testSqlQueryIntegration() {
        global $wpdb;
        
        // Malicious input
        $user_input = "1'; DROP TABLE users; --";
        
        // Test query builder protection
        $builder = new \MoneyQuiz\Security\SQL\QueryBuilder();
        $query = $builder->table($wpdb->prefix . 'money_quiz_quizzes')
            ->select(['id', 'title'])
            ->where('id', '=', $user_input)
            ->build();
        
        // Verify query is safe
        $this->assertStringNotContainsString('DROP TABLE', $query);
        $this->assertStringContainsString('%s', $query); // Placeholder
    }
    
    /**
     * Test Rate Limiting + API endpoints
     */
    public function testRateLimitApiIntegration() {
        // Set test user
        wp_set_current_user($this->test_users['subscriber']->ID);
        
        // Configure rate limit
        add_filter('money_quiz_rate_limit_config', function($config, $action) {
            if ($action === 'api_call') {
                return ['attempts' => 2, 'window' => 60, 'lockout' => 300];
            }
            return $config;
        }, 10, 2);
        
        $limiter = \MoneyQuiz\Security\RateLimit\RateLimiter::getInstance();
        
        // First 2 calls should succeed
        for ($i = 1; $i <= 2; $i++) {
            $result = $limiter->checkLimit('api_call');
            $this->assertTrue($result['allowed']);
        }
        
        // 3rd call should fail
        $result = $limiter->checkLimit('api_call');
        $this->assertFalse($result['allowed']);
        $this->assertEquals(429, $result['status_code']);
    }
}

/**
 * Security Headers Integration Test
 */
class SecurityHeadersIntegrationTest extends SecurityTestCase {
    
    /**
     * Test headers on different page types
     */
    public function testHeadersOnPageTypes() {
        $headers = \MoneyQuiz\Security\Headers\SecurityHeaders::getInstance();
        
        // Test admin page headers
        set_current_screen('admin');
        $admin_headers = $headers->getHeaders('admin');
        $this->assertArrayHasKey('X-Frame-Options', $admin_headers);
        $this->assertEquals('SAMEORIGIN', $admin_headers['X-Frame-Options']);
        
        // Test public page headers
        set_current_screen('front');
        $public_headers = $headers->getHeaders('public');
        $this->assertArrayHasKey('X-Content-Type-Options', $public_headers);
        $this->assertEquals('nosniff', $public_headers['X-Content-Type-Options']);
    }
    
    /**
     * Test CSP integration
     */
    public function testCspIntegration() {
        $csp = new \MoneyQuiz\Security\Headers\ContentSecurityPolicy();
        
        // Add nonce for inline scripts
        $nonce = $csp->generateNonce();
        $policy = $csp->getPolicy();
        
        $this->assertStringContainsString("'nonce-{$nonce}'", $policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
    }
}

/**
 * Audit Logging Integration Test
 */
class AuditLoggingIntegrationTest extends SecurityTestCase {
    
    /**
     * Test security event logging
     */
    public function testSecurityEventLogging() {
        $logger = \MoneyQuiz\Security\Audit\AuditLogger::getInstance();
        
        // Simulate failed login
        $logger->logSecurityEvent('login.failed', [
            'username' => 'attacker',
            'ip' => '192.168.1.100'
        ]);
        
        // Verify log entry
        $logs = $logger->getRecentLogs(1);
        $this->assertCount(1, $logs);
        $this->assertEquals('login.failed', $logs[0]['event']);
        $this->assertEquals('attacker', $logs[0]['context']['username']);
    }
    
    /**
     * Test log retention
     */
    public function testLogRetention() {
        $logger = \MoneyQuiz\Security\Audit\AuditLogger::getInstance();
        
        // Create old log entry
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_audit_log',
            [
                'event' => 'test.old',
                'created_at' => date('Y-m-d H:i:s', strtotime('-100 days'))
            ]
        );
        
        // Run retention cleanup
        $logger->cleanupOldLogs();
        
        // Verify old log is removed
        $old_logs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_audit_log WHERE event = %s",
            'test.old'
        ));
        
        $this->assertEquals(0, $old_logs);
    }
}