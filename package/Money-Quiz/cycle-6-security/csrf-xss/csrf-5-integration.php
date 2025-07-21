<?php
/**
 * CSRF WordPress Integration
 * 
 * @package MoneyQuiz\Security\CSRF
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\CSRF;

/**
 * WordPress Integration
 */
class CsrfWordPressIntegration implements CsrfConstants {
    
    private $generator;
    private $validator;
    
    public function __construct(CsrfTokenGenerator $generator, CsrfTokenValidator $validator) {
        $this->generator = $generator;
        $this->validator = $validator;
    }
    
    /**
     * Initialize hooks
     */
    public function init() {
        // Add meta tag to head
        add_action('wp_head', [$this, 'addMetaTag']);
        add_action('admin_head', [$this, 'addMetaTag']);
        
        // Enqueue JavaScript
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        
        // Ajax handlers
        add_action('wp_ajax_money_quiz_refresh_csrf', [$this, 'ajaxRefreshToken']);
        add_action('wp_ajax_nopriv_money_quiz_refresh_csrf', [$this, 'ajaxRefreshToken']);
        
        // Form submission verification
        add_action('admin_post_money_quiz_submit', [$this, 'verifyFormSubmission'], 1);
        add_action('admin_post_nopriv_money_quiz_submit', [$this, 'verifyFormSubmission'], 1);
        
        // REST API protection
        add_filter('rest_authentication_errors', [$this, 'verifyRestRequest']);
    }
    
    /**
     * Add CSRF meta tag
     */
    public function addMetaTag() {
        echo $this->generator->getMetaTag('money_quiz_rest_api');
    }
    
    /**
     * Enqueue JavaScript
     */
    public function enqueueScripts() {
        $token = $this->generator->generate('money_quiz_rest_api');
        
        wp_localize_script('money-quiz-frontend', 'moneyQuizCSRF', [
            'token' => $token,
            'headerName' => self::HEADER_NAME,
            'refreshUrl' => admin_url('admin-ajax.php')
        ]);
        
        // Auto-add CSRF to jQuery AJAX
        wp_add_inline_script('jquery', "
            jQuery(document).ajaxSend(function(event, xhr, settings) {
                if (settings.url.indexOf('money-quiz') !== -1) {
                    xhr.setRequestHeader('" . self::HEADER_NAME . "', moneyQuizCSRF.token);
                }
            });
        ");
    }
    
    /**
     * Verify form submission
     */
    public function verifyFormSubmission() {
        try {
            $this->validator->validateRequest('POST');
        } catch (CsrfException $e) {
            wp_die(
                'Security verification failed. Please refresh and try again.',
                'Security Error',
                ['response' => 403]
            );
        }
    }
    
    /**
     * Verify REST request
     */
    public function verifyRestRequest($result) {
        // Skip if already has error
        if (!empty($result)) {
            return $result;
        }
        
        // Only check modifying requests
        if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            return $result;
        }
        
        // Only check Money Quiz endpoints
        $route = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($route, '/money-quiz/') === false) {
            return $result;
        }
        
        try {
            $this->validator->validateRequest($_SERVER['REQUEST_METHOD']);
        } catch (CsrfException $e) {
            return new \WP_Error(
                'rest_csrf_failed',
                $e->getMessage(),
                ['status' => 403]
            );
        }
        
        return $result;
    }
    
    /**
     * Ajax refresh token
     */
    public function ajaxRefreshToken() {
        check_ajax_referer('money_quiz_ajax', 'nonce');
        
        $token = $this->generator->generate('money_quiz_rest_api');
        
        wp_send_json_success(['token' => $token]);
    }
}