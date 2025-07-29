<?php
/**
 * EmailService unit tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Unit\Services;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Services\EmailService;
use MoneyQuiz\Admin\SettingsManager;

/**
 * Test the email service
 */
class EmailServiceTest extends TestCase {
    
    /**
     * @var EmailService
     */
    private EmailService $service;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $settings_manager_mock;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create settings manager mock
        $this->settings_manager_mock = $this->createMock( SettingsManager::class );
        
        // Create service and inject mock
        $this->service = new EmailService();
        $this->set_private_property( $this->service, 'settings_manager', $this->settings_manager_mock );
    }
    
    /**
     * Test send quiz result when notifications disabled
     */
    public function test_send_quiz_result_notifications_disabled() {
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'are_notifications_enabled' )
            ->willReturn( false );
        
        $result = $this->service->send_quiz_result( 'test@example.com', [] );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test send quiz result when prospect emails disabled
     */
    public function test_send_quiz_result_prospect_emails_disabled() {
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'are_notifications_enabled' )
            ->willReturn( true );
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'get_email_config' )
            ->willReturn( [
                'send_to_prospect' => false,
                'prospect_subject' => 'Your Results',
                'prospect_template' => '',
            ] );
        
        $result = $this->service->send_quiz_result( 'test@example.com', [] );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test send quiz result with default template
     */
    public function test_send_quiz_result_with_default_template() {
        $to = 'test@example.com';
        $quiz_result = [
            'archetype' => (object) [ 'name' => 'Test Archetype', 'description' => 'Description' ],
            'score' => 85,
        ];
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'are_notifications_enabled' )
            ->willReturn( true );
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'get_email_config' )
            ->willReturn( [
                'send_to_prospect' => true,
                'prospect_subject' => 'Your Quiz Results',
                'prospect_template' => '',
                'from_email' => 'noreply@example.com',
                'from_name' => 'Quiz System',
            ] );
        
        // Mock wp_mail function
        $wp_mail_called = false;
        $wp_mail_args = [];
        
        add_filter( 'pre_wp_mail', function( $null, $args ) use ( &$wp_mail_called, &$wp_mail_args ) {
            $wp_mail_called = true;
            $wp_mail_args = $args;
            return true; // Prevent actual email sending
        }, 10, 2 );
        
        $result = $this->service->send_quiz_result( $to, $quiz_result );
        
        $this->assertTrue( $result );
        $this->assertTrue( $wp_mail_called );
        $this->assertEquals( $to, $wp_mail_args['to'] );
        $this->assertEquals( 'Your Quiz Results', $wp_mail_args['subject'] );
        $this->assertStringContainsString( 'Test Archetype', $wp_mail_args['message'] );
        $this->assertStringContainsString( '85%', $wp_mail_args['message'] );
    }
    
    /**
     * Test send quiz result with custom template
     */
    public function test_send_quiz_result_with_custom_template() {
        $to = 'test@example.com';
        $quiz_result = [
            'archetype' => (object) [ 'name' => 'Test Archetype' ],
            'score' => 90,
            'email' => $to,
        ];
        
        $custom_template = 'Hello {name}, your archetype is {archetype} with score {score}%';
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'are_notifications_enabled' )
            ->willReturn( true );
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'get_email_config' )
            ->willReturn( [
                'send_to_prospect' => true,
                'prospect_subject' => 'Results',
                'prospect_template' => $custom_template,
                'from_email' => 'noreply@example.com',
                'from_name' => 'Quiz',
            ] );
        
        // Mock wp_mail
        add_filter( 'pre_wp_mail', function( $null, $args ) use ( &$wp_mail_args ) {
            $wp_mail_args = $args;
            return true;
        }, 10, 2 );
        
        $result = $this->service->send_quiz_result( $to, $quiz_result );
        
        $this->assertTrue( $result );
        $this->assertStringContainsString( 'Test Archetype', $wp_mail_args['message'] );
        $this->assertStringContainsString( '90%', $wp_mail_args['message'] );
    }
    
    /**
     * Test send admin notification when disabled
     */
    public function test_send_admin_notification_when_disabled() {
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'are_notifications_enabled' )
            ->willReturn( false );
        
        $result = $this->service->send_admin_notification( [], [] );
        
        $this->assertFalse( $result );
    }
    
    /**
     * Test send admin notification
     */
    public function test_send_admin_notification() {
        $quiz_result = [
            'archetype' => (object) [ 'name' => 'Test Archetype' ],
            'score' => 75,
        ];
        
        $prospect = [
            'email' => 'user@example.com',
            'name' => 'Test User',
        ];
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'are_notifications_enabled' )
            ->willReturn( true );
        
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'get_email_config' )
            ->willReturn( [
                'send_to_admin' => true,
                'admin_email' => 'admin@example.com',
                'admin_subject' => 'New Quiz Submission',
                'admin_template' => '',
                'from_email' => 'noreply@example.com',
                'from_name' => 'Quiz',
            ] );
        
        // Mock wp_mail
        $wp_mail_args = [];
        add_filter( 'pre_wp_mail', function( $null, $args ) use ( &$wp_mail_args ) {
            $wp_mail_args = $args;
            return true;
        }, 10, 2 );
        
        $result = $this->service->send_admin_notification( $quiz_result, $prospect );
        
        $this->assertTrue( $result );
        $this->assertEquals( 'admin@example.com', $wp_mail_args['to'] );
        $this->assertEquals( 'New Quiz Submission', $wp_mail_args['subject'] );
        $this->assertStringContainsString( 'user@example.com', $wp_mail_args['message'] );
        $this->assertStringContainsString( 'Test User', $wp_mail_args['message'] );
        $this->assertStringContainsString( 'Test Archetype', $wp_mail_args['message'] );
        $this->assertStringContainsString( '75%', $wp_mail_args['message'] );
    }
    
    /**
     * Test build headers
     */
    public function test_build_headers() {
        $this->settings_manager_mock
            ->expects( $this->once() )
            ->method( 'get_email_config' )
            ->willReturn( [
                'from_email' => 'sender@example.com',
                'from_name' => 'Sender Name',
            ] );
        
        $options = [
            'reply_to' => 'reply@example.com',
            'cc' => 'cc@example.com',
            'bcc' => 'bcc@example.com',
        ];
        
        $headers = $this->call_private_method( $this->service, 'build_headers', [ $options ] );
        
        $this->assertContains( 'From: Sender Name <sender@example.com>', $headers );
        $this->assertContains( 'Reply-To: reply@example.com', $headers );
        $this->assertContains( 'Cc: cc@example.com', $headers );
        $this->assertContains( 'Bcc: bcc@example.com', $headers );
        $this->assertContains( 'Content-Type: text/html; charset=UTF-8', $headers );
    }
    
    /**
     * Test parse email template
     */
    public function test_parse_email_template() {
        $template = 'Hello {name}, your email is {email} and archetype is {archetype} with score {score}%. Visit {site_name} at {site_url}';
        
        $result = [
            'archetype' => (object) [ 'name' => 'Leader' ],
            'score' => 88,
            'id' => 123,
            'email' => 'user@example.com',
        ];
        
        $prospect = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
        
        // Mock WordPress functions
        add_filter( 'bloginfo', function( $output, $show ) {
            if ( $show === 'name' ) return 'Test Site';
            return $output;
        }, 10, 2 );
        
        add_filter( 'home_url', function() {
            return 'https://example.com';
        }, 10 );
        
        $parsed = $this->call_private_method( 
            $this->service, 
            'parse_email_template', 
            [ $template, $result, $prospect ] 
        );
        
        $this->assertStringContainsString( 'Hello John Doe', $parsed );
        $this->assertStringContainsString( 'john@example.com', $parsed );
        $this->assertStringContainsString( 'Leader', $parsed );
        $this->assertStringContainsString( '88%', $parsed );
        $this->assertStringContainsString( 'Test Site', $parsed );
        $this->assertStringContainsString( 'https://example.com', $parsed );
    }
    
    /**
     * Test email filters are applied
     */
    public function test_email_filters_applied() {
        $to = 'test@example.com';
        $subject = 'Original Subject';
        $message = 'Original Message';
        
        // Add filters
        add_filter( 'money_quiz_email_to', function( $email ) {
            return 'filtered@example.com';
        } );
        
        add_filter( 'money_quiz_email_subject', function( $subj ) {
            return 'Filtered Subject';
        } );
        
        add_filter( 'money_quiz_email_message', function( $msg ) {
            return 'Filtered Message';
        } );
        
        // Mock settings
        $this->settings_manager_mock
            ->method( 'get_email_config' )
            ->willReturn( [
                'from_email' => 'sender@example.com',
                'from_name' => 'Sender',
            ] );
        
        // Mock wp_mail to capture args
        $wp_mail_args = [];
        add_filter( 'pre_wp_mail', function( $null, $args ) use ( &$wp_mail_args ) {
            $wp_mail_args = $args;
            return true;
        }, 10, 2 );
        
        $this->service->send( $to, $subject, $message );
        
        $this->assertEquals( 'filtered@example.com', $wp_mail_args['to'] );
        $this->assertEquals( 'Filtered Subject', $wp_mail_args['subject'] );
        $this->assertEquals( 'Filtered Message', $wp_mail_args['message'] );
    }
}