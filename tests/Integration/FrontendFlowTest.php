<?php
/**
 * Frontend flow integration tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Integration;

use MoneyQuiz\Tests\IntegrationTestCase;
use MoneyQuiz\Frontend\ShortcodeManager;
use MoneyQuiz\Frontend\AjaxHandler;

/**
 * Test the complete frontend quiz flow
 */
class FrontendFlowTest extends IntegrationTestCase {
    
    /**
     * @var int
     */
    private int $quiz_id;
    
    /**
     * @var int
     */
    private int $archetype_id;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create test data
        $this->quiz_id = $this->create_test_quiz( [
            'title' => 'Frontend Test Quiz',
            'settings' => json_encode( [ 'show_progress' => true ] ),
        ] );
        
        $this->archetype_id = $this->create_test_archetype( [
            'name' => 'Leader',
            'description' => 'Natural born leader',
        ] );
        
        // Add questions
        $this->create_quiz_questions();
    }
    
    /**
     * Test shortcode renders quiz
     */
    public function test_shortcode_renders_quiz() {
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        
        // Test default shortcode
        $output = $shortcode_manager->render_quiz_shortcode( [] );
        
        $this->assertStringContainsString( 'money-quiz-container', $output );
        $this->assertStringContainsString( 'Frontend Test Quiz', $output );
        $this->assertStringContainsString( 'data-quiz-id="' . $this->quiz_id . '"', $output );
    }
    
    /**
     * Test shortcode with specific quiz ID
     */
    public function test_shortcode_with_quiz_id() {
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        
        $output = $shortcode_manager->render_quiz_shortcode( [
            'id' => $this->quiz_id,
        ] );
        
        $this->assertStringContainsString( 'Frontend Test Quiz', $output );
    }
    
    /**
     * Test shortcode with invalid quiz ID
     */
    public function test_shortcode_with_invalid_quiz_id() {
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        
        $output = $shortcode_manager->render_quiz_shortcode( [
            'id' => 9999,
        ] );
        
        $this->assertStringContainsString( 'Quiz not found', $output );
    }
    
    /**
     * Test AJAX quiz submission
     */
    public function test_ajax_quiz_submission() {
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        
        // Generate CSRF token
        $csrf_manager = $this->container->get( 'security.csrf' );
        $nonce = $csrf_manager->get_ajax_nonce();
        
        // Simulate AJAX request
        $this->simulate_ajax_request( 'money_quiz_submit', [
            'quiz_id' => $this->quiz_id,
            'answers' => json_encode( [
                '1' => 'a',
                '2' => 'b',
            ] ),
            'prospect' => json_encode( [
                'email' => 'test@example.com',
                'name' => 'Test User',
            ] ),
            'nonce' => $nonce,
        ] );
        
        // Capture output
        ob_start();
        $ajax_handler->handle_quiz_submission();
        $output = ob_get_clean();
        
        $response = json_decode( $output, true );
        
        $this->assertIsArray( $response );
        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'data', $response );
        $this->assertArrayHasKey( 'archetype', $response['data'] );
        $this->assertArrayHasKey( 'score', $response['data'] );
        
        // Verify database records
        $this->assertRecordExists( 'money_quiz_prospects', [
            'email' => 'test@example.com',
        ] );
        
        $this->assertRecordExists( 'money_quiz_results', [
            'quiz_id' => $this->quiz_id,
        ] );
    }
    
    /**
     * Test AJAX submission with invalid CSRF
     */
    public function test_ajax_submission_with_invalid_csrf() {
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        
        $this->simulate_ajax_request( 'money_quiz_submit', [
            'quiz_id' => $this->quiz_id,
            'answers' => json_encode( [ '1' => 'a' ] ),
            'nonce' => 'invalid_nonce',
        ] );
        
        ob_start();
        $ajax_handler->handle_quiz_submission();
        $output = ob_get_clean();
        
        $response = json_decode( $output, true );
        
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Security check failed', $response['data'] );
    }
    
    /**
     * Test AJAX submission with missing answers
     */
    public function test_ajax_submission_with_missing_answers() {
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        $csrf_manager = $this->container->get( 'security.csrf' );
        
        $this->simulate_ajax_request( 'money_quiz_submit', [
            'quiz_id' => $this->quiz_id,
            'answers' => json_encode( [] ),
            'nonce' => $csrf_manager->get_ajax_nonce(),
        ] );
        
        ob_start();
        $ajax_handler->handle_quiz_submission();
        $output = ob_get_clean();
        
        $response = json_decode( $output, true );
        
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Please answer all required questions', $response['data'] );
    }
    
    /**
     * Test assets are enqueued on quiz pages
     */
    public function test_assets_enqueued_on_quiz_pages() {
        global $post;
        
        // Create a post with quiz shortcode
        $post_id = $this->factory->post->create( [
            'post_content' => '[money_quiz]',
            'post_status' => 'publish',
        ] );
        
        $post = get_post( $post_id );
        
        // Go to singular page
        $this->go_to( get_permalink( $post_id ) );
        
        // Trigger asset enqueue
        $asset_manager = $this->container->get( 'frontend.assets' );
        $asset_manager->enqueue_assets();
        
        // Check scripts are enqueued
        $this->assertTrue( wp_script_is( 'money-quiz', 'enqueued' ) );
        $this->assertTrue( wp_style_is( 'money-quiz', 'enqueued' ) );
        
        // Check localized data
        $script_data = wp_scripts()->get_data( 'money-quiz', 'data' );
        $this->assertStringContainsString( 'money_quiz_ajax', $script_data );
    }
    
    /**
     * Test complete quiz flow
     */
    public function test_complete_quiz_flow() {
        // 1. Render quiz
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        $quiz_html = $shortcode_manager->render_quiz_shortcode( [] );
        
        $this->assertStringContainsString( 'money-quiz-container', $quiz_html );
        
        // 2. Submit quiz via AJAX
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        $csrf_manager = $this->container->get( 'security.csrf' );
        
        $prospect_data = [
            'email' => 'complete@example.com',
            'name' => 'Complete Test',
            'phone' => '123-456-7890',
            'company' => 'Test Company',
        ];
        
        $this->simulate_ajax_request( 'money_quiz_submit', [
            'quiz_id' => $this->quiz_id,
            'answers' => json_encode( [
                '1' => 'a',
                '2' => 'a',
            ] ),
            'prospect' => json_encode( $prospect_data ),
            'nonce' => $csrf_manager->get_ajax_nonce(),
        ] );
        
        // Prevent email sending in tests
        add_filter( 'pre_wp_mail', '__return_false' );
        
        ob_start();
        $ajax_handler->handle_quiz_submission();
        $output = ob_get_clean();
        
        $response = json_decode( $output, true );
        
        // 3. Verify response
        $this->assertTrue( $response['success'] );
        $this->assertEquals( 'Leader', $response['data']['archetype']['name'] );
        $this->assertGreaterThan( 0, $response['data']['score'] );
        
        // 4. Verify database state
        global $wpdb;
        
        // Check prospect was created
        $prospect = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_prospects WHERE email = %s",
            'complete@example.com'
        ) );
        
        $this->assertNotNull( $prospect );
        $this->assertEquals( 'Complete Test', $prospect->name );
        $this->assertEquals( 'Test Company', $prospect->company );
        
        // Check result was saved
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_results WHERE prospect_id = %d",
            $prospect->id
        ) );
        
        $this->assertNotNull( $result );
        $this->assertEquals( $this->quiz_id, $result->quiz_id );
        $this->assertEquals( $this->archetype_id, $result->archetype_id );
        $this->assertNotEmpty( $result->answers );
    }
    
    /**
     * Test legacy shortcode compatibility
     */
    public function test_legacy_shortcode_compatibility() {
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        
        // Legacy shortcode should work
        $output = do_shortcode( '[mq_questions]' );
        
        $this->assertStringContainsString( 'money-quiz-container', $output );
    }
    
    /**
     * Create quiz questions for testing
     */
    private function create_quiz_questions(): void {
        global $wpdb;
        
        $questions = [
            [
                'quiz_id' => $this->quiz_id,
                'question' => 'What is your leadership style?',
                'question_type' => 'multiple_choice',
                'options' => json_encode( [
                    [ 'value' => 'a', 'label' => 'Direct and decisive' ],
                    [ 'value' => 'b', 'label' => 'Collaborative and inclusive' ],
                ] ),
                'archetype_weights' => json_encode( [
                    $this->archetype_id => [ 'a' => 10, 'b' => 5 ],
                ] ),
                'sort_order' => 1,
                'is_required' => 1,
            ],
            [
                'quiz_id' => $this->quiz_id,
                'question' => 'How do you make decisions?',
                'question_type' => 'multiple_choice',
                'options' => json_encode( [
                    [ 'value' => 'a', 'label' => 'Trust my instincts' ],
                    [ 'value' => 'b', 'label' => 'Analyze all data' ],
                ] ),
                'archetype_weights' => json_encode( [
                    $this->archetype_id => [ 'a' => 10, 'b' => 5 ],
                ] ),
                'sort_order' => 2,
                'is_required' => 1,
            ],
        ];
        
        foreach ( $questions as $question ) {
            $wpdb->insert(
                $wpdb->prefix . 'money_quiz_questions',
                $question
            );
        }
    }
}