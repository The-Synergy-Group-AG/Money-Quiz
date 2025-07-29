<?php
/**
 * Security validation tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Security;

use MoneyQuiz\Tests\IntegrationTestCase;

/**
 * Test security measures across the plugin
 */
class SecurityValidationTest extends IntegrationTestCase {
    
    /**
     * Test CSRF protection on forms
     */
    public function test_csrf_protection_on_forms() {
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        
        // Render quiz form
        $output = $shortcode_manager->render_quiz_shortcode( [] );
        
        // Check for CSRF field
        $this->assertStringContainsString( 'name="money_quiz_csrf_token"', $output );
        $this->assertStringContainsString( 'type="hidden"', $output );
        
        // Extract token value
        preg_match( '/name="money_quiz_csrf_token" value="([^"]+)"/', $output, $matches );
        $this->assertCount( 2, $matches );
        
        $token = $matches[1];
        $this->assertNotEmpty( $token );
        
        // Verify token is valid
        $csrf_manager = $this->container->get( 'security.csrf' );
        $this->assertTrue( $csrf_manager->verify_token( $token, 'money_quiz_submit' ) );
    }
    
    /**
     * Test AJAX CSRF validation
     */
    public function test_ajax_csrf_validation() {
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        
        // Test without nonce
        $this->simulate_ajax_request( 'money_quiz_submit', [
            'quiz_id' => 1,
            'answers' => json_encode( [ '1' => 'a' ] ),
        ] );
        
        ob_start();
        $ajax_handler->handle_quiz_submission();
        $output = ob_get_clean();
        
        $response = json_decode( $output, true );
        
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Security check failed', $response['data'] );
    }
    
    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        // Create admin user
        $admin_id = $this->create_test_user( 'administrator' );
        wp_set_current_user( $admin_id );
        
        $controller = new \MoneyQuiz\Admin\Controllers\QuizController();
        
        // Test XSS in quiz creation
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_wpnonce'] = wp_create_nonce( 'create_quiz' );
        $_POST['title'] = '<script>alert("XSS")</script>Test Quiz';
        $_POST['description'] = '<p onclick="alert(\'XSS\')">Description</p>';
        $_POST['questions'] = [
            [
                'text' => '<img src=x onerror="alert(\'XSS\')">Question',
                'options' => [
                    [ 'value' => 'a', 'label' => '<script>alert("XSS")</script>Option' ],
                ],
            ],
        ];
        
        // Capture redirect
        add_filter( 'wp_redirect', function( $location ) {
            return false;
        } );
        
        $controller->create();
        
        // Check database for sanitized values
        global $wpdb;
        $quiz = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes ORDER BY id DESC LIMIT 1"
        );
        
        // Title should be sanitized
        $this->assertStringNotContainsString( '<script>', $quiz->title );
        $this->assertStringContainsString( 'Test Quiz', $quiz->title );
        
        // Description should allow safe HTML
        $this->assertStringContainsString( '<p>', $quiz->description );
        $this->assertStringNotContainsString( 'onclick=', $quiz->description );
    }
    
    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention() {
        $quiz_repository = $this->container->get( 'repository.quiz' );
        
        // Try SQL injection in find method
        $malicious_id = "1; DROP TABLE wp_money_quiz_quizzes; --";
        $result = $quiz_repository->find( $malicious_id );
        
        // Should return null, not execute malicious SQL
        $this->assertNull( $result );
        
        // Table should still exist
        $this->assertTableExists( 'money_quiz_quizzes' );
    }
    
    /**
     * Test capability checks
     */
    public function test_capability_checks() {
        // Test as non-admin user
        $subscriber_id = $this->create_test_user( 'subscriber' );
        wp_set_current_user( $subscriber_id );
        
        // Try to access admin controller
        $controller = new \MoneyQuiz\Admin\Controllers\QuizController();
        
        // Should die with permission error
        $this->expectException( \WPDieException::class );
        
        $_GET['_wpnonce'] = wp_create_nonce( 'delete_quiz_1' );
        $controller->delete( 1 );
    }
    
    /**
     * Test XSS prevention in output
     */
    public function test_xss_prevention_in_output() {
        // Create quiz with potentially malicious content
        $quiz_id = $this->create_test_quiz( [
            'title' => 'Test <script>alert("XSS")</script> Quiz',
        ] );
        
        // Create archetype with script
        $archetype_id = $this->create_test_archetype( [
            'name' => 'Leader<script>alert("XSS")</script>',
            'description' => '<p>Safe HTML</p><script>alert("XSS")</script>',
        ] );
        
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        $output = $shortcode_manager->render_quiz_shortcode( [ 'id' => $quiz_id ] );
        
        // Scripts should be escaped
        $this->assertStringNotContainsString( '<script>alert("XSS")</script>', $output );
        $this->assertStringContainsString( 'Test', $output );
        $this->assertStringContainsString( 'Quiz', $output );
    }
    
    /**
     * Test file upload security
     */
    public function test_file_upload_security() {
        $admin_id = $this->create_test_user( 'administrator' );
        wp_set_current_user( $admin_id );
        
        $controller = new \MoneyQuiz\Admin\Controllers\SettingsController();
        
        // Simulate malicious file upload attempt
        $_FILES['import_file'] = [
            'name' => 'malicious.php',
            'type' => 'application/x-php',
            'tmp_name' => '/tmp/test_file',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];
        
        $_POST['import_settings'] = '1';
        $_POST['_wpnonce'] = wp_create_nonce( 'import_settings' );
        
        // Create a test file
        file_put_contents( '/tmp/test_file', '<?php echo "malicious"; ?>' );
        
        ob_start();
        $controller->index();
        ob_end_clean();
        
        // Should have error about invalid format
        $errors = get_settings_errors( 'money_quiz_settings' );
        $this->assertNotEmpty( $errors );
        
        // Clean up
        @unlink( '/tmp/test_file' );
    }
    
    /**
     * Test nonce verification
     */
    public function test_nonce_verification() {
        $admin_id = $this->create_test_user( 'administrator' );
        wp_set_current_user( $admin_id );
        
        $controller = new \MoneyQuiz\Admin\Controllers\QuizController();
        
        // Test with invalid nonce
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_wpnonce'] = 'invalid_nonce';
        $_POST['title'] = 'Test Quiz';
        
        // This should die
        $this->expectException( \WPDieException::class );
        $controller->create();
    }
    
    /**
     * Test data validation
     */
    public function test_data_validation() {
        $quiz_service = $this->container->get( 'service.quiz' );
        
        // Create quiz with questions
        $quiz_id = $this->create_test_quiz();
        $this->create_test_quiz_questions( $quiz_id );
        
        // Test with invalid answers
        $invalid_answers = [
            'invalid_question_id' => 'a',
            '999' => 'invalid_option',
        ];
        
        $result = $quiz_service->calculate_result( $quiz_id, $invalid_answers );
        
        // Should handle gracefully
        $this->assertIsArray( $result );
    }
    
    /**
     * Test email header injection prevention
     */
    public function test_email_header_injection_prevention() {
        $email_service = $this->container->get( 'service.email' );
        
        // Try to inject headers
        $malicious_email = "test@example.com\r\nBcc: spam@example.com";
        $malicious_subject = "Test\r\nX-Spam: true";
        
        // Mock wp_mail to capture arguments
        $wp_mail_args = [];
        add_filter( 'pre_wp_mail', function( $null, $args ) use ( &$wp_mail_args ) {
            $wp_mail_args = $args;
            return true;
        }, 10, 2 );
        
        $email_service->send( $malicious_email, $malicious_subject, 'Test message' );
        
        // Headers should be sanitized
        $this->assertStringNotContainsString( "\r\n", $wp_mail_args['to'] );
        $this->assertStringNotContainsString( "\r\n", $wp_mail_args['subject'] );
    }
    
    /**
     * Test settings validation
     */
    public function test_settings_validation() {
        $settings_manager = $this->container->get( 'admin.settings' );
        
        // Test email validation
        $settings_manager->update_option( 'general', 'admin_email', 'invalid-email' );
        $email = $settings_manager->get_option( 'general', 'admin_email' );
        
        // Should be sanitized
        $this->assertNotEquals( 'invalid-email', $email );
        
        // Test number validation
        $settings_manager->update_option( 'advanced', 'cache_duration', -100 );
        $duration = $settings_manager->get_option( 'advanced', 'cache_duration' );
        
        // Should be positive
        $this->assertGreaterThanOrEqual( 0, $duration );
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        // This would test rate limiting if implemented
        // For now, just ensure multiple rapid submissions are handled
        
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        $csrf_manager = $this->container->get( 'security.csrf' );
        $nonce = $csrf_manager->get_ajax_nonce();
        
        // Simulate rapid submissions
        for ( $i = 0; $i < 5; $i++ ) {
            $this->simulate_ajax_request( 'money_quiz_submit', [
                'quiz_id' => 1,
                'answers' => json_encode( [ '1' => 'a' ] ),
                'prospect' => json_encode( [ 'email' => "test{$i}@example.com" ] ),
                'nonce' => $nonce,
            ] );
            
            ob_start();
            $ajax_handler->handle_quiz_submission();
            ob_end_clean();
        }
        
        // Should handle all requests without crashing
        $this->assertTrue( true );
    }
    
    /**
     * Create test quiz questions
     */
    private function create_test_quiz_questions( int $quiz_id ): void {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_questions',
            [
                'quiz_id' => $quiz_id,
                'question' => 'Test Question',
                'options' => json_encode( [
                    [ 'value' => 'a', 'label' => 'Option A' ],
                    [ 'value' => 'b', 'label' => 'Option B' ],
                ] ),
                'archetype_weights' => json_encode( [
                    '1' => [ 'a' => 10, 'b' => 5 ],
                ] ),
                'is_required' => 1,
            ]
        );
    }
}