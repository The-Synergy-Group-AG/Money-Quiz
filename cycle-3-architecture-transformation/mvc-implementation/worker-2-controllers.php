<?php
/**
 * Money Quiz Plugin - Controllers
 * Worker 2: MVC Implementation - Controllers
 * 
 * Implements controller classes that handle requests and coordinate
 * between models and views following MVC pattern.
 * 
 * @package MoneyQuiz
 * @subpackage Controllers
 * @since 4.0.0
 */

namespace MoneyQuiz\Controllers;

use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Services\DatabaseService;
use MoneyQuiz\Services\EmailService;
use MoneyQuiz\Services\ValidationService;

/**
 * Base Controller Class
 * 
 * Provides common functionality for all controllers
 */
abstract class BaseController {
    
    /**
     * Render a view
     * 
     * @param string $view View file name
     * @param array  $data Data to pass to view
     * @return string Rendered HTML
     */
    protected function render( $view, $data = array() ) {
        // Extract data for use in view
        extract( $data );
        
        // Start output buffering
        ob_start();
        
        // Include view file
        $view_file = MONEYQUIZ_PLUGIN_DIR . "src/Views/{$view}.php";
        if ( file_exists( $view_file ) ) {
            include $view_file;
        } else {
            echo "<!-- View not found: {$view} -->";
        }
        
        // Return rendered content
        return ob_get_clean();
    }
    
    /**
     * Send JSON response
     * 
     * @param mixed $data
     * @param int   $status_code
     */
    protected function json_response( $data, $status_code = 200 ) {
        wp_send_json( $data, $status_code );
    }
    
    /**
     * Send JSON error response
     * 
     * @param string $message
     * @param int    $status_code
     */
    protected function json_error( $message, $status_code = 400 ) {
        wp_send_json_error( array( 'message' => $message ), $status_code );
    }
    
    /**
     * Verify nonce
     * 
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    protected function verify_nonce( $nonce, $action ) {
        return wp_verify_nonce( $nonce, $action );
    }
    
    /**
     * Check user capability
     * 
     * @param string $capability
     * @return bool
     */
    protected function check_capability( $capability ) {
        return current_user_can( $capability );
    }
}

/**
 * Admin Controller
 * 
 * Handles all admin-related functionality
 */
class AdminController extends BaseController {
    
    /**
     * Quiz service instance
     * 
     * @var QuizService
     */
    protected $quiz_service;
    
    /**
     * Database service instance
     * 
     * @var DatabaseService
     */
    protected $database_service;
    
    /**
     * Constructor
     * 
     * @param QuizService     $quiz_service
     * @param DatabaseService $database_service
     */
    public function __construct( QuizService $quiz_service, DatabaseService $database_service ) {
        $this->quiz_service = $quiz_service;
        $this->database_service = $database_service;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Money Quiz', 'money-quiz' ),
            __( 'Money Quiz', 'money-quiz' ),
            'manage_options',
            'money-quiz',
            array( $this, 'render_dashboard_page' ),
            'dashicons-chart-pie',
            30
        );
        
        add_submenu_page(
            'money-quiz',
            __( 'Dashboard', 'money-quiz' ),
            __( 'Dashboard', 'money-quiz' ),
            'manage_options',
            'money-quiz',
            array( $this, 'render_dashboard_page' )
        );
        
        add_submenu_page(
            'money-quiz',
            __( 'Questions', 'money-quiz' ),
            __( 'Questions', 'money-quiz' ),
            'manage_options',
            'money-quiz-questions',
            array( $this, 'render_questions_page' )
        );
        
        add_submenu_page(
            'money-quiz',
            __( 'Results', 'money-quiz' ),
            __( 'Results', 'money-quiz' ),
            'manage_options',
            'money-quiz-results',
            array( $this, 'render_results_page' )
        );
        
        add_submenu_page(
            'money-quiz',
            __( 'Settings', 'money-quiz' ),
            __( 'Settings', 'money-quiz' ),
            'manage_options',
            'money-quiz-settings',
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages
        if ( strpos( $hook, 'money-quiz' ) === false ) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'money-quiz-admin',
            MONEYQUIZ_PLUGIN_URL . 'assets/dist/admin.css',
            array(),
            MONEYQUIZ_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'money-quiz-admin',
            MONEYQUIZ_PLUGIN_URL . 'assets/dist/admin.js',
            array( 'jquery', 'wp-api' ),
            MONEYQUIZ_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script( 'money-quiz-admin', 'moneyQuizAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'apiUrl' => home_url( '/wp-json/money-quiz/v1' ),
            'nonce' => wp_create_nonce( 'money_quiz_admin' ),
            'strings' => array(
                'confirmDelete' => __( 'Are you sure you want to delete this item?', 'money-quiz' ),
                'saving' => __( 'Saving...', 'money-quiz' ),
                'saved' => __( 'Saved!', 'money-quiz' ),
                'error' => __( 'An error occurred. Please try again.', 'money-quiz' )
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Get dashboard data
        $data = array(
            'total_quizzes' => $this->quiz_service->get_total_quizzes_taken(),
            'total_leads' => $this->quiz_service->get_total_leads(),
            'recent_results' => $this->quiz_service->get_recent_results( 10 ),
            'archetype_distribution' => $this->quiz_service->get_archetype_distribution(),
            'conversion_rate' => $this->quiz_service->get_conversion_rate()
        );
        
        // Render view
        echo $this->render( 'admin/dashboard', $data );
    }
    
    /**
     * Render questions page
     */
    public function render_questions_page() {
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Get questions data
        $data = array(
            'questions' => $this->quiz_service->get_all_questions(),
            'categories' => $this->quiz_service->get_question_categories()
        );
        
        // Render view
        echo $this->render( 'admin/questions', $data );
    }
    
    /**
     * Render results page
     */
    public function render_results_page() {
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Get results data with pagination
        $page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page = 20;
        
        $data = array(
            'results' => $this->quiz_service->get_results( $page, $per_page ),
            'total_results' => $this->quiz_service->get_total_results(),
            'current_page' => $page,
            'per_page' => $per_page
        );
        
        // Render view
        echo $this->render( 'admin/results', $data );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Handle form submission
        if ( isset( $_POST['submit'] ) && $this->verify_nonce( $_POST['_wpnonce'], 'money_quiz_settings' ) ) {
            $this->save_settings();
        }
        
        // Get settings data
        $data = array(
            'settings' => $this->quiz_service->get_settings(),
            'email_providers' => $this->quiz_service->get_email_providers(),
            'archetypes' => $this->quiz_service->get_archetypes()
        );
        
        // Render view
        echo $this->render( 'admin/settings', $data );
    }
    
    /**
     * AJAX handler for saving question
     */
    public function ajax_save_question() {
        // Verify nonce
        if ( ! $this->verify_nonce( $_POST['nonce'], 'money_quiz_admin' ) ) {
            $this->json_error( __( 'Security check failed', 'money-quiz' ) );
        }
        
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            $this->json_error( __( 'Insufficient permissions', 'money-quiz' ) );
        }
        
        // Validate and save question
        try {
            $question_data = array(
                'id' => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
                'question' => sanitize_text_field( $_POST['question'] ),
                'category' => sanitize_text_field( $_POST['category'] ),
                'answers' => array_map( 'sanitize_text_field', $_POST['answers'] ),
                'weights' => array_map( 'absint', $_POST['weights'] )
            );
            
            $question_id = $this->quiz_service->save_question( $question_data );
            
            $this->json_response( array(
                'success' => true,
                'message' => __( 'Question saved successfully', 'money-quiz' ),
                'question_id' => $question_id
            ));
            
        } catch ( \Exception $e ) {
            $this->json_error( $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for deleting question
     */
    public function ajax_delete_question() {
        // Verify nonce
        if ( ! $this->verify_nonce( $_POST['nonce'], 'money_quiz_admin' ) ) {
            $this->json_error( __( 'Security check failed', 'money-quiz' ) );
        }
        
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            $this->json_error( __( 'Insufficient permissions', 'money-quiz' ) );
        }
        
        // Delete question
        try {
            $question_id = absint( $_POST['question_id'] );
            $this->quiz_service->delete_question( $question_id );
            
            $this->json_response( array(
                'success' => true,
                'message' => __( 'Question deleted successfully', 'money-quiz' )
            ));
            
        } catch ( \Exception $e ) {
            $this->json_error( $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for exporting data
     */
    public function ajax_export_data() {
        // Verify nonce
        if ( ! $this->verify_nonce( $_POST['nonce'], 'money_quiz_admin' ) ) {
            $this->json_error( __( 'Security check failed', 'money-quiz' ) );
        }
        
        // Check capability
        if ( ! $this->check_capability( 'manage_options' ) ) {
            $this->json_error( __( 'Insufficient permissions', 'money-quiz' ) );
        }
        
        // Export data
        try {
            $export_type = sanitize_text_field( $_POST['export_type'] );
            $date_range = array(
                'start' => sanitize_text_field( $_POST['start_date'] ),
                'end' => sanitize_text_field( $_POST['end_date'] )
            );
            
            $export_url = $this->quiz_service->export_data( $export_type, $date_range );
            
            $this->json_response( array(
                'success' => true,
                'download_url' => $export_url
            ));
            
        } catch ( \Exception $e ) {
            $this->json_error( $e->getMessage() );
        }
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check for any notices to display
        $notices = get_transient( 'money_quiz_admin_notices' );
        if ( ! empty( $notices ) ) {
            foreach ( $notices as $notice ) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $notice['type'] ),
                    esc_html( $notice['message'] )
                );
            }
            delete_transient( 'money_quiz_admin_notices' );
        }
    }
    
    /**
     * Save settings
     */
    protected function save_settings() {
        try {
            // Sanitize and validate settings
            $settings = array(
                'email_provider' => sanitize_text_field( $_POST['email_provider'] ),
                'api_key' => sanitize_text_field( $_POST['api_key'] ),
                'list_id' => sanitize_text_field( $_POST['list_id'] ),
                'enable_tracking' => isset( $_POST['enable_tracking'] ) ? 1 : 0,
                'quiz_title' => sanitize_text_field( $_POST['quiz_title'] ),
                'results_page' => absint( $_POST['results_page'] ),
                'thank_you_message' => wp_kses_post( $_POST['thank_you_message'] )
            );
            
            $this->quiz_service->save_settings( $settings );
            
            // Add success notice
            set_transient( 'money_quiz_admin_notices', array(
                array(
                    'type' => 'success',
                    'message' => __( 'Settings saved successfully', 'money-quiz' )
                )
            ), 30 );
            
        } catch ( \Exception $e ) {
            // Add error notice
            set_transient( 'money_quiz_admin_notices', array(
                array(
                    'type' => 'error',
                    'message' => $e->getMessage()
                )
            ), 30 );
        }
    }
}