<?php
/**
 * NonceManager Test
 *
 * @package MoneyQuiz\Tests\Unit\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Tests\Unit\Security;

use MoneyQuiz\Security\NonceManager;
use MoneyQuiz\Tests\TestCase;

/**
 * NonceManager test class.
 *
 * @since 7.0.0
 */
class NonceManagerTest extends TestCase {

	/**
	 * NonceManager instance.
	 *
	 * @var NonceManager
	 */
	private NonceManager $nonce_manager;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->nonce_manager = new NonceManager( 'money_quiz' );
	}

	/**
	 * Test nonce creation.
	 *
	 * @return void
	 */
	public function test_create_nonce(): void {
		$nonce = $this->nonce_manager->create( 'test_action' );

		$this->assertIsString( $nonce );
		$this->assertNotEmpty( $nonce );
	}

	/**
	 * Test nonce verification.
	 *
	 * @return void
	 */
	public function test_verify_nonce(): void {
		$nonce = $this->nonce_manager->create( 'test_action' );
		$is_valid = $this->nonce_manager->verify( $nonce, 'test_action' );

		$this->assertTrue( $is_valid );
	}

	/**
	 * Test invalid nonce verification.
	 *
	 * @return void
	 */
	public function test_verify_invalid_nonce(): void {
		$is_valid = $this->nonce_manager->verify( 'invalid_nonce', 'test_action' );

		$this->assertFalse( $is_valid );
	}

	/**
	 * Test nonce field generation.
	 *
	 * @return void
	 */
	public function test_nonce_field(): void {
		$field = $this->nonce_manager->field( 'test_action', '_wpnonce', true, false );

		$this->assertStringContainsString( '<input type="hidden"', $field );
		$this->assertStringContainsString( 'name="_wpnonce"', $field );
		$this->assertStringContainsString( 'value="', $field );
	}

	/**
	 * Test nonce URL generation.
	 *
	 * @return void
	 */
	public function test_nonce_url(): void {
		$url = 'https://example.com/test';
		$nonce_url = $this->nonce_manager->url( $url, 'test_action' );

		$this->assertStringContainsString( $url, $nonce_url );
		$this->assertStringContainsString( '_wpnonce=', $nonce_url );
	}

	/**
	 * Test request verification.
	 *
	 * @return void
	 */
	public function test_verify_request(): void {
		$action = 'test_action';
		$nonce = $this->nonce_manager->create( $action );
		
		// Simulate request.
		$_REQUEST['_wpnonce'] = $nonce;
		
		$is_valid = $this->nonce_manager->verify_request( $action );
		
		$this->assertTrue( $is_valid );
		
		// Clean up.
		unset( $_REQUEST['_wpnonce'] );
	}

	/**
	 * Test request verification with custom query arg.
	 *
	 * @return void
	 */
	public function test_verify_request_custom_arg(): void {
		$action = 'test_action';
		$nonce = $this->nonce_manager->create( $action );
		
		// Simulate request with custom arg.
		$_REQUEST['custom_nonce'] = $nonce;
		
		$is_valid = $this->nonce_manager->verify_request( $action, 'custom_nonce' );
		
		$this->assertTrue( $is_valid );
		
		// Clean up.
		unset( $_REQUEST['custom_nonce'] );
	}

	/**
	 * Test lifetime getter.
	 *
	 * @return void
	 */
	public function test_get_lifetime(): void {
		$lifetime = $this->nonce_manager->get_lifetime();
		
		$this->assertIsInt( $lifetime );
		$this->assertGreaterThan( 0, $lifetime );
	}
}