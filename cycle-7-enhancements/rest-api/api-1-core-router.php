<?php
/**
 * REST API Core Router
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Core API Router
 */
class ApiRouter {
    
    private static $instance = null;
    private $namespace = 'money-quiz/v1';
    private $routes = [];
    private $middlewares = [];
    
    private function __construct() {
        $this->registerCoreHooks();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize router
     */
    public static function init() {
        $instance = self::getInstance();
        add_action('rest_api_init', [$instance, 'registerRoutes']);
    }
    
    /**
     * Register core hooks
     */
    private function registerCoreHooks() {
        // Add custom headers
        add_filter('rest_pre_serve_request', [$this, 'addCustomHeaders'], 10, 4);
        
        // Handle preflight requests
        add_action('rest_api_init', [$this, 'handlePreflight'], 5);
        
        // Add versioning support
        add_filter('rest_url', [$this, 'addVersioning'], 10, 4);
    }
    
    /**
     * Register a route
     */
    public function addRoute($path, $methods, $callback, $options = []) {
        $this->routes[] = [
            'path' => $path,
            'args' => [
                'methods' => $methods,
                'callback' => $callback,
                'permission_callback' => $options['permission_callback'] ?? '__return_true',
                'args' => $options['args'] ?? []
            ]
        ];
    }
    
    /**
     * Register middleware
     */
    public function addMiddleware($middleware, $priority = 10) {
        $this->middlewares[] = [
            'middleware' => $middleware,
            'priority' => $priority
        ];
    }
    
    /**
     * Register all routes
     */
    public function registerRoutes() {
        // Sort middlewares by priority
        usort($this->middlewares, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        // Register each route
        foreach ($this->routes as $route) {
            $callback = $this->wrapWithMiddleware($route['args']['callback']);
            $route['args']['callback'] = $callback;
            
            register_rest_route($this->namespace, $route['path'], $route['args']);
        }
        
        // Register health check endpoint
        register_rest_route($this->namespace, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'healthCheck'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Wrap callback with middleware
     */
    private function wrapWithMiddleware($callback) {
        return function($request) use ($callback) {
            // Apply middlewares
            foreach ($this->middlewares as $middleware) {
                $result = call_user_func($middleware['middleware'], $request);
                
                if (is_wp_error($result)) {
                    return $result;
                }
                
                if ($result === false) {
                    return new \WP_Error(
                        'middleware_rejected',
                        'Request rejected by middleware',
                        ['status' => 403]
                    );
                }
            }
            
            // Call original callback
            return call_user_func($callback, $request);
        };
    }
    
    /**
     * Add custom headers
     */
    public function addCustomHeaders($served, $result, $request, $server) {
        header('X-API-Version: 1.0.0');
        header('X-RateLimit-Limit: ' . apply_filters('money_quiz_api_rate_limit', 60));
        
        return $served;
    }
    
    /**
     * Handle preflight requests
     */
    public function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: ' . apply_filters('money_quiz_api_cors_origin', '*'));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
            exit;
        }
    }
    
    /**
     * Add versioning support
     */
    public function addVersioning($url, $path, $blog_id, $scheme) {
        if (strpos($path, 'money-quiz/') === 0) {
            $version = get_option('money_quiz_api_version', 'v1');
            $url = str_replace('/money-quiz/', "/money-quiz/{$version}/", $url);
        }
        return $url;
    }
    
    /**
     * Health check endpoint
     */
    public function healthCheck($request) {
        return rest_ensure_response([
            'status' => 'healthy',
            'version' => '1.0.0',
            'timestamp' => current_time('c')
        ]);
    }
    
    /**
     * Get namespace
     */
    public function getNamespace() {
        return $this->namespace;
    }
}