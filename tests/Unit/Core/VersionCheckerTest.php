<?php
/**
 * Version Checker test
 *
 * @package MoneyQuiz\Tests\Unit\Core
 */

namespace MoneyQuiz\Tests\Unit\Core;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Core\VersionChecker;

/**
 * Version checker test class.
 *
 * @covers \MoneyQuiz\Core\VersionChecker
 */
class VersionCheckerTest extends TestCase {

	/**
	 * Version checker instance.
	 *
	 * @var VersionChecker
	 */
	private VersionChecker $checker;

	/**
	 * Set up test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->checker = new VersionChecker(
			'7.0.0',
			'7.4',
			'5.9',
			'https://api.moneyquiz.com/v1/updates'
		);
	}

	/**
	 * Test compatibility check with valid environment.
	 */
	public function test_compatibility_check_valid(): void {
		$result = $this->checker->check_compatibility();

		// Assuming test environment meets requirements.
		$this->assertTrue( $result['compatible'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test version comparison.
	 */
	public function test_version_comparison(): void {
		$this->assertTrue( $this->checker->compare( '6.0.0', '>' ) );
		$this->assertTrue( $this->checker->compare( '7.0.0', '=' ) );
		$this->assertFalse( $this->checker->compare( '8.0.0', '>' ) );
	}

	/**
	 * Test get version.
	 */
	public function test_get_version(): void {
		$this->assertEquals( '7.0.0', $this->checker->get_version() );
	}

	/**
	 * Test version history.
	 */
	public function test_version_history(): void {
		$history = $this->checker->get_history();

		$this->assertIsArray( $history );
		$this->assertArrayHasKey( '7.0.0', $history );
		$this->assertArrayHasKey( '3.22.10', $history );
	}

	/**
	 * Test update check caching.
	 */
	public function test_update_check_caching(): void {
		// Mock transient functions for testing.
		add_filter( 'pre_transient_money_quiz_update_check', function() {
			return [
				'available' => true,
				'version' => '7.1.0',
				'url' => 'https://example.com/update',
			];
		} );

		$result = $this->checker->check_for_updates();

		$this->assertIsArray( $result );
		$this->assertTrue( $result['available'] );
		$this->assertEquals( '7.1.0', $result['version'] );
	}
}