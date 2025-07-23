<?php
/**
 * AccessControl Test
 *
 * @package MoneyQuiz\Tests\Unit\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Tests\Unit\Security;

use MoneyQuiz\Security\AccessControl;
use MoneyQuiz\Tests\TestCase;

/**
 * AccessControl test class.
 *
 * @since 7.0.0
 */
class AccessControlTest extends TestCase {

	/**
	 * AccessControl instance.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access_control;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private int $test_user_id;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->access_control = new AccessControl();
		
		// Create test user.
		$this->test_user_id = wp_create_user( 'testuser', 'testpass', 'test@example.com' );
	}

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Remove test user.
		if ( $this->test_user_id ) {
			wp_delete_user( $this->test_user_id );
		}
		
		// Remove capabilities.
		$this->access_control->remove_capabilities();
		
		parent::tearDown();
	}

	/**
	 * Test capability check for logged out user.
	 *
	 * @return void
	 */
	public function test_can_logged_out_user(): void {
		// Ensure logged out.
		wp_set_current_user( 0 );
		
		$result = $this->access_control->can( 'manage_money_quiz' );
		$this->assertFalse( $result );
	}

	/**
	 * Test capability check for admin user.
	 *
	 * @return void
	 */
	public function test_can_admin_user(): void {
		// Create admin user.
		$admin_id = wp_create_user( 'testadmin', 'testpass', 'admin@example.com' );
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_role( 'administrator' );
		
		// Set as current user.
		wp_set_current_user( $admin_id );
		
		// Add capabilities.
		$this->access_control->add_capabilities();
		
		// Admin should have all capabilities.
		$this->assertTrue( $this->access_control->can( 'manage_money_quiz' ) );
		$this->assertTrue( $this->access_control->can_manage() );
		$this->assertTrue( $this->access_control->can_edit() );
		$this->assertTrue( $this->access_control->can_delete() );
		$this->assertTrue( $this->access_control->can_publish() );
		$this->assertTrue( $this->access_control->can_view_analytics() );
		$this->assertTrue( $this->access_control->can_export() );
		
		// Clean up.
		wp_delete_user( $admin_id );
	}

	/**
	 * Test capability check for editor user.
	 *
	 * @return void
	 */
	public function test_can_editor_user(): void {
		// Create editor user.
		$editor_id = wp_create_user( 'testeditor', 'testpass', 'editor@example.com' );
		$editor = get_user_by( 'id', $editor_id );
		$editor->add_role( 'editor' );
		
		// Set as current user.
		wp_set_current_user( $editor_id );
		
		// Add capabilities.
		$this->access_control->add_capabilities();
		
		// Editor should have some capabilities.
		$this->assertFalse( $this->access_control->can( 'manage_money_quiz' ) );
		$this->assertTrue( $this->access_control->can_edit() );
		$this->assertFalse( $this->access_control->can_delete() );
		$this->assertTrue( $this->access_control->can_publish() );
		$this->assertTrue( $this->access_control->can_view_analytics() );
		$this->assertFalse( $this->access_control->can_export() );
		
		// Clean up.
		wp_delete_user( $editor_id );
	}

	/**
	 * Test add and remove capabilities.
	 *
	 * @return void
	 */
	public function test_add_remove_capabilities(): void {
		// Add capabilities.
		$this->access_control->add_capabilities();
		
		// Check admin role has capability.
		$admin_role = get_role( 'administrator' );
		$this->assertTrue( $admin_role->has_cap( 'manage_money_quiz' ) );
		
		// Remove capabilities.
		$this->access_control->remove_capabilities();
		
		// Check capability removed.
		$admin_role = get_role( 'administrator' );
		$this->assertFalse( $admin_role->has_cap( 'manage_money_quiz' ) );
	}

	/**
	 * Test filter content.
	 *
	 * @return void
	 */
	public function test_filter_content(): void {
		// Set non-admin user.
		wp_set_current_user( $this->test_user_id );
		
		$secret_content = 'Secret admin content';
		$default_content = 'Public content';
		
		$result = $this->access_control->filter_content(
			$secret_content,
			'manage_money_quiz',
			$default_content
		);
		
		$this->assertEquals( $default_content, $result );
	}

	/**
	 * Test get user role.
	 *
	 * @return void
	 */
	public function test_get_user_role(): void {
		// Test with admin.
		$admin_id = wp_create_user( 'roletest', 'testpass', 'role@example.com' );
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_role( 'administrator' );
		
		$role = $this->access_control->get_user_role( $admin_id );
		$this->assertEquals( 'administrator', $role );
		
		// Test with no user.
		$role = $this->access_control->get_user_role( 99999 );
		$this->assertNull( $role );
		
		// Clean up.
		wp_delete_user( $admin_id );
	}

	/**
	 * Test get allowed actions.
	 *
	 * @return void
	 */
	public function test_get_allowed_actions(): void {
		// Create admin user.
		$admin_id = wp_create_user( 'actiontest', 'testpass', 'action@example.com' );
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_role( 'administrator' );
		
		// Set as current user.
		wp_set_current_user( $admin_id );
		
		// Add capabilities.
		$this->access_control->add_capabilities();
		
		$actions = $this->access_control->get_allowed_actions();
		
		$this->assertIsArray( $actions );
		$this->assertContains( 'manage', $actions );
		$this->assertContains( 'edit', $actions );
		$this->assertContains( 'delete', $actions );
		$this->assertContains( 'publish', $actions );
		$this->assertContains( 'analytics', $actions );
		$this->assertContains( 'export', $actions );
		
		// Clean up.
		wp_delete_user( $admin_id );
	}

	/**
	 * Test verify nonce with capability.
	 *
	 * @return void
	 */
	public function test_verify_nonce_with_cap(): void {
		// Create admin user.
		$admin_id = wp_create_user( 'noncetest', 'testpass', 'nonce@example.com' );
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_role( 'administrator' );
		
		// Set as current user.
		wp_set_current_user( $admin_id );
		
		// Add capabilities.
		$this->access_control->add_capabilities();
		
		// Create valid nonce.
		$nonce = wp_create_nonce( 'test_action' );
		
		// Should pass with capability.
		$result = $this->access_control->verify_nonce_with_cap(
			$nonce,
			'test_action',
			'manage_money_quiz'
		);
		$this->assertTrue( $result );
		
		// Should fail without capability.
		$result = $this->access_control->verify_nonce_with_cap(
			$nonce,
			'test_action',
			'non_existent_cap'
		);
		$this->assertFalse( $result );
		
		// Clean up.
		wp_delete_user( $admin_id );
	}
}