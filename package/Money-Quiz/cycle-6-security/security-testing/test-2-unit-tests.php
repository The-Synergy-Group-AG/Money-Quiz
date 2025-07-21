<?php
/**
 * Security Unit Tests
 * 
 * @package MoneyQuiz\Security\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Testing;

/**
 * Input Validation Tests
 */
class InputValidationTest extends SecurityTestCase {
    
    /**
     * Test text sanitization
     */
    public function testTextSanitization() {
        $inputs = [
            '<script>alert("XSS")</script>' => '',
            'Normal text' => 'Normal text',
            'Text with <b>HTML</b>' => 'Text with HTML',
            "Line1\nLine2" => 'Line1 Line2',
            '   Trimmed   ' => 'Trimmed'
        ];
        
        foreach ($inputs as $input => $expected) {
            $result = \MoneyQuiz\Security\Validation\InputSanitization::sanitize_text($input);
            $this->assertEquals($expected, $result);
        }
    }
    
    /**
     * Test email validation
     */
    public function testEmailValidation() {
        $valid_emails = [
            'test@example.com',
            'user+tag@domain.co.uk',
            'name.surname@company.org'
        ];
        
        $invalid_emails = [
            'not-an-email',
            '@example.com',
            'user@',
            'user space@example.com',
            '<script>@example.com'
        ];
        
        foreach ($valid_emails as $email) {
            $result = \MoneyQuiz\Security\Validation\InputSanitization::sanitize_email($email);
            $this->assertEquals($email, $result);
        }
        
        foreach ($invalid_emails as $email) {
            $result = \MoneyQuiz\Security\Validation\InputSanitization::sanitize_email($email);
            $this->assertEquals('', $result);
        }
    }
    
    /**
     * Test number validation
     */
    public function testNumberValidation() {
        // Integer validation
        $result = \MoneyQuiz\Security\Validation\InputSanitization::sanitize_number('123', 'int');
        $this->assertEquals(123, $result);
        
        // Float validation
        $result = \MoneyQuiz\Security\Validation\InputSanitization::sanitize_number('123.45', 'float');
        $this->assertEquals(123.45, $result);
        
        // Min/max constraints
        $result = \MoneyQuiz\Security\Validation\InputSanitization::sanitize_number(
            '150', 'int', ['min' => 0, 'max' => 100]
        );
        $this->assertEquals(100, $result);
    }
}

/**
 * CSRF Protection Tests
 */
class CsrfProtectionTest extends SecurityTestCase {
    
    private $csrf;
    
    protected function setUp(): void {
        parent::setUp();
        $this->csrf = \MoneyQuiz\Security\CSRF\CsrfProtection::getInstance();
    }
    
    /**
     * Test token generation
     */
    public function testTokenGeneration() {
        $generator = new \MoneyQuiz\Security\CSRF\CsrfTokenGenerator(
            new \MoneyQuiz\Security\CSRF\CsrfSessionStorage()
        );
        
        $token1 = $generator->generate();
        $token2 = $generator->generate();
        
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
    }
    
    /**
     * Test token validation
     */
    public function testTokenValidation() {
        $storage = new \MoneyQuiz\Security\CSRF\CsrfSessionStorage();
        $generator = new \MoneyQuiz\Security\CSRF\CsrfTokenGenerator($storage);
        $validator = new \MoneyQuiz\Security\CSRF\CsrfTokenValidator($storage);
        
        // Generate token
        $token = $generator->generate('test_action');
        
        // Valid token
        $this->assertTrue($validator->validate($token, 'test_action'));
        
        // Token should be removed after validation (one-time use)
        $this->expectException(\MoneyQuiz\Security\CSRF\CsrfException::class);
        $validator->validate($token, 'test_action');
    }
    
    /**
     * Test invalid token
     */
    public function testInvalidToken() {
        $validator = new \MoneyQuiz\Security\CSRF\CsrfTokenValidator(
            new \MoneyQuiz\Security\CSRF\CsrfSessionStorage()
        );
        
        $this->expectException(\MoneyQuiz\Security\CSRF\CsrfException::class);
        $validator->validate('invalid_token', 'test_action');
    }
}

/**
 * XSS Protection Tests
 */
class XssProtectionTest extends SecurityTestCase {
    
    /**
     * Test HTML filtering
     */
    public function testHtmlFiltering() {
        $filter = new \MoneyQuiz\Security\XSS\HtmlFilter();
        
        $tests = [
            '<script>alert("XSS")</script>' => '',
            '<p onclick="alert()">Text</p>' => '<p>Text</p>',
            '<img src="x" onerror="alert()">' => '',
            '<a href="javascript:void(0)">Link</a>' => '<a>Link</a>',
            '<p>Safe <strong>HTML</strong></p>' => '<p>Safe <strong>HTML</strong></p>'
        ];
        
        foreach ($tests as $input => $expected) {
            $result = $filter->filter($input);
            $this->assertEquals($expected, $result);
        }
    }
    
    /**
     * Test JavaScript escaping
     */
    public function testJavaScriptEscaping() {
        $filter = new \MoneyQuiz\Security\XSS\JavaScriptFilter();
        
        $tests = [
            'alert("XSS")' => 'alert\\x28\\x22XSS\\x22\\x29',
            "Line1\nLine2" => 'Line1\\nLine2',
            '</script>' => '\\x3C\\x2Fscript\\x3E'
        ];
        
        foreach ($tests as $input => $expected) {
            $result = $filter->escape($input);
            $this->assertEquals($expected, $result);
        }
    }
    
    /**
     * Test CSS filtering
     */
    public function testCssFiltering() {
        $filter = new \MoneyQuiz\Security\XSS\CssFilter();
        
        $tests = [
            'color: red; behavior: url(xss.htc)' => 'color: red',
            'background: url(javascript:alert())' => '',
            'width: expression(alert())' => '',
            'color: blue; font-size: 14px' => 'color: blue; font-size: 14px'
        ];
        
        foreach ($tests as $input => $expected) {
            $result = $filter->filter($input);
            $this->assertStringContainsString($expected, $result);
        }
    }
}

/**
 * SQL Injection Tests
 */
class SqlInjectionTest extends SecurityTestCase {
    
    /**
     * Test SQL validation
     */
    public function testSqlValidation() {
        $validator = new \MoneyQuiz\Security\SQL\SqlValidator();
        
        $malicious_inputs = [
            "' OR '1'='1",
            "1; DROP TABLE users",
            "1 UNION SELECT * FROM passwords",
            "admin'--",
            "1' AND SLEEP(5)#"
        ];
        
        foreach ($malicious_inputs as $input) {
            $this->assertFalse(
                \MoneyQuiz\Security\SQL\SqlValidator::validate($input),
                "Failed to detect SQL injection: {$input}"
            );
        }
        
        // Valid inputs
        $valid_inputs = [
            'normal text',
            '123',
            'user@example.com'
        ];
        
        foreach ($valid_inputs as $input) {
            $this->assertTrue(
                \MoneyQuiz\Security\SQL\SqlValidator::validate($input),
                "False positive for: {$input}"
            );
        }
    }
    
    /**
     * Test query builder
     */
    public function testQueryBuilder() {
        $builder = new \MoneyQuiz\Security\SQL\QueryBuilder();
        
        // Test SELECT query
        $query = $builder->table('test')
            ->select(['id', 'name'])
            ->where('status', '=', 'active')
            ->orderBy('created', 'DESC')
            ->limit(10)
            ->build();
        
        $this->assertStringContainsString('SELECT', $query);
        $this->assertStringContainsString('WHERE', $query);
        $this->assertStringContainsString('ORDER BY', $query);
        $this->assertStringContainsString('LIMIT 10', $query);
    }
}

/**
 * Rate Limiting Tests
 */
class RateLimitingTest extends SecurityTestCase {
    
    /**
     * Test rate limit enforcement
     */
    public function testRateLimitEnforcement() {
        $storage = new \MoneyQuiz\Security\RateLimit\MemoryStorage();
        $limiter = new \MoneyQuiz\Security\RateLimit\RateLimitEnforcer($storage);
        
        // Configure test rate limit
        add_filter('money_quiz_rate_limit_config', function($config, $action) {
            if ($action === 'test') {
                return [
                    'attempts' => 3,
                    'window' => 60,
                    'lockout' => 300
                ];
            }
            return $config;
        }, 10, 2);
        
        // First 3 attempts should pass
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue($limiter->enforce('test_user', 'test'));
        }
        
        // 4th attempt should fail
        $this->expectException(\MoneyQuiz\Security\RateLimit\RateLimitException::class);
        $limiter->enforce('test_user', 'test');
    }
    
    /**
     * Test rate limit headers
     */
    public function testRateLimitHeaders() {
        $storage = new \MoneyQuiz\Security\RateLimit\MemoryStorage();
        $limiter = new \MoneyQuiz\Security\RateLimit\RateLimitEnforcer($storage);
        
        $headers = $limiter->getHeaders('test_user', 'default');
        
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Window', $headers);
    }
}