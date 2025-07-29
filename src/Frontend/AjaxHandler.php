<?php
/**
 * AJAX Handler
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Frontend;

use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Services\EmailService;
use MoneyQuiz\Security\CsrfManager;
use MoneyQuiz\Exceptions\QuizException;

/**
 * Handles AJAX requests for frontend
 */
class AjaxHandler {
    
    /**
     * @var QuizService
     */
    private QuizService $quiz_service;
    
    /**
     * @var EmailService
     */
    private EmailService $email_service;
    
    /**
     * @var CsrfManager
     */
    private CsrfManager $csrf_manager;
    
    /**
     * Constructor
     * 
     * @param QuizService  $quiz_service
     * @param EmailService $email_service
     * @param CsrfManager  $csrf_manager
     */
    public function __construct( 
        QuizService $quiz_service, 
        EmailService $email_service,
        CsrfManager $csrf_manager 
    ) {
        $this->quiz_service = $quiz_service;
        $this->email_service = $email_service;
        $this->csrf_manager = $csrf_manager;
    }
    
    /**
     * Register AJAX handlers
     * 
     * @return void
     */
    public function register_handlers(): void {
        // Public handlers
        add_action( 'wp_ajax_money_quiz_submit', [ $this, 'handle_quiz_submission' ] );
        add_action( 'wp_ajax_nopriv_money_quiz_submit', [ $this, 'handle_quiz_submission' ] );
        
        // Legacy handler support
        add_action( 'wp_ajax_mq_submit_quiz', [ $this, 'handle_quiz_submission' ] );
        add_action( 'wp_ajax_nopriv_mq_submit_quiz', [ $this, 'handle_quiz_submission' ] );
        
        // Additional handlers
        add_action( 'wp_ajax_money_quiz_save_progress', [ $this, 'handle_save_progress' ] );
        add_action( 'wp_ajax_nopriv_money_quiz_save_progress', [ $this, 'handle_save_progress' ] );
    }
    
    /**
     * Handle quiz submission
     * 
     * @return void
     */
    public function handle_quiz_submission(): void {
        try {
            // Verify nonce
            if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'money_quiz_submit' ) ) {
                throw new \Exception( __( 'Security check failed. Please refresh the page and try again.', 'money-quiz' ) );
            }
            
            // Verify CSRF token if present
            if ( ! empty( $_POST['csrf_token'] ) ) {
                $quiz_id = absint( $_POST['quiz_id'] ?? 0 );
                if ( ! $this->csrf_manager->verify_token( $_POST['csrf_token'], 'quiz_submit_' . $quiz_id ) ) {
                    throw new \Exception( __( 'Session expired. Please refresh the page and try again.', 'money-quiz' ) );
                }
            }
            
            // Validate required fields
            $quiz_id = absint( $_POST['quiz_id'] ?? 0 );
            $answers = $_POST['answers'] ?? [];
            
            if ( ! $quiz_id ) {
                throw new \Exception( __( 'Invalid quiz ID.', 'money-quiz' ) );
            }
            
            if ( empty( $answers ) || ! is_array( $answers ) ) {
                throw new \Exception( __( 'Please answer all questions.', 'money-quiz' ) );
            }
            
            // Sanitize answers
            $sanitized_answers = [];
            foreach ( $answers as $question_id => $answer ) {
                $sanitized_answers[ absint( $question_id ) ] = sanitize_text_field( $answer );
            }
            
            // Prepare user data if provided
            $user_data = $this->prepare_user_data();
            
            // Process submission
            $result = $this->quiz_service->process_submission( 
                $quiz_id, 
                $sanitized_answers, 
                $user_data 
            );
            
            // Send emails if configured
            $this->send_emails( $result, $user_data );
            
            // Prepare response data
            $response_data = $this->prepare_response_data( $result );
            
            // Success response
            wp_send_json_success( $response_data );
            
        } catch ( QuizException $e ) {
            wp_send_json_error( [
                'message' => $e->getMessage(),
                'code' => 'quiz_error',
            ], 400 );
            
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Money Quiz Submission Error: ' . $e->getMessage() );
            }
            
            wp_send_json_error( [
                'message' => $e->getMessage(),
                'code' => 'general_error',
            ], 500 );
        }
    }
    
    /**
     * Handle progress saving
     * 
     * @return void
     */
    public function handle_save_progress(): void {
        try {
            // Verify nonce
            if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'money_quiz_submit' ) ) {
                throw new \Exception( __( 'Security check failed.', 'money-quiz' ) );
            }
            
            $quiz_id = absint( $_POST['quiz_id'] ?? 0 );
            $progress = $_POST['progress'] ?? [];
            
            if ( ! $quiz_id ) {
                throw new \Exception( __( 'Invalid quiz ID.', 'money-quiz' ) );
            }
            
            // Save progress to session or transient
            $progress_key = 'money_quiz_progress_' . md5( $quiz_id . '_' . session_id() );
            set_transient( $progress_key, $progress, HOUR_IN_SECONDS );
            
            wp_send_json_success( [
                'message' => __( 'Progress saved.', 'money-quiz' ),
                'progress_key' => $progress_key,
            ] );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( [
                'message' => $e->getMessage(),
            ], 400 );
        }
    }
    
    /**
     * Prepare user data from POST
     * 
     * @return array
     */
    private function prepare_user_data(): array {
        $user_data = [];
        
        // Email (most important)
        if ( ! empty( $_POST['email'] ) ) {
            $email = sanitize_email( $_POST['email'] );
            if ( is_email( $email ) ) {
                $user_data['email'] = $email;
            }
        }
        
        // Name fields
        if ( ! empty( $_POST['name'] ) ) {
            $user_data['name'] = sanitize_text_field( $_POST['name'] );
        } elseif ( ! empty( $_POST['first_name'] ) || ! empty( $_POST['last_name'] ) ) {
            $first = sanitize_text_field( $_POST['first_name'] ?? '' );
            $last = sanitize_text_field( $_POST['last_name'] ?? '' );
            $user_data['name'] = trim( $first . ' ' . $last );
        }
        
        // Other fields
        $fields = [ 'phone', 'company', 'website', 'message' ];
        foreach ( $fields as $field ) {
            if ( ! empty( $_POST[ $field ] ) ) {
                $user_data[ $field ] = sanitize_text_field( $_POST[ $field ] );
            }
        }
        
        // UTM parameters
        $utm_fields = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
        foreach ( $utm_fields as $utm ) {
            if ( ! empty( $_POST[ $utm ] ) ) {
                $user_data[ $utm ] = sanitize_text_field( $_POST[ $utm ] );
            }
        }
        
        // Consent checkboxes
        if ( ! empty( $_POST['consent_email'] ) ) {
            $user_data['consent_email'] = true;
        }
        
        if ( ! empty( $_POST['consent_terms'] ) ) {
            $user_data['consent_terms'] = true;
        }
        
        return $user_data;
    }
    
    /**
     * Send emails after submission
     * 
     * @param array $result    Quiz result
     * @param array $user_data User data
     * @return void
     */
    private function send_emails( array $result, array $user_data ): void {
        // Send user email if they provided email and consented
        if ( ! empty( $user_data['email'] ) && get_option( 'money_quiz_send_user_email', true ) ) {
            try {
                $this->email_service->send_quiz_result( 
                    $user_data['email'], 
                    $result,
                    [ 'user_name' => $user_data['name'] ?? '' ]
                );
            } catch ( \Exception $e ) {
                // Log error but don't fail the submission
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Money Quiz Email Error: ' . $e->getMessage() );
                }
            }
        }
        
        // Send admin notification if configured
        if ( get_option( 'money_quiz_send_admin_notification', true ) ) {
            try {
                $this->email_service->send_admin_notification( $result, $user_data );
            } catch ( \Exception $e ) {
                // Log error but don't fail the submission
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Money Quiz Admin Email Error: ' . $e->getMessage() );
                }
            }
        }
    }
    
    /**
     * Prepare response data
     * 
     * @param array $result Quiz result
     * @return array
     */
    private function prepare_response_data( array $result ): array {
        $response = [
            'success' => true,
            'message' => __( 'Thank you for completing the quiz!', 'money-quiz' ),
            'result_id' => $result['id'] ?? 0,
            'archetype' => null,
            'score' => $result['score'] ?? 0,
            'redirect_url' => null,
        ];
        
        // Add archetype data if available
        if ( ! empty( $result['archetype'] ) ) {
            $response['archetype'] = [
                'id' => $result['archetype']->id ?? 0,
                'name' => $result['archetype']->name ?? '',
                'description' => $result['archetype']->description ?? '',
                'color' => $result['archetype']->color ?? '#000000',
                'icon' => $result['archetype']->icon ?? '',
            ];
        }
        
        // Check for redirect URL
        $redirect_url = get_option( 'money_quiz_redirect_url' );
        if ( $redirect_url ) {
            $response['redirect_url'] = add_query_arg( [
                'result_id' => $result['id'] ?? 0,
                'archetype' => $result['archetype']->slug ?? '',
            ], $redirect_url );
        }
        
        // Add custom result page content if configured
        if ( get_option( 'money_quiz_show_result_inline', true ) ) {
            $response['html'] = $this->render_result_html( $result );
        }
        
        // Allow filtering of response
        return apply_filters( 'money_quiz_submission_response', $response, $result );
    }
    
    /**
     * Render result HTML
     * 
     * @param array $result Quiz result
     * @return string
     */
    private function render_result_html( array $result ): string {
        ob_start();
        
        // Check for custom template
        $template = locate_template( 'money-quiz/result.php' );
        
        if ( ! $template ) {
            $template = MONEY_QUIZ_PLUGIN_DIR . 'templates/result.php';
        }
        
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            // Default result HTML
            ?>
            <div class="money-quiz-result">
                <h3><?php _e( 'Your Results', 'money-quiz' ); ?></h3>
                
                <?php if ( ! empty( $result['archetype'] ) ) : ?>
                    <div class="result-archetype">
                        <h4><?php echo esc_html( $result['archetype']->name ); ?></h4>
                        <p><?php echo wp_kses_post( $result['archetype']->description ); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="result-score">
                    <p><?php printf( __( 'Your score: %s%%', 'money-quiz' ), esc_html( $result['score'] ) ); ?></p>
                </div>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
}