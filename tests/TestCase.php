<?php
/**
 * Base test case
 *
 * @package MoneyQuiz\Tests
 */

namespace MoneyQuiz\Tests;

use WP_UnitTestCase;
use MoneyQuiz\Core\Container;

/**
 * Base test case class.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Set up test case.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create fresh container for each test.
		$this->container = new Container();
		Container::set_instance( $this->container );
	}

	/**
	 * Tear down test case.
	 */
	protected function tearDown(): void {
		parent::tearDown();

		// Clear container.
		$this->container->clear();
	}

	/**
	 * Assert WordPress error.
	 *
	 * @param mixed $actual Value to check.
	 */
	protected function assertWPError( $actual ): void {
		$this->assertInstanceOf( 'WP_Error', $actual );
	}

	/**
	 * Assert not WordPress error.
	 *
	 * @param mixed $actual Value to check.
	 */
	protected function assertNotWPError( $actual ): void {
		$this->assertNotInstanceOf( 'WP_Error', $actual );
	}
}