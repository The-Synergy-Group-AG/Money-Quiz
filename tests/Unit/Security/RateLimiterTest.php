<?php
/**
 * RateLimiter Test
 *
 * @package MoneyQuiz\Tests\Unit\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Tests\Unit\Security;

use MoneyQuiz\Security\RateLimiter;
use MoneyQuiz\Core\Exceptions\RateLimitExceededException;
use MoneyQuiz\Tests\TestCase;

/**
 * RateLimiter test class.
 *
 * @since 7.0.0
 */
class RateLimiterTest extends TestCase {

	/**
	 * RateLimiter instance.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		
		global $wpdb;
		
		// Create test table.
		$table = $wpdb->prefix . 'money_quiz_rate_limits';
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table} (
				identifier VARCHAR(255) NOT NULL,
				action VARCHAR(255) NOT NULL,
				attempts INT NOT NULL DEFAULT 0,
				window_start DATETIME NOT NULL,
				PRIMARY KEY (identifier, action)
			)"
		);
		
		$this->rate_limiter = new RateLimiter( $wpdb, 'money_quiz' );
	}

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'money_quiz_rate_limits';
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		
		parent::tearDown();
	}

	/**
	 * Test rate limit check within limit.
	 *
	 * @return void
	 */
	public function test_check_within_limit(): void {
		$identifier = 'test_user';
		$action = 'test_action';
		$limit = 5;
		$window = 3600;
		
		// First attempt should succeed.
		$result = $this->rate_limiter->check( $identifier, $action, $limit, $window );
		$this->assertTrue( $result );
		
		// Additional attempts within limit should succeed.
		for ( $i = 0; $i < 3; $i++ ) {
			$result = $this->rate_limiter->check( $identifier, $action, $limit, $window );
			$this->assertTrue( $result );
		}
	}

	/**
	 * Test rate limit exceeded.
	 *
	 * @return void
	 */
	public function test_check_exceeds_limit(): void {
		$identifier = 'test_user_2';
		$action = 'test_action';
		$limit = 3;
		$window = 3600;
		
		// Use up the limit.
		for ( $i = 0; $i < $limit; $i++ ) {
			$this->rate_limiter->check( $identifier, $action, $limit, $window );
		}
		
		// Next attempt should throw exception.
		$this->expectException( RateLimitExceededException::class );
		$this->rate_limiter->check( $identifier, $action, $limit, $window );
	}

	/**
	 * Test get remaining attempts.
	 *
	 * @return void
	 */
	public function test_get_remaining_attempts(): void {
		$identifier = 'test_user_3';
		$action = 'test_action';
		$limit = 10;
		$window = 3600;
		
		// Initially should have all attempts.
		$remaining = $this->rate_limiter->get_remaining_attempts( $identifier, $action, $limit, $window );
		$this->assertEquals( $limit, $remaining );
		
		// Use some attempts.
		$this->rate_limiter->check( $identifier, $action, $limit, $window );
		$this->rate_limiter->check( $identifier, $action, $limit, $window );
		
		// Should have fewer remaining.
		$remaining = $this->rate_limiter->get_remaining_attempts( $identifier, $action, $limit, $window );
		$this->assertEquals( $limit - 2, $remaining );
	}

	/**
	 * Test reset rate limit.
	 *
	 * @return void
	 */
	public function test_reset(): void {
		$identifier = 'test_user_4';
		$action = 'test_action';
		$limit = 3;
		$window = 3600;
		
		// Use up the limit.
		for ( $i = 0; $i < $limit; $i++ ) {
			$this->rate_limiter->check( $identifier, $action, $limit, $window );
		}
		
		// Reset the limit.
		$this->rate_limiter->reset( $identifier, $action );
		
		// Should be able to check again.
		$result = $this->rate_limiter->check( $identifier, $action, $limit, $window );
		$this->assertTrue( $result );
	}

	/**
	 * Test cleanup old entries.
	 *
	 * @return void
	 */
	public function test_cleanup(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'money_quiz_rate_limits';
		
		// Insert old entry.
		$wpdb->insert(
			$table,
			[
				'identifier' => 'old_user',
				'action' => 'old_action',
				'attempts' => 5,
				'window_start' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) ),
			]
		);
		
		// Insert recent entry.
		$wpdb->insert(
			$table,
			[
				'identifier' => 'recent_user',
				'action' => 'recent_action',
				'attempts' => 3,
				'window_start' => current_time( 'mysql', true ),
			]
		);
		
		// Run cleanup.
		$this->rate_limiter->cleanup();
		
		// Old entry should be gone.
		$old_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE identifier = 'old_user'"
		);
		$this->assertEquals( 0, $old_count );
		
		// Recent entry should remain.
		$recent_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE identifier = 'recent_user'"
		);
		$this->assertEquals( 1, $recent_count );
	}

	/**
	 * Test rate limit exception contains retry after.
	 *
	 * @return void
	 */
	public function test_exception_retry_after(): void {
		$identifier = 'test_user_5';
		$action = 'test_action';
		$limit = 1;
		$window = 60;
		
		// Use up the limit.
		$this->rate_limiter->check( $identifier, $action, $limit, $window );
		
		try {
			$this->rate_limiter->check( $identifier, $action, $limit, $window );
			$this->fail( 'Expected RateLimitExceededException' );
		} catch ( RateLimitExceededException $e ) {
			$retry_after = $e->getRetryAfter();
			$this->assertIsInt( $retry_after );
			$this->assertGreaterThan( 0, $retry_after );
			$this->assertLessThanOrEqual( $window, $retry_after );
		}
	}
}