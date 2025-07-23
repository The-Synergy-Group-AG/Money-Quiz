<?php
/**
 * Main Authenticator
 *
 * Handles user authentication using multiple providers.
 *
 * @package MoneyQuiz\Security\Authentication
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Authentication;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Contracts\AuthenticationInterface;
use MoneyQuiz\Security\Contracts\AuthenticationProviderInterface;
use WP_REST_Request;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Authenticator class.
 *
 * @since 7.0.0
 */
class Authenticator implements AuthenticationInterface {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Authentication providers.
     *
     * @var array<AuthenticationProviderInterface>
     */
    private array $providers = [];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Register authentication provider.
     *
     * @param AuthenticationProviderInterface $provider Provider instance.
     * @param int                            $priority Priority (lower first).
     */
    public function register_provider(AuthenticationProviderInterface $provider, int $priority = 10): void {
        $this->providers[] = [
            'provider' => $provider,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort($this->providers, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }
    
    /**
     * Authenticate request.
     *
     * @param WP_REST_Request $request The request.
     * @return AuthenticationResult Authentication result.
     */
    public function authenticate(WP_REST_Request $request): AuthenticationResult {
        $this->logger->debug('Starting authentication', [
            'route' => $request->get_route(),
            'method' => $request->get_method()
        ]);
        
        // Try each provider
        foreach ($this->providers as $item) {
            $provider = $item['provider'];
            
            if (!$provider->can_handle($request)) {
                continue;
            }
            
            $result = $provider->authenticate($request);
            
            if ($result->is_authenticated()) {
                $this->logger->info('Authentication successful', [
                    'provider' => $provider->get_name(),
                    'user_id' => $result->get_user_id(),
                    'method' => $result->get_method()
                ]);
                
                return $result;
            }
            
            // Log failed attempt
            $this->logger->debug('Authentication failed', [
                'provider' => $provider->get_name(),
                'error' => $result->get_error_message()
            ]);
        }
        
        // No provider authenticated
        return new AuthenticationResult(
            false,
            0,
            'none',
            __('Authentication required.', 'money-quiz')
        );
    }
    
    /**
     * Remove authentication provider.
     *
     * @param string $provider_class Provider class name.
     * @return void
     */
    public function remove_provider(string $provider_class): void {
        $this->providers = array_filter(
            $this->providers,
            fn($item) => get_class($item['provider']) !== $provider_class
        );
        
        // Re-sort
        usort($this->providers, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }
    
    /**
     * Get registered providers.
     *
     * @return array<AuthenticationProviderInterface> Array of providers.
     */
    public function get_providers(): array {
        return array_column($this->providers, 'provider');
    }
    
    /**
     * Verify user credentials.
     *
     * @param string $username Username or email.
     * @param string $password Password.
     * @return AuthenticationResult Authentication result.
     */
    public function verify_credentials(string $username, string $password): AuthenticationResult {
        // Try to authenticate user
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            $this->logger->warning('Credential verification failed', [
                'username' => $username,
                'error' => $user->get_error_message()
            ]);
            
            return new AuthenticationResult(
                false,
                0,
                'credentials',
                $user->get_error_message()
            );
        }
        
        return new AuthenticationResult(
            true,
            $user->ID,
            'credentials'
        );
    }
    
    /**
     * Get current authenticated user.
     *
     * @return int User ID or 0 if not authenticated.
     */
    public function get_current_user(): int {
        return get_current_user_id();
    }
    
    /**
     * Check if user is authenticated.
     *
     * @return bool True if authenticated.
     */
    public function is_authenticated(): bool {
        return is_user_logged_in();
    }
    
    /**
     * Logout current user.
     */
    public function logout(): void {
        $user_id = $this->get_current_user();
        
        if ($user_id > 0) {
            $this->logger->info('User logout', ['user_id' => $user_id]);
            wp_logout();
        }
    }
}

/**
 * Authentication result class.
 */
class AuthenticationResult {
    
    /**
     * Authentication status.
     *
     * @var bool
     */
    private bool $authenticated;
    
    /**
     * User ID.
     *
     * @var int
     */
    private int $user_id;
    
    /**
     * Authentication method.
     *
     * @var string
     */
    private string $method;
    
    /**
     * Error message.
     *
     * @var string
     */
    private string $error_message;
    
    /**
     * Constructor.
     *
     * @param bool   $authenticated Authentication status.
     * @param int    $user_id       User ID.
     * @param string $method        Authentication method.
     * @param string $error_message Error message.
     */
    public function __construct(
        bool $authenticated,
        int $user_id = 0,
        string $method = '',
        string $error_message = ''
    ) {
        $this->authenticated = $authenticated;
        $this->user_id = $user_id;
        $this->method = $method;
        $this->error_message = $error_message;
    }
    
    public function is_authenticated(): bool {
        return $this->authenticated;
    }
    
    public function get_user_id(): int {
        return $this->user_id;
    }
    
    public function get_method(): string {
        return $this->method;
    }
    
    public function get_error_message(): string {
        return $this->error_message;
    }
}