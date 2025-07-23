<?php
/**
 * Authentication Interface
 *
 * Defines the contract for authentication services.
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
 * Authentication interface.
 *
 * This interface allows for multiple authentication implementations
 * (session, JWT, API key, etc.) while maintaining a consistent API.
 *
 * @since 7.0.0
 */
interface AuthenticationInterface {
    
    /**
     * Authenticate a request.
     *
     * @param WP_REST_Request $request The request to authenticate.
     * @return AuthenticationResult Authentication result.
     */
    public function authenticate(WP_REST_Request $request): AuthenticationResult;
    
    /**
     * Register authentication provider.
     *
     * @param AuthenticationProviderInterface $provider Provider instance.
     * @param int                            $priority Provider priority.
     * @return void
     */
    public function register_provider(AuthenticationProviderInterface $provider, int $priority = 10): void;
    
    /**
     * Remove authentication provider.
     *
     * @param string $provider_class Provider class name.
     * @return void
     */
    public function remove_provider(string $provider_class): void;
    
    /**
     * Get registered providers.
     *
     * @return array<AuthenticationProviderInterface> Array of providers.
     */
    public function get_providers(): array;
}