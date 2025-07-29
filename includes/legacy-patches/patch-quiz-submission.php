<?php
/**
 * Legacy Patch: Quiz Submission Security
 * 
 * Patches the quiz submission process to add security
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

// Override the original mq_questions_func if it exists
if ( ! function_exists( 'mq_questions_func_patched' ) ) {
    
    /**
     * Patched version of quiz display function
     */
    function mq_questions_func_patched( $atts ) {
        // Use modern shortcode manager if available
        if ( class_exists( '\MoneyQuiz\Frontend\ShortcodeManager' ) ) {
            $shortcode_manager = new \MoneyQuiz\Frontend\ShortcodeManager();
            return $shortcode_manager->render_quiz( $atts );
        }
        
        // Fall back to original with security enhancements
        ob_start();
        
        // Add security token to form
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add CSRF token to quiz form
            var $form = $('#money-quiz-form, form[name="quiz_form"]');
            if ($form.length && !$form.find('input[name="_mq_csrf_token"]').length) {
                <?php if ( class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) : ?>
                    <?php $csrf = new \MoneyQuiz\Security\CsrfManager(); ?>
                    $form.append('<?php echo $csrf->get_token_field( 'quiz_submission' ); ?>');
                <?php endif; ?>
                $form.append('<?php wp_nonce_field( 'mq_quiz_submit', '_wpnonce', false ); ?>');
            }
            
            // Intercept form submission
            $form.on('submit', function(e) {
                var formData = $(this).serialize();
                
                // Add security headers
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-Token': $('input[name="_mq_csrf_token"]').val()
                    }
                });
            });
        });
        </script>
        <?php
        
        // Call original function if it exists
        if ( function_exists( 'mq_questions_func_original' ) ) {
            mq_questions_func_original( $atts );
        }
        
        return ob_get_clean();
    }
}

// Patch quiz processing AJAX handler
if ( ! function_exists( 'mq_process_quiz_ajax_patched' ) ) {
    
    /**
     * Patched AJAX handler for quiz processing
     */
    function mq_process_quiz_ajax_patched() {
        // Security checks
        if ( ! mq_verify_security( 'mq_quiz_submit' ) ) {
            wp_send_json_error( [
                'message' => __( 'Security verification failed', 'money-quiz' )
            ], 403 );
        }
        
        // Sanitize all input
        $data = mq_sanitize_input( $_POST, [
            'answers' => function( $answers ) {
                $clean = [];
                if ( is_array( $answers ) ) {
                    foreach ( $answers as $q_id => $answer ) {
                        $clean[ absint( $q_id ) ] = sanitize_text_field( $answer );
                    }
                }
                return $clean;
            },
            'quiz_id' => 'number',
            'email' => 'email',
            'name' => 'text',
            'phone' => 'text',
            'company' => 'text'
        ] );
        
        // Validate required fields
        if ( empty( $data['answers'] ) ) {
            wp_send_json_error( [
                'message' => __( 'Please answer all questions', 'money-quiz' )
            ] );
        }
        
        // Use modern handler if available
        if ( class_exists( '\MoneyQuiz\Frontend\AjaxHandler' ) ) {
            $ajax_handler = new \MoneyQuiz\Frontend\AjaxHandler();
            $ajax_handler->handle_submission();
            return;
        }
        
        // Process with original function if available
        if ( function_exists( 'mq_process_quiz_ajax_original' ) ) {
            // Temporarily set sanitized data
            $_POST = $data;
            mq_process_quiz_ajax_original();
        } else {
            wp_send_json_error( [
                'message' => __( 'Quiz processing function not found', 'money-quiz' )
            ] );
        }
    }
}

// Patch save prospect function
if ( ! function_exists( 'mq_save_prospect_patched' ) ) {
    
    /**
     * Patched version of save prospect function
     */
    function mq_save_prospect_patched( $data ) {
        global $wpdb;
        
        // Use modern repository if available
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            try {
                $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
                $prospect_repo = $container->get( 'prospect_repository' );
                return $prospect_repo->create( $data );
            } catch ( \Exception $e ) {
                // Fall through to legacy
            }
        }
        
        // Sanitize data
        $clean_data = mq_sanitize_input( $data, [
            'email' => 'email',
            'first_name' => 'text',
            'last_name' => 'text',
            'phone' => 'text',
            'company' => 'text',
            'quiz_id' => 'number',
            'archetype_id' => 'number',
            'score' => 'float'
        ] );
        
        // Validate email
        if ( ! is_email( $clean_data['email'] ) ) {
            return false;
        }
        
        // Check for duplicate
        $existing = mq_safe_get_var(
            "SELECT id FROM {$wpdb->prefix}mq_prospects WHERE email = %s",
            [ $clean_data['email'] ]
        );
        
        if ( $existing ) {
            // Update existing
            return $wpdb->update(
                $wpdb->prefix . 'mq_prospects',
                [
                    'last_quiz_date' => current_time( 'mysql' ),
                    'quiz_count' => [ 'raw' => 'quiz_count + 1' ]
                ],
                [ 'id' => $existing ],
                [ '%s' ],
                [ '%d' ]
            );
        }
        
        // Insert new prospect
        return $wpdb->insert(
            $wpdb->prefix . 'mq_prospects',
            [
                'email' => $clean_data['email'],
                'first_name' => $clean_data['first_name'] ?? '',
                'last_name' => $clean_data['last_name'] ?? '',
                'phone' => $clean_data['phone'] ?? '',
                'company' => $clean_data['company'] ?? '',
                'created_at' => current_time( 'mysql' ),
                'last_quiz_date' => current_time( 'mysql' ),
                'quiz_count' => 1
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
        );
    }
}

// Apply patches by replacing original functions
add_action( 'init', function() {
    // Rename original functions if they exist
    if ( function_exists( 'mq_questions_func' ) && ! function_exists( 'mq_questions_func_original' ) ) {
        eval( 'function mq_questions_func_original( $atts ) { 
            return mq_questions_func( $atts ); 
        }' );
    }
    
    if ( function_exists( 'mq_process_quiz_ajax' ) && ! function_exists( 'mq_process_quiz_ajax_original' ) ) {
        eval( 'function mq_process_quiz_ajax_original() { 
            return mq_process_quiz_ajax(); 
        }' );
    }
    
    if ( function_exists( 'mq_save_prospect' ) && ! function_exists( 'mq_save_prospect_original' ) ) {
        eval( 'function mq_save_prospect_original( $data ) { 
            return mq_save_prospect( $data ); 
        }' );
    }
    
    // Replace AJAX actions
    remove_action( 'wp_ajax_mq_process_quiz', 'mq_process_quiz_ajax' );
    remove_action( 'wp_ajax_nopriv_mq_process_quiz', 'mq_process_quiz_ajax' );
    
    add_action( 'wp_ajax_mq_process_quiz', 'mq_process_quiz_ajax_patched' );
    add_action( 'wp_ajax_nopriv_mq_process_quiz', 'mq_process_quiz_ajax_patched' );
    
}, 20 ); // Run after plugin loads