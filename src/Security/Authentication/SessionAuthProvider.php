<?php
/**
 * Session Authentication Provider
 *
 * Handles authentication via WordPress sessions.
 *
 * @package MoneyQuiz\Security\Authentication
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Authentication;

use MoneyQuiz\Security\Contracts\AuthenticationProviderInterface;
use WP_REST_Request;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Session authentication provider class.
 *
 * @since 7.0.0
 */
class SessionAuthProvider implements AuthenticationProviderInterface {
    
    /**
     * Check if provider can handle request.
     *
     * @param WP_REST_Request $request The request.
     * @return bool True if can handle.
     */
    public function can_handle(WP_REST_Request $request): bool {
        // Support if cookie is present
        return isset($_COOKIE[LOGGED_IN_COOKIE]);
    }
    
    /**
     * Authenticate via session.
     *
     * @param WP_REST_Request $request The request.
     * @return AuthenticationResult Authentication result.
     */
    public function authenticate(WP_REST_Request $request): AuthenticationResult {
        // Check if user is logged in via session
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            // Verify session is still valid
            if ($this->verify_session($user_id)) {
                return new AuthenticationResult(true, $user_id, 'session');
            }
        }
        
        return new AuthenticationResult(
            false,
            0,
            'session',
            __('Session authentication failed.', 'money-quiz')
        );
    }
    
    /**
     * Verify session is valid.
     *
     * @param int $user_id User ID.
     * @return bool True if valid.
     */
    private function verify_session(int $user_id): bool {
        // Verify user still exists and is not blocked
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        // Check if user is blocked
        if (get_user_meta($user_id, 'money_quiz_blocked', true) === 'yes') {
            return false;
        }
        
        // Verify session token
        $session_token = wp_get_session_token();
        if (empty($session_token)) {
            return false;
        }
        
        // Verify token is still valid
        $manager = \WP_Session_Tokens::get_instance($user_id);
        return $manager->verify($session_token);
    }
    
    /**
     * Get provider name.
     *
     * @return string Provider name.
     */
    public function get_name(): string {
        return 'WordPress Session';
    }
}