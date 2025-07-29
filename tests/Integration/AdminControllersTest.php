<?php
/**
 * Admin controllers integration tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Integration;

use MoneyQuiz\Tests\IntegrationTestCase;
use MoneyQuiz\Admin\Controllers\QuizController;
use MoneyQuiz\Admin\Controllers\ResultsController;
use MoneyQuiz\Admin\Controllers\SettingsController;

/**
 * Test admin controller functionality
 */
class AdminControllersTest extends IntegrationTestCase {
    
    /**
     * @var int
     */
    private int $admin_user_id;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create admin user and log in
        $this->admin_user_id = $this->create_test_user( 'administrator' );
        wp_set_current_user( $this->admin_user_id );
        
        // Set up $_SERVER for admin context
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    
    /**
     * Test quiz controller index page
     */
    public function test_quiz_controller_index() {
        $controller = new QuizController();
        
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        
        $this->assertStringContainsString( 'Quizzes', $output );
        $this->assertStringContainsString( 'Add New', $output );
    }
    
    /**
     * Test quiz controller create form
     */
    public function test_quiz_controller_create_form() {
        $controller = new QuizController();
        
        ob_start();
        $controller->create();
        $output = ob_get_clean();
        
        $this->assertStringContainsString( 'Add New Quiz', $output );
        $this->assertStringContainsString( 'name="title"', $output );
        $this->assertStringContainsString( 'id="questions-container"', $output );
    }
    
    /**
     * Test quiz controller create submission
     */
    public function test_quiz_controller_create_submission() {
        $controller = new QuizController();
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_wpnonce'] = wp_create_nonce( 'create_quiz' );
        $_POST['title'] = 'Test Quiz Creation';
        $_POST['description'] = 'Test quiz description';
        $_POST['is_active'] = '1';
        $_POST['questions'] = [
            [
                'text' => 'Question 1',
                'options' => [
                    [ 'value' => 'a', 'label' => 'Option A' ],
                    [ 'value' => 'b', 'label' => 'Option B' ],
                ],
            ],
        ];
        
        // Capture redirect
        add_filter( 'wp_redirect', function( $location ) {
            $this->assertStringContainsString( 'message=created', $location );
            return false; // Prevent actual redirect
        } );
        
        $controller->create();
        
        // Verify quiz was created
        $this->assertRecordExists( 'money_quiz_quizzes', [
            'title' => 'Test Quiz Creation',
        ] );
    }
    
    /**
     * Test quiz controller edit
     */
    public function test_quiz_controller_edit() {
        // Create a quiz first
        $quiz_id = $this->create_test_quiz( [
            'title' => 'Edit Test Quiz',
        ] );
        
        $controller = new QuizController();
        
        ob_start();
        $controller->edit( $quiz_id );
        $output = ob_get_clean();
        
        $this->assertStringContainsString( 'Edit Quiz', $output );
        $this->assertStringContainsString( 'Edit Test Quiz', $output );
        $this->assertStringContainsString( 'value="Edit Test Quiz"', $output );
    }
    
    /**
     * Test quiz controller delete
     */
    public function test_quiz_controller_delete() {
        $quiz_id = $this->create_test_quiz( [
            'title' => 'Delete Test Quiz',
        ] );
        
        $controller = new QuizController();
        
        $_GET['_wpnonce'] = wp_create_nonce( 'delete_quiz_' . $quiz_id );
        
        // Capture redirect
        add_filter( 'wp_redirect', function( $location ) {
            $this->assertStringContainsString( 'message=deleted', $location );
            return false;
        } );
        
        $controller->delete( $quiz_id );
        
        // Verify quiz was deleted
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $quiz_id
        ) );
        
        $this->assertEquals( 0, $exists );
    }
    
    /**
     * Test results controller index
     */
    public function test_results_controller_index() {
        // Create test data
        $quiz_id = $this->create_test_quiz();
        $archetype_id = $this->create_test_archetype();
        $prospect_id = $this->create_test_prospect();
        
        // Create a result
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_results',
            [
                'quiz_id' => $quiz_id,
                'prospect_id' => $prospect_id,
                'archetype_id' => $archetype_id,
                'score' => 85.5,
                'answers' => json_encode( [ '1' => 'a', '2' => 'b' ] ),
            ]
        );
        
        $controller = new ResultsController();
        
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        
        $this->assertStringContainsString( 'Quiz Results', $output );
        $this->assertStringContainsString( 'Export CSV', $output );
        $this->assertStringContainsString( 'test@example.com', $output );
        $this->assertStringContainsString( '85.5%', $output );
    }
    
    /**
     * Test results controller CSV export
     */
    public function test_results_controller_csv_export() {
        // Create test data
        $quiz_id = $this->create_test_quiz();
        $archetype_id = $this->create_test_archetype( [ 'name' => 'Leader' ] );
        $prospect_id = $this->create_test_prospect( [
            'email' => 'export@example.com',
            'name' => 'Export Test',
        ] );
        
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_results',
            [
                'quiz_id' => $quiz_id,
                'prospect_id' => $prospect_id,
                'archetype_id' => $archetype_id,
                'score' => 92,
                'answers' => json_encode( [ '1' => 'a' ] ),
            ]
        );
        
        $controller = new ResultsController();
        
        $_GET['export'] = 'csv';
        
        // Capture output instead of actual file download
        ob_start();
        
        // Override header function for testing
        add_filter( 'wp_headers', function( $headers ) {
            $this->assertArrayHasKey( 'Content-Type', $headers );
            $this->assertEquals( 'text/csv; charset=utf-8', $headers['Content-Type'] );
            return $headers;
        } );
        
        $controller->index();
        $output = ob_get_clean();
        
        // Basic CSV validation
        $this->assertStringContainsString( 'Export Test', $output );
        $this->assertStringContainsString( 'export@example.com', $output );
        $this->assertStringContainsString( 'Leader', $output );
    }
    
    /**
     * Test results filtering
     */
    public function test_results_filtering() {
        // Create multiple results
        $quiz_id = $this->create_test_quiz();
        $archetype_id = $this->create_test_archetype();
        
        $prospect1 = $this->create_test_prospect( [
            'email' => 'filter1@example.com',
            'name' => 'Filter Test 1',
        ] );
        
        $prospect2 = $this->create_test_prospect( [
            'email' => 'filter2@example.com', 
            'name' => 'Different Name',
        ] );
        
        global $wpdb;
        
        // Result 1
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_results',
            [
                'quiz_id' => $quiz_id,
                'prospect_id' => $prospect1,
                'archetype_id' => $archetype_id,
                'score' => 80,
                'completed_at' => current_time( 'mysql' ),
            ]
        );
        
        // Result 2
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_results',
            [
                'quiz_id' => $quiz_id,
                'prospect_id' => $prospect2,
                'archetype_id' => $archetype_id,
                'score' => 90,
                'completed_at' => current_time( 'mysql' ),
            ]
        );
        
        $controller = new ResultsController();
        
        // Test search filter
        $_GET['s'] = 'filter1';
        
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        
        $this->assertStringContainsString( 'filter1@example.com', $output );
        $this->assertStringNotContainsString( 'filter2@example.com', $output );
    }
    
    /**
     * Test settings controller
     */
    public function test_settings_controller() {
        $controller = new SettingsController();
        
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        
        $this->assertStringContainsString( 'Money Quiz Settings', $output );
        $this->assertStringContainsString( 'General Settings', $output );
        $this->assertStringContainsString( 'Email Settings', $output );
        $this->assertStringContainsString( 'Advanced Settings', $output );
    }
    
    /**
     * Test settings save
     */
    public function test_settings_save() {
        // Get settings manager
        $settings_manager = $this->container->get( 'admin.settings' );
        
        // Update a setting
        $updated = $settings_manager->update_option( 'general', 'company_name', 'Test Company' );
        $this->assertTrue( $updated );
        
        // Verify it was saved
        $value = $settings_manager->get_option( 'general', 'company_name' );
        $this->assertEquals( 'Test Company', $value );
    }
    
    /**
     * Test bulk actions
     */
    public function test_bulk_actions() {
        // Create multiple quizzes
        $quiz_ids = [];
        for ( $i = 1; $i <= 3; $i++ ) {
            $quiz_ids[] = $this->create_test_quiz( [
                'title' => "Bulk Test Quiz {$i}",
                'is_active' => 1,
            ] );
        }
        
        $controller = new QuizController();
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action'] = 'deactivate';
        $_POST['quiz'] = array_map( 'strval', $quiz_ids );
        $_POST['_wpnonce'] = wp_create_nonce( 'bulk-quizzes' );
        
        // Capture redirect
        add_filter( 'wp_redirect', function( $location ) {
            $this->assertStringContainsString( 'message=deactivated', $location );
            return false;
        } );
        
        $controller->index();
        
        // Verify quizzes were deactivated
        global $wpdb;
        $active_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_quizzes 
             WHERE id IN (" . implode( ',', array_fill( 0, count( $quiz_ids ), '%d' ) ) . ") 
             AND is_active = 1",
            ...$quiz_ids
        ) );
        
        $this->assertEquals( 0, $active_count );
    }
    
    /**
     * Test permission checks
     */
    public function test_permission_checks() {
        // Create non-admin user
        $subscriber_id = $this->create_test_user( 'subscriber' );
        wp_set_current_user( $subscriber_id );
        
        $controller = new QuizController();
        
        // Attempt to delete quiz should fail
        $quiz_id = $this->create_test_quiz();
        $_GET['_wpnonce'] = wp_create_nonce( 'delete_quiz_' . $quiz_id );
        
        // This should trigger wp_die
        $this->expectException( \WPDieException::class );
        $controller->delete( $quiz_id );
    }
    
    /**
     * Test menu registration
     */
    public function test_menu_registration() {
        global $submenu;
        
        $menu_manager = $this->container->get( 'admin.menu' );
        $menu_manager->register_menus();
        
        // Check main menu exists
        $this->assertArrayHasKey( 'money-quiz', $submenu );
        
        // Check submenu items
        $submenu_items = $submenu['money-quiz'];
        $menu_slugs = array_column( $submenu_items, 2 );
        
        $this->assertContains( 'money-quiz', $menu_slugs ); // Dashboard
        $this->assertContains( 'money-quiz-quizzes', $menu_slugs );
        $this->assertContains( 'money-quiz-results', $menu_slugs );
        $this->assertContains( 'money-quiz-settings', $menu_slugs );
    }
}