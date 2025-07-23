<?php
/**
 * Middleware Stack Manager
 *
 * Manages the execution of security middleware.
 *
 * @package MoneyQuiz\Security\Middleware
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Middleware;

use MoneyQuiz\Core\Logging\Logger;
use WP_REST_Request;
use WP_REST_Response;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Middleware stack class.
 *
 * @since 7.0.0
 */
class MiddlewareStack {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Registered middleware.
     *
     * @var array<SecurityMiddleware>
     */
    private array $middleware = [];
    
    /**
     * Middleware sorted by priority.
     *
     * @var bool
     */
    private bool $sorted = false;
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Add middleware to stack.
     *
     * @param SecurityMiddleware $middleware Middleware instance.
     * @return self
     */
    public function add(SecurityMiddleware $middleware): self {
        $this->middleware[] = $middleware;
        $this->sorted = false;
        
        $this->logger->debug('Middleware added to stack', [
            'middleware' => get_class($middleware),
            'priority' => $middleware->get_priority()
        ]);
        
        return $this;
    }
    
    /**
     * Remove middleware from stack.
     *
     * @param string $class_name Middleware class name.
     * @return self
     */
    public function remove(string $class_name): self {
        $this->middleware = array_filter(
            $this->middleware,
            fn($m) => get_class($m) !== $class_name
        );
        
        return $this;
    }
    
    /**
     * Process request through middleware stack.
     *
     * This method orchestrates the execution of all registered middleware in priority order.
     * It builds a chain of closures where each middleware wraps the next, creating a 
     * Russian doll pattern. The request flows through each middleware in order, and the
     * response flows back in reverse order.
     *
     * @since 7.0.0
     *
     * @param WP_REST_Request $request  The incoming REST request to process.
     * @param callable        $handler  The final request handler (typically the endpoint controller).
     * 
     * @return WP_REST_Response The response after processing through all middleware.
     * 
     * @throws \Exception If any middleware throws an exception during processing.
     *
     * @example
     * ```php
     * $stack = new MiddlewareStack($logger);
     * $stack->add(new AuthenticationMiddleware($logger, $auth));
     * $stack->add(new AuthorizationMiddleware($logger, $authz));
     * 
     * $response = $stack->process($request, function($req) {
     *     return new WP_REST_Response(['status' => 'ok']);
     * });
     * ```
     */
    public function process(WP_REST_Request $request, callable $handler): WP_REST_Response {
        // Sort middleware by priority if not already sorted
        // Lower priority numbers execute first (e.g., 5 before 10)
        if (!$this->sorted) {
            $this->sort_middleware();
        }
        
        // Start with the final handler as the innermost layer
        $next = $handler;
        
        // Build the middleware chain in reverse order
        // This ensures that middleware[0] executes first and middleware[n] executes last
        // Example: If we have [Auth, CSRF, RateLimit], we build: RateLimit(CSRF(Auth(handler)))
        for ($i = count($this->middleware) - 1; $i >= 0; $i--) {
            $middleware = $this->middleware[$i];
            
            // Allow middleware to opt-out of processing certain requests
            // This improves performance by skipping unnecessary middleware
            if (!$middleware->should_process($request)) {
                continue;
            }
            
            // Wrap the current $next handler with this middleware
            // Each middleware receives the next handler in the chain
            $next = $this->create_middleware_closure($middleware, $next);
        }
        
        // Execute the complete middleware chain
        // The request will flow through each middleware in order
        try {
            return $next($request);
        } catch (\Exception $e) {
            // Log the exception with full context for debugging
            $this->logger->error('Middleware execution failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a generic error response to avoid leaking sensitive information
            return new WP_REST_Response([
                'code' => 'middleware_error',
                'message' => __('An error occurred processing your request.', 'money-quiz')
            ], 500);
        }
    }
    
    /**
     * Create middleware closure.
     *
     * @param SecurityMiddleware $middleware Middleware instance.
     * @param callable           $next       Next handler.
     * @return callable Middleware closure.
     */
    private function create_middleware_closure(SecurityMiddleware $middleware, callable $next): callable {
        return function(WP_REST_Request $request) use ($middleware, $next) {
            $start_time = microtime(true);
            
            // Execute middleware
            $response = $middleware->process($request, $next);
            
            // Log execution time
            $execution_time = (microtime(true) - $start_time) * 1000;
            if ($execution_time > 100) { // Log if over 100ms
                $this->logger->warning('Slow middleware execution', [
                    'middleware' => get_class($middleware),
                    'execution_time_ms' => $execution_time
                ]);
            }
            
            return $response;
        };
    }
    
    /**
     * Sort middleware by priority.
     */
    private function sort_middleware(): void {
        usort(
            $this->middleware,
            fn($a, $b) => $a->get_priority() <=> $b->get_priority()
        );
        
        $this->sorted = true;
        
        $this->logger->debug('Middleware stack sorted', [
            'order' => array_map(
                fn($m) => [
                    'class' => get_class($m),
                    'priority' => $m->get_priority()
                ],
                $this->middleware
            )
        ]);
    }
    
    /**
     * Get all registered middleware.
     *
     * @return array<SecurityMiddleware>
     */
    public function get_middleware(): array {
        if (!$this->sorted) {
            $this->sort_middleware();
        }
        
        return $this->middleware;
    }
    
    /**
     * Clear all middleware.
     *
     * @return self
     */
    public function clear(): self {
        $this->middleware = [];
        $this->sorted = false;
        
        return $this;
    }
    
    /**
     * Check if middleware is registered.
     *
     * @param string $class_name Middleware class name.
     * @return bool True if registered.
     */
    public function has(string $class_name): bool {
        foreach ($this->middleware as $m) {
            if (get_class($m) === $class_name) {
                return true;
            }
        }
        
        return false;
    }
}