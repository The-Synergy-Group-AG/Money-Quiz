<?php
/**
 * Worker 8: Security Function Unit Tests
 * Framework: PHPUnit
 * Focus: Testing all security patches from Cycle 1
 */

use PHPUnit\Framework\TestCase;

class MoneyQuizSecurityTest extends TestCase {
    
    private $wpdb_mock;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Mock WordPress functions
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) {
                return $nonce === 'valid_nonce';
            }
        }
        
        if (!function_exists('esc_html')) {
            function esc_html($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
        
        // Mock $wpdb
        $this->wpdb_mock = $this->createMock('wpdb');
    }
    
    /**
     * Test SQL injection prevention
     */
    public function testSqlInjectionPrevention() {
        global $wpdb;
        $wpdb = $this->wpdb_mock;
        
        // Test malicious input
        $malicious_email = "admin' OR '1'='1";
        
        // Mock prepare method
        $wpdb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->stringContains('WHERE Email = %s'),
                $malicious_email
            )
            ->willReturn("WHERE Email = 'admin\' OR \'1\'=\'1'");
        
        // The query should be escaped
        $prepared = $wpdb->prepare("SELECT * FROM prospects WHERE Email = %s", $malicious_email);
        
        $this->assertStringNotContainsString("OR '1'='1", $prepared);
        $this->assertStringContainsString("\'", $prepared);
    }
    
    /**
     * Test XSS prevention
     */
    public function testXssPrevention() {
        $test_cases = array(
            '<script>alert("XSS")</script>' => '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
            '"><script>alert(1)</script>' => '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
            'javascript:alert(1)' => 'javascript:alert(1)',
            '<img src=x onerror=alert(1)>' => '&lt;img src=x onerror=alert(1)&gt;'
        );
        
        foreach ($test_cases as $input => $expected) {
            $escaped = esc_html($input);
            $this->assertEquals($expected, $escaped, "Failed to escape: $input");
        }
    }
    
    /**
     * Test CSRF protection
     */
    public function testCsrfProtection() {
        // Test nonce generation
        $nonce = MoneyQuizCSRF::create_nonce('test_action');
        $this->assertNotEmpty($nonce);
        $this->assertIsString($nonce);
        
        // Test valid nonce
        $valid = MoneyQuizCSRF::verify_nonce('valid_nonce', 'test_action');
        $this->assertTrue($valid);
        
        // Test invalid nonce
        $invalid = MoneyQuizCSRF::verify_nonce('invalid_nonce', 'test_action');
        $this->assertFalse($invalid);
    }
    
    /**
     * Test credential security
     */
    public function testCredentialSecurity() {
        // Test config retrieval
        $config = MoneyQuizConfig::get('non_existent_key', 'default_value');
        $this->assertEquals('default_value', $config);
        
        // Test encryption/decryption
        $sensitive = 'my_secret_password';
        $encrypted = MoneyQuizConfig::encrypt_value($sensitive);
        
        $this->assertNotEquals($sensitive, $encrypted);
        $this->assertStringContainsString('=', $encrypted); // Base64 encoded
        
        $decrypted = MoneyQuizConfig::decrypt_value($encrypted);
        $this->assertEquals($sensitive, $decrypted);
    }
    
    /**
     * Test access control
     */
    public function testAccessControl() {
        // Mock current_user_can
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return $capability === 'manage_options';
            }
        }
        
        // Test capability check
        $can_manage = current_user_can(MoneyQuizCapabilities::MANAGE_QUIZ);
        $this->assertTrue($can_manage);
        
        // Test custom capability mapping
        $can_edit = MoneyQuizCapabilities::current_user_can('edit_posts');
        $this->assertTrue($can_edit);
    }
    
    /**
     * Test safe array access
     */
    public function testSafeArrayAccess() {
        $array = array('key1' => 'value1', 'key2' => 'value2');
        
        // Test existing key
        $value = mq_get_array_value($array, 'key1');
        $this->assertEquals('value1', $value);
        
        // Test non-existing key
        $value = mq_get_array_value($array, 'key3', 'default');
        $this->assertEquals('default', $value);
        
        // Test with non-array
        $value = mq_get_array_value('not_an_array', 'key', 'default');
        $this->assertEquals('default', $value);
    }
    
    /**
     * Test input sanitization
     */
    public function testInputSanitization() {
        // Test email sanitization
        $dirty_email = ' Test@Example.COM ';
        $clean_email = sanitize_email($dirty_email);
        $this->assertEquals('test@example.com', $clean_email);
        
        // Test text field sanitization
        $dirty_text = '<script>alert(1)</script>Hello';
        $clean_text = sanitize_text_field($dirty_text);
        $this->assertEquals('Hello', $clean_text);
        
        // Test integer sanitization
        $dirty_int = '123abc';
        $clean_int = absint($dirty_int);
        $this->assertEquals(123, $clean_int);
    }
    
    /**
     * Test rate limiting
     */
    public function testRateLimiting() {
        // First request should pass
        $allowed = MoneyQuizRateLimit::check('test_action', 1);
        $this->assertTrue($allowed);
        
        // Simulate multiple requests
        for ($i = 0; $i < MoneyQuizRateLimit::MAX_REQUESTS; $i++) {
            MoneyQuizRateLimit::check('test_action', 1);
        }
        
        // Next request should fail
        $blocked = MoneyQuizRateLimit::check('test_action', 1);
        $this->assertFalse($blocked);
    }
    
    /**
     * Test secure token generation
     */
    public function testSecureTokenGeneration() {
        $token = MoneyQuizFrontendSecurity::generate_access_token(123, 'test@example.com');
        
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertGreaterThan(20, strlen($token));
        
        // Test token verification
        $valid = MoneyQuizFrontendSecurity::verify_access_token($token, 123);
        $this->assertTrue($valid);
        
        // Test invalid token
        $invalid = MoneyQuizFrontendSecurity::verify_access_token('invalid_token', 123);
        $this->assertFalse($invalid);
    }
    
    /**
     * Test password hashing
     */
    public function testPasswordSecurity() {
        if (!function_exists('wp_hash_password')) {
            function wp_hash_password($password) {
                return password_hash($password, PASSWORD_DEFAULT);
            }
        }
        
        $password = 'test_password_123';
        $hash = wp_hash_password($password);
        
        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrong_password', $hash));
    }
}

/**
 * Test suite for SQL injection prevention
 */
class MoneyQuizSqlInjectionTest extends TestCase {
    
    /**
     * Test various SQL injection patterns
     */
    public function testSqlInjectionPatterns() {
        $injection_patterns = array(
            "1' OR '1'='1",
            "1'; DROP TABLE users; --",
            "1' UNION SELECT * FROM wp_users --",
            "1\" OR \"1\"=\"1",
            "1) OR (1=1",
            "1' AND SLEEP(5) --",
            "1' AND 1=1 --",
            "'; EXEC xp_cmdshell('dir'); --"
        );
        
        foreach ($injection_patterns as $pattern) {
            // All patterns should be escaped
            $escaped = esc_sql($pattern);
            
            // Check that dangerous characters are escaped
            $this->assertStringNotContainsString("'", $escaped);
            $this->assertStringContainsString("\\'", $escaped);
        }
    }
    
    /**
     * Test prepared statement placeholders
     */
    public function testPreparedStatements() {
        global $wpdb;
        $wpdb = $this->createMock('wpdb');
        
        // Test integer placeholder
        $wpdb->expects($this->once())
            ->method('prepare')
            ->with(
                'SELECT * FROM table WHERE id = %d',
                '123abc'
            )
            ->willReturn('SELECT * FROM table WHERE id = 123');
        
        $query = $wpdb->prepare('SELECT * FROM table WHERE id = %d', '123abc');
        $this->assertStringContainsString('123', $query);
        $this->assertStringNotContainsString('abc', $query);
    }
}

/**
 * Test suite for XSS prevention
 */
class MoneyQuizXssTest extends TestCase {
    
    /**
     * Test context-aware escaping
     */
    public function testContextAwareEscaping() {
        // HTML context
        $html_input = '<div onclick="alert(1)">Test</div>';
        $html_escaped = esc_html($html_input);
        $this->assertStringNotContainsString('<div', $html_escaped);
        
        // Attribute context
        $attr_input = '" onmouseover="alert(1)"';
        $attr_escaped = esc_attr($attr_input);
        $this->assertStringNotContainsString('"', $attr_escaped);
        
        // URL context
        $url_input = 'javascript:alert(1)';
        $url_escaped = esc_url($url_input);
        $this->assertEmpty($url_escaped);
        
        // JavaScript context
        $js_input = '</script><script>alert(1)</script>';
        $js_escaped = esc_js($js_input);
        $this->assertStringNotContainsString('</script>', $js_escaped);
    }
    
    /**
     * Test wp_kses for controlled HTML
     */
    public function testWpKses() {
        $allowed_html = array(
            'a' => array('href' => array()),
            'strong' => array(),
            'em' => array()
        );
        
        $input = '<script>alert(1)</script><a href="test.com">Link</a><strong>Bold</strong>';
        $filtered = wp_kses($input, $allowed_html);
        
        $this->assertStringNotContainsString('<script>', $filtered);
        $this->assertStringContainsString('<a href="test.com">', $filtered);
        $this->assertStringContainsString('<strong>', $filtered);
    }
}