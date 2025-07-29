<?php
/**
 * CSRF Protection Trait
 *
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Traits;

use MoneyQuiz\Security\CsrfManager;

/**
 * Trait to add CSRF protection to classes
 */
trait CsrfProtectionTrait {
    
    /**
     * @var CsrfManager|null
     */
    private ?CsrfManager $csrf_manager = null;
    
    /**
     * Get CSRF manager instance
     * 
     * @return CsrfManager
     */
    protected function get_csrf_manager(): CsrfManager {
        if ( null === $this->csrf_manager ) {
            $this->csrf_manager = new CsrfManager();
        }
        
        return $this->csrf_manager;
    }
    
    /**
     * Verify CSRF token for current request
     * 
     * @param string $action Action identifier
     * @return bool
     */
    protected function verify_csrf( string $action = 'default' ): bool {
        try {
            $this->get_csrf_manager()->verify_request( $action );
            return true;
        } catch ( \Exception $e ) {
            $this->handle_csrf_failure( $e );
            return false;
        }
    }
    
    /**
     * Generate CSRF token field
     * 
     * @param string $action Action identifier
     * @return string
     */
    protected function csrf_field( string $action = 'default' ): string {
        return $this->get_csrf_manager()->get_token_field( $action );
    }
    
    /**
     * Add CSRF token to data array
     * 
     * @param array  $data Data array
     * @param string $action Action identifier
     * @return array
     */
    protected function add_csrf_to_data( array $data, string $action = 'default' ): array {
        $data['_mq_csrf_token'] = $this->get_csrf_manager()->generate_token( $action );
        return $data;
    }
    
    /**
     * Handle CSRF verification failure
     * 
     * @param \Exception $e Exception
     * @return void
     */
    protected function handle_csrf_failure( \Exception $e ): void {
        if ( wp_doing_ajax() ) {
            wp_send_json_error( [
                'message' => __( 'Security verification failed. Please refresh and try again.', 'money-quiz' ),
                'code' => 'csrf_failed',
            ], 403 );
        } else {
            wp_die( 
                esc_html__( 'Security verification failed. Please go back and try again.', 'money-quiz' ),
                esc_html__( 'Security Error', 'money-quiz' ),
                [ 'response' => 403, 'back_link' => true ]
            );
        }
    }
}