<?php
/**
 * Authentication Provider Interface
 *
 * Defines the contract for authentication providers.
 *
 * @package MoneyQuiz\Security\Contracts
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Contracts;

use WP_REST_Request;
use MoneyQuiz\Security\Authentication\AuthenticationResult;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Authentication provider interface.
 *
 * Implementations of this interface handle specific authentication
 * methods like session-based auth, JWT tokens, or API keys.
 *
 * @since 7.0.0
 */
interface AuthenticationProviderInterface {
    
    /**
     * Check if this provider can handle the request.
     *
     * @param WP_REST_Request $request The request to check.
     * @return bool True if provider can handle the request.
     */
    public function can_handle(WP_REST_Request $request): bool;
    
    /**
     * Authenticate the request.
     *
     * @param WP_REST_Request $request The request to authenticate.
     * @return AuthenticationResult Authentication result.
     */
    public function authenticate(WP_REST_Request $request): AuthenticationResult;
    
    /**
     * Get provider name.
     *
     * @return string Provider name for logging.
     */
    public function get_name(): string;
}