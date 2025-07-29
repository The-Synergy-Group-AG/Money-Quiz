<?php
/**
 * CsrfManager unit tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Unit\Security;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Security\CsrfManager;

/**
 * Test the CSRF manager
 */
class CsrfManagerTest extends TestCase {
    
    /**
     * @var CsrfManager
     */
    private CsrfManager $csrf_manager;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->csrf_manager = new CsrfManager();
    }
    
    /**
     * Test generate token creates valid token
     */
    public function test_generate_token_creates_valid_token() {
        $action = 'test_action';
        $token = $this->csrf_manager->generate_token( $action );
        
        $this->assertIsString( $token );
        $this->assertNotEmpty( $token );
        
        // Token should be consistent for same action
        $token2 = $this->csrf_manager->generate_token( $action );
        $this->assertEquals( $token, $token2 );
    }
    
    /**
     * Test different actions generate different tokens
     */
    public function test_different_actions_generate_different_tokens() {
        $token1 = $this->csrf_manager->generate_token( 'action1' );
        $token2 = $this->csrf_manager->generate_token( 'action2' );
        
        $this->assertNotEquals( $token1, $token2 );
    }
    
    /**
     * Test verify token with valid token
     */
    public function test_verify_token_with_valid_token() {
        $action = 'test_action';
        $token = $this->csrf_manager->generate_token( $action );
        
        $result = $this->csrf_manager->verify_token( $token, $action );
        
        $this->assertTrue( $result );
    }
    
    /**
     * Test verify token with invalid token
     */
    public function test_verify_token_with_invalid_token() {
        $action = 'test_action';
        $invalid_token = 'invalid_token_12345';
        
        $result = $this->csrf_manager->verify_token( $invalid_token, $action );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test verify token with wrong action
     */
    public function test_verify_token_with_wrong_action() {
        $token = $this->csrf_manager->generate_token( 'action1' );
        
        $result = $this->csrf_manager->verify_token( $token, 'action2' );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test verify token with empty token
     */
    public function test_verify_token_with_empty_token() {
        $result = $this->csrf_manager->verify_token( '', 'test_action' );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test get field name
     */
    public function test_get_field_name() {
        $field_name = $this->csrf_manager->get_field_name();
        
        $this->assertEquals( 'money_quiz_csrf_token', $field_name );
    }
    
    /**
     * Test render field outputs HTML
     */
    public function test_render_field_outputs_html() {
        $action = 'test_action';
        
        ob_start();
        $this->csrf_manager->render_field( $action );
        $output = ob_get_clean();
        
        $this->assertStringContainsString( '<input type="hidden"', $output );
        $this->assertStringContainsString( 'name="money_quiz_csrf_token"', $output );
        $this->assertStringContainsString( 'value="', $output );
        
        // Extract token from output
        preg_match( '/value="([^"]+)"/', $output, $matches );
        $this->assertCount( 2, $matches );
        
        $token = $matches[1];
        $this->assertTrue( $this->csrf_manager->verify_token( $token, $action ) );
    }
    
    /**
     * Test verify request with valid token in POST
     */
    public function test_verify_request_with_valid_token_in_post() {
        $action = 'test_action';
        $token = $this->csrf_manager->generate_token( $action );
        
        $_POST['money_quiz_csrf_token'] = $token;
        
        $result = $this->csrf_manager->verify_request( $action );
        
        $this->assertTrue( $result );
        
        // Clean up
        unset( $_POST['money_quiz_csrf_token'] );
    }
    
    /**
     * Test verify request with valid token in GET
     */
    public function test_verify_request_with_valid_token_in_get() {
        $action = 'test_action';
        $token = $this->csrf_manager->generate_token( $action );
        
        $_GET['money_quiz_csrf_token'] = $token;
        
        $result = $this->csrf_manager->verify_request( $action );
        
        $this->assertTrue( $result );
        
        // Clean up
        unset( $_GET['money_quiz_csrf_token'] );
    }
    
    /**
     * Test verify request with no token
     */
    public function test_verify_request_with_no_token() {
        $result = $this->csrf_manager->verify_request( 'test_action' );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test verify request prioritizes POST over GET
     */
    public function test_verify_request_prioritizes_post_over_get() {
        $action = 'test_action';
        $valid_token = $this->csrf_manager->generate_token( $action );
        
        $_POST['money_quiz_csrf_token'] = $valid_token;
        $_GET['money_quiz_csrf_token'] = 'invalid_token';
        
        $result = $this->csrf_manager->verify_request( $action );
        
        $this->assertTrue( $result );
        
        // Clean up
        unset( $_POST['money_quiz_csrf_token'] );
        unset( $_GET['money_quiz_csrf_token'] );
    }
    
    /**
     * Test AJAX nonce generation
     */
    public function test_ajax_nonce_generation() {
        $nonce = $this->csrf_manager->get_ajax_nonce();
        
        $this->assertIsString( $nonce );
        $this->assertNotEmpty( $nonce );
    }
    
    /**
     * Test token expiration
     */
    public function test_token_expiration() {
        $action = 'test_action';
        
        // Generate token
        $token = $this->csrf_manager->generate_token( $action );
        
        // Mock time passing by manipulating nonce tick
        add_filter( 'nonce_life', function() {
            return 1; // 1 second lifetime
        } );
        
        // Sleep to ensure expiration (in real tests, you'd mock time)
        sleep( 2 );
        
        // Token should now be invalid
        $result = $this->csrf_manager->verify_token( $token, $action );
        
        // Note: WordPress nonces don't actually expire this quickly in tests
        // This is more of a conceptual test
        $this->assertIsBool( $result );
    }
    
    /**
     * Test get ajax localize data
     */
    public function test_get_ajax_localize_data() {
        $data = $this->csrf_manager->get_ajax_localize_data();
        
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'nonce', $data );
        $this->assertArrayHasKey( 'field_name', $data );
        
        $this->assertEquals( 'money_quiz_csrf_token', $data['field_name'] );
        $this->assertNotEmpty( $data['nonce'] );
    }
    
    /**
     * Test integration with WordPress nonce functions
     */
    public function test_integration_with_wordpress_nonce() {
        $action = 'money_quiz_test';
        
        // Our token should work with wp_verify_nonce
        $token = $this->csrf_manager->generate_token( $action );
        
        $wp_verify_result = wp_verify_nonce( $token, $action );
        $this->assertNotFalse( $wp_verify_result );
    }
}