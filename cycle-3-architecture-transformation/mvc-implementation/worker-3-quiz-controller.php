<?php
/**
 * Money Quiz Plugin - Quiz Controller
 * Worker 3: MVC Implementation - Public Quiz Controller
 * 
 * Handles public-facing quiz functionality including form processing,
 * AJAX handlers, and shortcode rendering.
 * 
 * @package MoneyQuiz
 * @subpackage Controllers
 * @since 4.0.0
 */

namespace MoneyQuiz\Controllers;

use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Services\EmailService;
use MoneyQuiz\Services\ValidationService;
use MoneyQuiz\Models\QuizSubmission;

/**
 * Quiz Controller Class
 * 
 * Manages public quiz interactions
 */
class QuizController extends BaseController {
    
    /**
     * Quiz service instance
     * 
     * @var QuizService
     */
    protected $quiz_service;
    
    /**
     * Email service instance
     * 
     * @var EmailService
     */
    protected $email_service;
    
    /**
     * Validation service instance
     * 
     * @var ValidationService
     */
    protected $validation_service;
    
    /**
     * Constructor
     * 
     * @param QuizService       $quiz_service
     * @param EmailService      $email_service
     * @param ValidationService $validation_service
     */
    public function __construct( 
        QuizService $quiz_service, 
        EmailService $email_service,
        ValidationService $validation_service 
    ) {
        $this->quiz_service = $quiz_service;
        $this->email_service = $email_service;
        $this->validation_service = $validation_service;
    }
    
    /**
     * Render quiz shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_quiz_shortcode( $atts ) {
        // Parse attributes
        $atts = shortcode_atts( array(
            'id' => 0,
            'version' => 'default',
            'theme' => 'light',
            'show_progress' => 'yes',
            'redirect' => ''
        ), $atts, 'money_quiz' );
        
        // Check if quiz exists
        $quiz_id = absint( $atts['id'] );
        if ( $quiz_id && ! $this->quiz_service->quiz_exists( $quiz_id ) ) {
            return '<p>' . __( 'Quiz not found.', 'money-quiz' ) . '</p>';
        }
        
        // Get quiz data
        $quiz_data = $this->quiz_service->get_quiz_data( $quiz_id, $atts['version'] );
        
        // Prepare view data
        $data = array(
            'quiz' => $quiz_data,
            'attributes' => $atts,
            'nonce' => wp_create_nonce( 'money_quiz_submit' ),
            'ajax_url' => admin_url( 'admin-ajax.php' )
        );
        
        // Enqueue required assets
        $this->enqueue_quiz_assets();
        
        // Render quiz view
        return $this->render( 'public/quiz', $data );
    }
    
    /**
     * Render results shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_results_shortcode( $atts ) {
        // Parse attributes
        $atts = shortcode_atts( array(
            'show_chart' => 'yes',
            'show_recommendations' => 'yes',
            'cta_id' => 0
        ), $atts, 'money_quiz_results' );
        
        // Get result ID from session or URL
        $result_id = $this->get_result_id();
        if ( ! $result_id ) {
            return '<p>' . __( 'No quiz results found.', 'money-quiz' ) . '</p>';
        }
        
        // Get result data
        $result_data = $this->quiz_service->get_result_data( $result_id );
        if ( ! $result_data ) {
            return '<p>' . __( 'Invalid result ID.', 'money-quiz' ) . '</p>';
        }
        
        // Prepare view data
        $data = array(
            'result' => $result_data,
            'attributes' => $atts,
            'archetype' => $this->quiz_service->get_archetype( $result_data['archetype_id'] ),
            'recommendations' => $this->quiz_service->get_recommendations( $result_data['archetype_id'] ),
            'cta' => $atts['cta_id'] ? $this->quiz_service->get_cta( $atts['cta_id'] ) : null
        );
        
        // Render results view
        return $this->render( 'public/results', $data );
    }
    
    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Only load on pages with our shortcodes
        if ( ! $this->has_shortcode() ) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'money-quiz-public',
            MONEYQUIZ_PLUGIN_URL . 'assets/dist/public.css',
            array(),
            MONEYQUIZ_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'money-quiz-public',
            MONEYQUIZ_PLUGIN_URL . 'assets/dist/public.js',
            array( 'jquery' ),
            MONEYQUIZ_VERSION,
            true
        );
        
        // Add Chart.js if needed
        if ( $this->has_shortcode( 'money_quiz_results' ) ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(),
                '3.9.1',
                true
            );
        }
        
        // Localize script
        wp_localize_script( 'money-quiz-public', 'moneyQuiz', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'money_quiz_public' ),
            'strings' => array(
                'loading' => __( 'Loading...', 'money-quiz' ),
                'error' => __( 'An error occurred. Please try again.', 'money-quiz' ),
                'required' => __( 'This field is required.', 'money-quiz' ),
                'invalidEmail' => __( 'Please enter a valid email address.', 'money-quiz' ),
                'selectAnswer' => __( 'Please select an answer for all questions.', 'money-quiz' )
            ),
            'validation' => array(
                'email' => true,
                'phone' => false,
                'honeypot' => true
            )
        ));
    }
    
    /**
     * Process quiz form submission
     */
    public function process_quiz_form() {
        // Check if form was submitted
        if ( ! isset( $_POST['money_quiz_submit'] ) ) {
            return;
        }
        
        // Verify nonce
        if ( ! $this->verify_nonce( $_POST['_wpnonce'], 'money_quiz_submit' ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        try {
            // Validate form data
            $form_data = $this->validate_quiz_submission( $_POST );
            
            // Process submission
            $result_id = $this->quiz_service->process_submission( $form_data );
            
            // Send email if configured
            if ( $form_data['email'] ) {
                $this->email_service->send_results_email( $form_data['email'], $result_id );
            }
            
            // Store result ID in session
            $this->store_result_id( $result_id );
            
            // Redirect to results page
            $redirect_url = $this->get_results_url( $result_id );
            wp_redirect( $redirect_url );
            exit;
            
        } catch ( \Exception $e ) {
            // Handle error
            wp_die( $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for quiz submission
     */
    public function ajax_submit_quiz() {
        // Verify nonce
        if ( ! $this->verify_nonce( $_POST['nonce'], 'money_quiz_submit' ) ) {
            $this->json_error( __( 'Security check failed', 'money-quiz' ) );
        }
        
        try {
            // Validate form data
            $form_data = $this->validate_quiz_submission( $_POST );
            
            // Check honeypot
            if ( ! empty( $_POST['website'] ) ) {
                throw new \Exception( __( 'Spam detected', 'money-quiz' ) );
            }
            
            // Process submission
            $result_id = $this->quiz_service->process_submission( $form_data );
            
            // Send email asynchronously
            if ( $form_data['email'] ) {
                wp_schedule_single_event( time(), 'money_quiz_send_results_email', array(
                    'email' => $form_data['email'],
                    'result_id' => $result_id
                ));
            }
            
            // Add to email provider if configured
            $this->email_service->add_to_list( $form_data );
            
            // Store result ID
            $this->store_result_id( $result_id );
            
            // Return success response
            $this->json_response( array(
                'success' => true,
                'message' => __( 'Quiz submitted successfully!', 'money-quiz' ),
                'redirect_url' => $this->get_results_url( $result_id ),
                'result_id' => $result_id
            ));
            
        } catch ( \Exception $e ) {
            $this->json_error( $e->getMessage() );
        }
    }
    
    /**
     * Validate quiz submission data
     * 
     * @param array $post_data
     * @return array Validated data
     * @throws \Exception
     */
    protected function validate_quiz_submission( $post_data ) {
        $validated = array();
        
        // Validate quiz ID
        if ( empty( $post_data['quiz_id'] ) ) {
            throw new \Exception( __( 'Invalid quiz ID', 'money-quiz' ) );
        }
        $validated['quiz_id'] = absint( $post_data['quiz_id'] );
        
        // Validate answers
        if ( empty( $post_data['answers'] ) || ! is_array( $post_data['answers'] ) ) {
            throw new \Exception( __( 'Please answer all questions', 'money-quiz' ) );
        }
        
        // Sanitize and validate each answer
        $validated['answers'] = array();
        $questions = $this->quiz_service->get_quiz_questions( $validated['quiz_id'] );
        
        foreach ( $questions as $question ) {
            if ( ! isset( $post_data['answers'][ $question['id'] ] ) ) {
                throw new \Exception( 
                    sprintf( __( 'Please answer question: %s', 'money-quiz' ), $question['text'] )
                );
            }
            
            $answer = absint( $post_data['answers'][ $question['id'] ] );
            if ( $answer < 1 || $answer > 8 ) {
                throw new \Exception( __( 'Invalid answer value', 'money-quiz' ) );
            }
            
            $validated['answers'][ $question['id'] ] = $answer;
        }
        
        // Validate email
        $email = sanitize_email( $post_data['email'] );
        if ( ! empty( $email ) ) {
            if ( ! $this->validation_service->validate_email( $email ) ) {
                throw new \Exception( __( 'Please enter a valid email address', 'money-quiz' ) );
            }
            $validated['email'] = $email;
        } else {
            $validated['email'] = '';
        }
        
        // Validate optional fields
        $validated['first_name'] = isset( $post_data['first_name'] ) 
            ? sanitize_text_field( $post_data['first_name'] ) 
            : '';
            
        $validated['last_name'] = isset( $post_data['last_name'] ) 
            ? sanitize_text_field( $post_data['last_name'] ) 
            : '';
            
        $validated['phone'] = isset( $post_data['phone'] ) 
            ? sanitize_text_field( $post_data['phone'] ) 
            : '';
        
        // Validate phone if provided
        if ( ! empty( $validated['phone'] ) && ! $this->validation_service->validate_phone( $validated['phone'] ) ) {
            throw new \Exception( __( 'Please enter a valid phone number', 'money-quiz' ) );
        }
        
        // Add metadata
        $validated['ip_address'] = $this->get_client_ip();
        $validated['user_agent'] = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
        $validated['referrer'] = isset( $_SERVER['HTTP_REFERER'] ) 
            ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) 
            : '';
        
        return $validated;
    }
    
    /**
     * Check if page has our shortcode
     * 
     * @param string $shortcode Specific shortcode to check
     * @return bool
     */
    protected function has_shortcode( $shortcode = '' ) {
        global $post;
        
        if ( ! is_singular() || ! $post ) {
            return false;
        }
        
        if ( $shortcode ) {
            return has_shortcode( $post->post_content, $shortcode );
        }
        
        return has_shortcode( $post->post_content, 'money_quiz' ) 
            || has_shortcode( $post->post_content, 'money_quiz_results' );
    }
    
    /**
     * Enqueue quiz-specific assets
     */
    protected function enqueue_quiz_assets() {
        // Add inline CSS for theme
        $theme_css = $this->quiz_service->get_theme_css();
        if ( $theme_css ) {
            wp_add_inline_style( 'money-quiz-public', $theme_css );
        }
    }
    
    /**
     * Get result ID from session or URL
     * 
     * @return int|false
     */
    protected function get_result_id() {
        // Check URL parameter first
        if ( isset( $_GET['result'] ) ) {
            return absint( $_GET['result'] );
        }
        
        // Check session
        if ( isset( $_SESSION['money_quiz_result_id'] ) ) {
            return absint( $_SESSION['money_quiz_result_id'] );
        }
        
        // Check cookie
        if ( isset( $_COOKIE['money_quiz_result_id'] ) ) {
            return absint( $_COOKIE['money_quiz_result_id'] );
        }
        
        return false;
    }
    
    /**
     * Store result ID in session
     * 
     * @param int $result_id
     */
    protected function store_result_id( $result_id ) {
        // Start session if not started
        if ( ! session_id() ) {
            session_start();
        }
        
        // Store in session
        $_SESSION['money_quiz_result_id'] = $result_id;
        
        // Also store in cookie for 24 hours
        setcookie( 
            'money_quiz_result_id', 
            $result_id, 
            time() + DAY_IN_SECONDS, 
            COOKIEPATH, 
            COOKIE_DOMAIN 
        );
    }
    
    /**
     * Get results page URL
     * 
     * @param int $result_id
     * @return string
     */
    protected function get_results_url( $result_id ) {
        $results_page_id = $this->quiz_service->get_results_page_id();
        
        if ( $results_page_id ) {
            $url = get_permalink( $results_page_id );
            return add_query_arg( 'result', $result_id, $url );
        }
        
        // Fallback to current page with results parameter
        return add_query_arg( array(
            'money_quiz_results' => 1,
            'result' => $result_id
        ), home_url() );
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    protected function get_client_ip() {
        $ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                $ips = explode( ',', $_SERVER[ $key ] );
                foreach ( $ips as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}