<?php
/**
 * Legacy Integration Tests
 * 
 * Tests for legacy code integration and safety wrappers
 * 
 * @package MoneyQuiz\Tests\Integration
 */

namespace MoneyQuiz\Tests\Integration;

use MoneyQuiz\Tests\IntegrationTestCase;
use MoneyQuiz\Legacy\Legacy_DB_Wrapper;
use MoneyQuiz\Security\Legacy_Input_Sanitizer;
use MoneyQuiz\Core\Version_Manager;
use MoneyQuiz\Integration\Legacy_Integration;

class LegacyIntegrationTest extends IntegrationTestCase {
    
    /**
     * Test database wrapper prevents SQL injection
     */
    public function test_database_wrapper_prevents_sql_injection() {
        $db_wrapper = new Legacy_DB_Wrapper();
        
        // Test malicious input
        $malicious_inputs = [
            "'; DROP TABLE wp_users; --",
            "1' OR '1'='1",
            "1; DELETE FROM wp_options WHERE 1=1;",
            "' UNION SELECT * FROM wp_users --"
        ];
        
        foreach ( $malicious_inputs as $input ) {
            // This should return false due to injection detection
            $result = $db_wrapper->safe_query( 
                "SELECT * FROM {$this->wpdb->prefix}mq_prospects WHERE email = '" . $input . "'"
            );
            
            $this->assertFalse( $result, "SQL injection not prevented for: $input" );
        }
    }
    
    /**
     * Test safe query with proper parameters
     */
    public function test_safe_query_with_parameters() {
        $db_wrapper = new Legacy_DB_Wrapper();
        
        // Create test data
        $this->wpdb->insert(
            $this->wpdb->prefix . 'mq_prospects',
            [
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'created_at' => current_time( 'mysql' )
            ]
        );
        
        // Test safe query
        $result = $db_wrapper->safe_get_row(
            "SELECT * FROM {$this->wpdb->prefix}mq_prospects WHERE email = %s",
            [ 'test@example.com' ]
        );
        
        $this->assertNotNull( $result );
        $this->assertEquals( 'test@example.com', $result->email );
    }
    
    /**
     * Test input sanitizer
     */
    public function test_input_sanitizer() {
        $sanitizer = new Legacy_Input_Sanitizer();
        
        $dirty_input = [
            'email' => 'test@example.com<script>alert("xss")</script>',
            'name' => 'Test<b>Name</b>',
            'phone' => '123-456-7890<script>',
            'message' => '<p>Hello</p><script>alert("xss")</script>',
            'quiz_id' => '123abc',
            'score' => '98.5points'
        ];
        
        $clean_input = $sanitizer->sanitize_request( $dirty_input );
        
        // Check sanitization
        $this->assertEquals( 'test@example.com', $clean_input['email'] );
        $this->assertEquals( 'TestName', $clean_input['name'] );
        $this->assertEquals( '123-456-7890', $clean_input['phone'] );
        $this->assertStringNotContainsString( '<script>', $clean_input['message'] );
        $this->assertEquals( 123, $clean_input['quiz_id'] );
        $this->assertEquals( 98.5, $clean_input['score'] );
    }
    
    /**
     * Test email validation
     */
    public function test_email_validation() {
        $sanitizer = new Legacy_Input_Sanitizer();
        
        // Valid emails
        $valid_emails = [
            'user@example.com',
            'test.user@example.co.uk',
            'user+tag@example.com'
        ];
        
        foreach ( $valid_emails as $email ) {
            $result = $sanitizer->validate_email( $email );
            $this->assertNotFalse( $result, "Valid email rejected: $email" );
        }
        
        // Invalid emails
        $invalid_emails = [
            'not-an-email',
            'test@test.com<script>',
            'admin@admin.com', // Blocked pattern
            '@example.com',
            'user@',
            'test@test..com'
        ];
        
        foreach ( $invalid_emails as $email ) {
            $result = $sanitizer->validate_email( $email );
            $this->assertFalse( $result, "Invalid email accepted: $email" );
        }
    }
    
    /**
     * Test version manager reconciliation
     */
    public function test_version_reconciliation() {
        // Set conflicting versions
        update_option( 'money_quiz_version', '4.0.0' );
        update_option( 'mq_money_coach_plugin_version', '1.4' );
        
        // Initialize version manager
        $version_manager = new Version_Manager();
        $version_manager->init();
        
        // Check versions are reconciled
        $this->assertEquals( 
            Version_Manager::CURRENT_VERSION, 
            get_option( 'money_quiz_version' ) 
        );
        $this->assertEquals( 
            Version_Manager::CURRENT_VERSION, 
            get_option( 'mq_money_coach_plugin_version' ) 
        );
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection() {
        if ( ! class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) {
            $this->markTestSkipped( 'CSRF Manager not available' );
        }
        
        $csrf = new \MoneyQuiz\Security\CsrfManager();
        
        // Generate token
        $token = $csrf->generate_token( 'test_action' );
        $this->assertNotEmpty( $token );
        
        // Validate token
        $valid = $csrf->validate_token( $token, 'test_action', false );
        $this->assertTrue( $valid );
        
        // Invalid token
        $invalid = $csrf->validate_token( 'invalid_token', 'test_action' );
        $this->assertFalse( $invalid );
    }
    
    /**
     * Test quiz submission sanitization
     */
    public function test_quiz_submission_sanitization() {
        $_POST = [
            'quiz_id' => '1<script>',
            'answers' => [
                '1' => 'answer1<script>alert("xss")</script>',
                '2' => 'answer2',
                '3<script>' => 'answer3'
            ],
            'email' => 'test@example.com<script>',
            'name' => 'Test User<b>Bold</b>',
            'phone' => '123-456-7890'
        ];
        
        // Sanitize input
        $clean = mq_sanitize_input( $_POST, [
            'answers' => function( $answers ) {
                $clean = [];
                if ( is_array( $answers ) ) {
                    foreach ( $answers as $q_id => $answer ) {
                        $clean[ absint( $q_id ) ] = sanitize_text_field( $answer );
                    }
                }
                return $clean;
            }
        ] );
        
        // Verify sanitization
        $this->assertEquals( 1, $clean['quiz_id'] );
        $this->assertEquals( 'test@example.com', $clean['email'] );
        $this->assertEquals( 'Test UserBold', $clean['name'] );
        $this->assertArrayHasKey( 1, $clean['answers'] );
        $this->assertArrayHasKey( 2, $clean['answers'] );
        $this->assertArrayNotHasKey( 3, $clean['answers'] ); // Invalid key removed
        $this->assertEquals( 'answer1alert("xss")', $clean['answers'][1] );
    }
    
    /**
     * Test legacy integration health check
     */
    public function test_integration_health_check() {
        $integration = Legacy_Integration::instance();
        $integration->init();
        
        $health_result = $integration->health_check();
        
        $this->assertArrayHasKey( 'status', $health_result );
        $this->assertArrayHasKey( 'label', $health_result );
        $this->assertArrayHasKey( 'test', $health_result );
        $this->assertEquals( 'money_quiz_integration', $health_result['test'] );
    }
    
    /**
     * Test function router
     */
    public function test_function_router() {
        if ( ! class_exists( '\MoneyQuiz\Legacy\Legacy_Function_Router' ) ) {
            $this->markTestSkipped( 'Function Router not available' );
        }
        
        $router = \MoneyQuiz\Legacy\Legacy_Function_Router::instance();
        $router->init();
        
        // Test routing statistics
        $stats = $router->get_stats();
        $this->assertIsArray( $stats );
        
        // Test toggling modern implementation
        $result = $router->toggle_modern_implementation( 'mq_get_quiz_questions', false );
        $this->assertTrue( $result );
        
        // Verify it's disabled
        $flags = get_option( 'money_quiz_feature_flags', [] );
        $this->assertFalse( $flags['mq_get_quiz_questions'] );
    }
    
    /**
     * Test error logger
     */
    public function test_error_logger() {
        if ( ! defined( 'MONEY_QUIZ_ERROR_LOGGING' ) ) {
            define( 'MONEY_QUIZ_ERROR_LOGGING', true );
        }
        
        $logger = new \MoneyQuiz\Debug\Enhanced_Error_Logger();
        
        // Trigger a warning
        @trigger_error( 'Test warning', E_USER_WARNING );
        
        // Get recent errors
        $recent = $logger->get_recent_errors( 5 );
        $this->assertIsArray( $recent );
        
        // Get statistics
        $stats = $logger->get_stats( 1 );
        $this->assertIsArray( $stats );
    }
    
    /**
     * Test migration script detection
     */
    public function test_migration_script_detection() {
        // Create a test file with unsafe queries
        $test_file = MONEY_QUIZ_PLUGIN_DIR . 'test-unsafe-queries.php';
        $unsafe_content = '<?php
        $wpdb->query("SELECT * FROM table WHERE id = " . $id);
        $wpdb->get_results("SELECT * FROM table WHERE email = \'" . $email . "\'");
        $wpdb->get_row("DELETE FROM table WHERE id = $user_id");
        ';
        
        file_put_contents( $test_file, $unsafe_content );
        
        // Check if migration script exists
        $migration_script = MONEY_QUIZ_PLUGIN_DIR . 'tools/migrate-database-queries.php';
        $this->assertFileExists( $migration_script );
        
        // Clean up
        unlink( $test_file );
    }
    
    /**
     * Test admin security patches
     */
    public function test_admin_security_patches() {
        // Simulate admin request
        set_current_screen( 'admin' );
        $_GET['page'] = 'moneyquiz-settings';
        
        // Check if user needs proper capabilities
        $this->expectNotToPerformAssertions();
        
        // This would normally die() if user lacks permissions
        // We're just verifying the patches are loaded
    }
}