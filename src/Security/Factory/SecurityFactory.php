<?php
/**
 * Security Factory
 *
 * Factory for creating security components with proper dependencies.
 *
 * @package MoneyQuiz\Security\Factory
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Factory;

use MoneyQuiz\Core\Container;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Contracts\EncryptionInterface;
use MoneyQuiz\Security\Contracts\AuthenticationInterface;
use MoneyQuiz\Security\Contracts\AuthorizationInterface;
use MoneyQuiz\Security\Encryption\Encryptor;
use MoneyQuiz\Security\Encryption\KeyManager;
use MoneyQuiz\Security\Authentication\Authenticator;
use MoneyQuiz\Security\Authorization\Authorizer;
use MoneyQuiz\Security\Middleware\MiddlewareStack;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Security factory class.
 *
 * This factory centralizes the creation of security components,
 * ensuring consistent configuration and dependency injection.
 * It serves as a single point for security component instantiation.
 *
 * @since 7.0.0
 */
class SecurityFactory {
    
    /**
     * Container instance.
     *
     * @var Container
     */
    private Container $container;
    
    /**
     * Constructor.
     *
     * @param Container $container Dependency injection container.
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Create encryption service.
     *
     * Creates a fully configured encryption service with key management.
     * The service implements AES-256-GCM encryption with automatic key rotation.
     *
     * @return EncryptionInterface Configured encryption service.
     */
    public function create_encryption_service(): EncryptionInterface {
        // Get or create logger
        $logger = $this->container->get(Logger::class);
        
        // Create key manager first (dependency of encryptor)
        $key_manager = new KeyManager($logger);
        
        // Create and return encryptor
        return new Encryptor($logger, $key_manager);
    }
    
    /**
     * Create authentication service.
     *
     * Creates the authentication service with all registered providers.
     * Supports multiple authentication methods (session, JWT, API key, etc.).
     *
     * @return AuthenticationInterface Configured authentication service.
     */
    public function create_authentication_service(): AuthenticationInterface {
        $logger = $this->container->get(Logger::class);
        
        return new Authenticator($logger);
    }
    
    /**
     * Create authorization service.
     *
     * Creates the authorization service with default policies.
     * Implements RBAC with support for custom policies.
     *
     * @return AuthorizationInterface Configured authorization service.
     */
    public function create_authorization_service(): AuthorizationInterface {
        $logger = $this->container->get(Logger::class);
        
        return new Authorizer($logger);
    }
    
    /**
     * Create middleware stack.
     *
     * Creates an empty middleware stack ready for middleware registration.
     * Middleware will be executed in priority order.
     *
     * @return MiddlewareStack Configured middleware stack.
     */
    public function create_middleware_stack(): MiddlewareStack {
        $logger = $this->container->get(Logger::class);
        
        return new MiddlewareStack($logger);
    }
    
    /**
     * Create complete security system.
     *
     * Creates and wires together all security components for a complete
     * security implementation. This is the recommended way to set up
     * the security system.
     *
     * @return array{
     *     encryption: EncryptionInterface,
     *     authentication: AuthenticationInterface,
     *     authorization: AuthorizationInterface,
     *     middleware: MiddlewareStack
     * } Array of configured security components.
     */
    public function create_security_system(): array {
        // Create core services
        $encryption = $this->create_encryption_service();
        $authentication = $this->create_authentication_service();
        $authorization = $this->create_authorization_service();
        $middleware = $this->create_middleware_stack();
        
        // Register services in container for dependency injection
        $this->container->set(EncryptionInterface::class, $encryption);
        $this->container->set(AuthenticationInterface::class, $authentication);
        $this->container->set(AuthorizationInterface::class, $authorization);
        $this->container->set(MiddlewareStack::class, $middleware);
        
        // Also register concrete implementations for backward compatibility
        $this->container->set(Encryptor::class, $encryption);
        $this->container->set(Authenticator::class, $authentication);
        $this->container->set(Authorizer::class, $authorization);
        
        return [
            'encryption' => $encryption,
            'authentication' => $authentication,
            'authorization' => $authorization,
            'middleware' => $middleware
        ];
    }
}