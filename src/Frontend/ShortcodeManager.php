<?php
/**
 * Shortcode Manager
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Frontend;

use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Security\CsrfManager;

/**
 * Manages frontend shortcodes
 */
class ShortcodeManager {
    
    /**
     * @var QuizService
     */
    private QuizService $quiz_service;
    
    /**
     * @var CsrfManager
     */
    private CsrfManager $csrf_manager;
    
    /**
     * @var string Path to templates
     */
    private string $template_path;
    
    /**
     * Constructor
     * 
     * @param QuizService $quiz_service
     * @param CsrfManager $csrf_manager
     */
    public function __construct( QuizService $quiz_service, CsrfManager $csrf_manager ) {
        $this->quiz_service = $quiz_service;
        $this->csrf_manager = $csrf_manager;
        $this->template_path = MONEY_QUIZ_PLUGIN_DIR . 'templates/';
    }
    
    /**
     * Register shortcodes
     * 
     * @return void
     */
    public function register_shortcodes(): void {
        // Don't register if legacy shortcode is already registered
        if ( ! shortcode_exists( 'mq_questions' ) ) {
            add_shortcode( 'mq_questions', [ $this, 'render_quiz_shortcode' ] );
        }
        
        // Register modern shortcode aliases
        add_shortcode( 'money_quiz', [ $this, 'render_quiz_shortcode' ] );
        add_shortcode( 'money-quiz', [ $this, 'render_quiz_shortcode' ] ); // Alias
    }
    
    /**
     * Render quiz shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_quiz_shortcode( $atts ): string {
        // Parse attributes
        $attributes = shortcode_atts( [
            'id' => '',
            'quiz_id' => '', // Alias for id
            'title' => 'true',
            'description' => 'true',
            'progress' => 'true',
            'class' => 'money-quiz-container',
        ], $atts );
        
        // Get quiz ID
        $quiz_id = ! empty( $attributes['id'] ) ? $attributes['id'] : $attributes['quiz_id'];
        
        // If no quiz ID specified, get the default quiz
        if ( empty( $quiz_id ) ) {
            $quiz_id = $this->get_default_quiz_id();
        }
        
        if ( empty( $quiz_id ) ) {
            return $this->render_error( __( 'No quiz found. Please specify a quiz ID.', 'money-quiz' ) );
        }
        
        try {
            // Get quiz data
            $quiz = $this->quiz_service->get_quiz( (int) $quiz_id );
            
            if ( ! $quiz ) {
                return $this->render_error( __( 'Quiz not found.', 'money-quiz' ) );
            }
            
            // Enqueue frontend assets
            $this->enqueue_assets();
            
            // Generate CSRF token for form
            $csrf_token = $this->csrf_manager->generate_token( 'quiz_submit_' . $quiz_id );
            
            // Prepare template data
            $template_data = [
                'quiz' => $quiz,
                'attributes' => $attributes,
                'csrf_token' => $csrf_token,
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'money_quiz_submit' ),
            ];
            
            // Check for legacy template first
            $legacy_template = $this->get_legacy_template_path();
            if ( file_exists( $legacy_template ) ) {
                return $this->render_legacy_template( $legacy_template, $template_data );
            }
            
            // Use modern template
            return $this->render_template( 'quiz-display.php', $template_data );
            
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                return $this->render_error( 'Error: ' . $e->getMessage() );
            }
            return $this->render_error( __( 'An error occurred while loading the quiz.', 'money-quiz' ) );
        }
    }
    
    /**
     * Get default quiz ID
     * 
     * @return int|null
     */
    private function get_default_quiz_id(): ?int {
        // Check settings for default quiz
        $default_id = get_option( 'money_quiz_default_quiz_id' );
        
        if ( $default_id ) {
            return (int) $default_id;
        }
        
        // Get the first available quiz
        global $wpdb;
        $table = $wpdb->prefix . 'money_quiz';
        
        $quiz_id = $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id ASC LIMIT 1" );
        
        return $quiz_id ? (int) $quiz_id : null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    private function enqueue_assets(): void {
        // Check if assets are already enqueued by legacy code
        if ( wp_script_is( 'money-quiz-script', 'enqueued' ) ) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'money-quiz-style',
            MONEY_QUIZ_PLUGIN_URL . 'assets/css/money-quiz.css',
            [],
            MONEY_QUIZ_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'money-quiz-script',
            MONEY_QUIZ_PLUGIN_URL . 'assets/js/money-quiz.js',
            [ 'jquery' ],
            MONEY_QUIZ_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script( 'money-quiz-script', 'money_quiz_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'money_quiz_submit' ),
            'messages' => [
                'error' => __( 'An error occurred. Please try again.', 'money-quiz' ),
                'required' => __( 'Please answer all questions.', 'money-quiz' ),
                'submitting' => __( 'Submitting...', 'money-quiz' ),
            ],
        ] );
    }
    
    /**
     * Get legacy template path
     * 
     * @return string
     */
    private function get_legacy_template_path(): string {
        // Check if legacy template exists
        $legacy_paths = [
            MONEY_QUIZ_PLUGIN_DIR . 'money-quiz-template.php',
            MONEY_QUIZ_PLUGIN_DIR . 'templates/money-quiz-template.php',
            MONEY_QUIZ_PLUGIN_DIR . 'includes/templates/money-quiz-template.php',
        ];
        
        foreach ( $legacy_paths as $path ) {
            if ( file_exists( $path ) ) {
                return $path;
            }
        }
        
        return '';
    }
    
    /**
     * Render legacy template
     * 
     * @param string $template_path Path to template
     * @param array  $data          Template data
     * @return string
     */
    private function render_legacy_template( string $template_path, array $data ): string {
        // Extract data for legacy template
        extract( $data );
        
        // Start output buffering
        ob_start();
        
        // Include legacy template
        include $template_path;
        
        // Get output
        $output = ob_get_clean();
        
        // Inject CSRF token if form exists
        if ( strpos( $output, '</form>' ) !== false ) {
            $csrf_field = sprintf(
                '<input type="hidden" name="csrf_token" value="%s" />',
                esc_attr( $data['csrf_token'] )
            );
            $output = str_replace( '</form>', $csrf_field . '</form>', $output );
        }
        
        return $output;
    }
    
    /**
     * Render template
     * 
     * @param string $template Template file name
     * @param array  $data     Template data
     * @return string
     */
    private function render_template( string $template, array $data = [] ): string {
        $template_path = $this->template_path . $template;
        
        if ( ! file_exists( $template_path ) ) {
            return $this->render_error( __( 'Template not found.', 'money-quiz' ) );
        }
        
        // Extract data
        extract( $data );
        
        // Start output buffering
        ob_start();
        
        // Include template
        include $template_path;
        
        return ob_get_clean();
    }
    
    /**
     * Render error message
     * 
     * @param string $message Error message
     * @return string
     */
    private function render_error( string $message ): string {
        return sprintf(
            '<div class="money-quiz-error">%s</div>',
            esc_html( $message )
        );
    }
}