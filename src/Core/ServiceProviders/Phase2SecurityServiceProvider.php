<?php
/**
 * Phase 2 Security Service Provider
 *
 * Registers all Phase 2 security components.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Config\ConfigManager;

// Middleware
use MoneyQuiz\Security\Middleware\MiddlewareStack;
use MoneyQuiz\Security\Middleware\AuthenticationMiddleware;
use MoneyQuiz\Security\Middleware\AuthorizationMiddleware;
use MoneyQuiz\Security\Middleware\InputValidationMiddleware;
use MoneyQuiz\Security\Middleware\CSRFMiddleware;

// Authentication
use MoneyQuiz\Security\Authentication\Authenticator;
use MoneyQuiz\Security\Authentication\SessionAuthProvider;

// Authorization
use MoneyQuiz\Security\Authorization\Authorizer;

// Encryption
use MoneyQuiz\Security\Encryption\Encryptor;
use MoneyQuiz\Security\Encryption\KeyManager;

// File Security
use MoneyQuiz\Security\FileSystem\FileValidator;
use MoneyQuiz\Security\FileSystem\MimeTypeChecker;

// From Phase 1
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Security\EnhancedNonceManager;
use MoneyQuiz\Security\ConfigurableRateLimiter;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Phase 2 security service provider class.
 *
 * @since 7.0.0
 */
class Phase2SecurityServiceProvider extends AbstractServiceProvider {
    
    /**
     * Register services.
     *
     * @since 7.0.0
     *
     * @return void
     */
    public function register(): void {
        // Register Middleware Stack
        $this->singleton(
            MiddlewareStack::class,
            function($container) {
                return new MiddlewareStack(
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Authenticator
        $this->singleton(
            Authenticator::class,
            function($container) {
                $authenticator = new Authenticator(
                    $container->get(Logger::class)
                );
                
                // Register session provider
                $authenticator->register_provider(
                    new SessionAuthProvider(),
                    10
                );
                
                return $authenticator;
            }
        );
        
        // Register Authorizer
        $this->singleton(
            Authorizer::class,
            function($container) {
                return new Authorizer(
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Key Manager
        $this->singleton(
            KeyManager::class,
            function($container) {
                return new KeyManager(
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Encryptor
        $this->singleton(
            Encryptor::class,
            function($container) {
                return new Encryptor(
                    $container->get(Logger::class),
                    $container->get(KeyManager::class)
                );
            }
        );
        
        // Register File Validator
        $this->singleton(
            FileValidator::class,
            function($container) {
                return new FileValidator(
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register MIME Type Checker
        $this->singleton(
            MimeTypeChecker::class,
            function($container) {
                return new MimeTypeChecker(
                    $container->get(Logger::class)
                );
            }
        );
    }
    
    /**
     * Bootstrap services.
     *
     * @since 7.0.0
     *
     * @return void
     */
    public function boot(): void {
        // Get middleware stack
        $stack = $this->get(MiddlewareStack::class);
        
        // Add Rate Limiting Middleware (from Phase 1)
        if ($this->container->has(ConfigurableRateLimiter::class)) {
            // We'll create RateLimitMiddleware separately
        }
        
        // Add Input Validation Middleware
        $stack->add(new InputValidationMiddleware(
            $this->get(Logger::class),
            $this->get(InputValidator::class)
        ));
        
        // Add CSRF Middleware
        $stack->add(new CSRFMiddleware(
            $this->get(Logger::class),
            $this->get(EnhancedNonceManager::class)
        ));
        
        // Add Authentication Middleware
        $stack->add(new AuthenticationMiddleware(
            $this->get(Logger::class),
            $this->get(Authenticator::class)
        ));
        
        // Add Authorization Middleware
        $stack->add(new AuthorizationMiddleware(
            $this->get(Logger::class),
            $this->get(Authorizer::class)
        ));
        
        // Hook middleware stack into WordPress REST API
        add_filter('rest_pre_dispatch', [$this, 'process_rest_request'], 10, 3);
        
        // Register file upload handler
        add_filter('wp_handle_upload_prefilter', [$this, 'validate_file_upload']);
        
        // Log Phase 2 initialization
        $this->get(Logger::class)->info('Phase 2 Security initialized', [
            'middleware_count' => count($stack->get_middleware()),
            'auth_providers' => 1,
            'encryption_enabled' => true,
            'file_security_enabled' => true
        ]);
    }
    
    /**
     * Process REST request through middleware.
     *
     * @param mixed            $result  Dispatch result.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request instance.
     * @return mixed Dispatch result.
     */
    public function process_rest_request($result, $server, $request) {
        // Skip if already processed
        if ($result !== null) {
            return $result;
        }
        
        // Skip non-Money Quiz routes
        $route = $request->get_route();
        if (strpos($route, '/money-quiz/') !== 0) {
            return $result;
        }
        
        // Get middleware stack
        $stack = $this->get(MiddlewareStack::class);
        
        // Process through middleware
        try {
            $response = $stack->process($request, function($request) use ($server) {
                // Continue with normal WordPress REST processing
                return $server->dispatch($request);
            });
            
            return $response;
        } catch (\Exception $e) {
            $this->get(Logger::class)->error('Middleware processing failed', [
                'exception' => $e->getMessage(),
                'route' => $route
            ]);
            
            return new \WP_Error(
                'middleware_error',
                __('Request processing failed.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Validate file upload.
     *
     * @param array $file File array.
     * @return array Modified file array.
     */
    public function validate_file_upload(array $file): array {
        // Skip if already has error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $file;
        }
        
        // Validate file
        $validator = $this->get(FileValidator::class);
        $result = $validator->validate($file);
        
        if (!$result->is_valid()) {
            $file['error'] = $result->get_error();
        }
        
        return $file;
    }
}