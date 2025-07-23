<?php
/**
 * Logger test
 *
 * @package MoneyQuiz\Tests\Unit\Core\Logging
 */

namespace MoneyQuiz\Tests\Unit\Core\Logging;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Core\Logging\Logger;

/**
 * Logger test class.
 *
 * @covers \MoneyQuiz\Core\Logging\Logger
 */
class LoggerTest extends TestCase {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Test log directory.
	 *
	 * @var string
	 */
	private string $log_dir;

	/**
	 * Set up test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create temp log directory.
		$this->log_dir = sys_get_temp_dir() . '/money-quiz-test-logs/';
		wp_mkdir_p( $this->log_dir );

		$this->logger = new Logger( $this->log_dir );
	}

	/**
	 * Tear down test.
	 */
	protected function tearDown(): void {
		parent::tearDown();

		// Clean up test logs.
		$files = glob( $this->log_dir . '*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		rmdir( $this->log_dir );
	}

	/**
	 * Test password sanitization.
	 */
	public function test_password_sanitization(): void {
		$patterns = $this->logger->get_sanitization_patterns();

		// Test various password formats.
		$test_cases = [
			'password: secret123' => 'password: [REDACTED]',
			'password="secret123"' => 'password: [REDACTED]',
			"password='secret123'" => 'password: [REDACTED]',
			'password:secret123' => 'password: [REDACTED]',
		];

		foreach ( $test_cases as $input => $expected ) {
			$result = $input;
			foreach ( $patterns as $pattern => $replacement ) {
				$result = preg_replace( $pattern, $replacement, $result );
			}

			$this->assertStringContainsString( '[REDACTED]', $result );
			$this->assertStringNotContainsString( 'secret123', $result );
		}
	}

	/**
	 * Test API key sanitization.
	 */
	public function test_api_key_sanitization(): void {
		$patterns = $this->logger->get_sanitization_patterns();

		$input = 'api_key: xai-12345abcdef';
		$result = $input;

		foreach ( $patterns as $pattern => $replacement ) {
			$result = preg_replace( $pattern, $replacement, $result );
		}

		$this->assertEquals( 'api_key: [REDACTED]', $result );
	}

	/**
	 * Test email sanitization.
	 */
	public function test_email_sanitization(): void {
		$patterns = $this->logger->get_sanitization_patterns();

		$input = 'User email: user@example.com logged in';
		$result = $input;

		foreach ( $patterns as $pattern => $replacement ) {
			$result = preg_replace( $pattern, $replacement, $result );
		}

		$this->assertEquals( 'User email: [EMAIL] logged in', $result );
	}

	/**
	 * Test credit card sanitization.
	 */
	public function test_credit_card_sanitization(): void {
		$patterns = $this->logger->get_sanitization_patterns();

		$test_cases = [
			'1234567812345678' => '[CREDIT_CARD]',
			'1234-5678-1234-5678' => '[CREDIT_CARD]',
			'1234 5678 1234 5678' => '[CREDIT_CARD]',
		];

		foreach ( $test_cases as $input => $expected ) {
			$result = $input;
			foreach ( $patterns as $pattern => $replacement ) {
				$result = preg_replace( $pattern, $replacement, $result );
			}

			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * Test array sanitization.
	 */
	public function test_array_sanitization(): void {
		$logger = new Logger( $this->log_dir );

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $logger );
		$method = $reflection->getMethod( 'sanitize_array' );
		$method->setAccessible( true );

		$input = [
			'username' => 'testuser',
			'password' => 'secret123',
			'api_key' => 'xai-12345',
			'data' => [
				'token' => 'bearer-123',
				'email' => 'user@example.com',
			],
		];

		$result = $method->invoke( $logger, $input );

		$this->assertEquals( 'testuser', $result['username'] );
		$this->assertEquals( '[REDACTED]', $result['password'] );
		$this->assertEquals( '[REDACTED]', $result['api_key'] );
		$this->assertEquals( '[REDACTED]', $result['data']['token'] );
		$this->assertEquals( '[EMAIL]', $result['data']['email'] );
	}

	/**
	 * Test custom sanitization pattern.
	 */
	public function test_custom_sanitization_pattern(): void {
		$this->logger->add_sanitization_pattern(
			'/custom_secret:\s*([^\s]+)/i',
			'custom_secret: [HIDDEN]'
		);

		$patterns = $this->logger->get_sanitization_patterns();
		$input = 'custom_secret: my_secret_value';
		$result = $input;

		foreach ( $patterns as $pattern => $replacement ) {
			$result = preg_replace( $pattern, $replacement, $result );
		}

		$this->assertEquals( 'custom_secret: [HIDDEN]', $result );
	}

	/**
	 * Test log file creation.
	 */
	public function test_log_file_creation(): void {
		$this->logger->info( 'Test log entry' );

		$log_file = $this->log_dir . 'money-quiz-' . gmdate( 'Y-m-d' ) . '.log';
		$this->assertFileExists( $log_file );

		$content = file_get_contents( $log_file );
		$this->assertStringContainsString( '[INFO] Test log entry', $content );
	}

	/**
	 * Test debug logging respects WP_DEBUG.
	 */
	public function test_debug_logging_respects_wp_debug(): void {
		// Temporarily disable WP_DEBUG.
		$original = defined( 'WP_DEBUG' ) ? WP_DEBUG : false;
		
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', false );
		}

		$this->logger->debug( 'Debug message' );

		$log_file = $this->log_dir . 'money-quiz-' . gmdate( 'Y-m-d' ) . '.log';
		
		if ( file_exists( $log_file ) ) {
			$content = file_get_contents( $log_file );
			$this->assertStringNotContainsString( '[DEBUG]', $content );
		}
	}
}