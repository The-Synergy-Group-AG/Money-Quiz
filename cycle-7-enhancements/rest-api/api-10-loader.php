<?php
/**
 * REST API Loader
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

// Load API components
require_once __DIR__ . '/api-1-core-router.php';
require_once __DIR__ . '/api-2-endpoint-base.php';
require_once __DIR__ . '/api-3-quiz-endpoints.php';
require_once __DIR__ . '/api-4-result-endpoints.php';
require_once __DIR__ . '/api-5-user-endpoints.php';
require_once __DIR__ . '/api-6-auth-middleware.php';
require_once __DIR__ . '/api-7-validation-middleware.php';
require_once __DIR__ . '/api-8-response-formatter.php';
require_once __DIR__ . '/api-9-error-handler.php';

/**
 * API Manager
 */
class ApiManager {
    
    private static $instance = null;
    private $router;
    private $endpoints = [];
    
    private function __construct() {
        $this->router = ApiRouter::getInstance();
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
     * Initialize API
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Initialize components
        ApiRouter::init();
        ErrorHandler::init();
        
        // Register middlewares
        $instance->registerMiddlewares();
        
        // Register endpoints
        $instance->registerEndpoints();
        
        // Add API documentation
        add_action('rest_api_init', [$instance, 'registerDocumentation']);
        
        // Add discovery endpoint
        add_action('rest_api_init', [$instance, 'registerDiscovery']);
    }
    
    /**
     * Register middlewares
     */
    private function registerMiddlewares() {
        // Authentication middleware
        $this->router->addMiddleware([AuthMiddleware::class, 'verify'], 5);
        
        // Validation middleware
        $this->router->addMiddleware([ValidationMiddleware::class, 'validate'], 10);
        
        // Custom middlewares
        $this->router->addMiddleware(function($request) {
            // Add request ID
            $request->set_header('X-Request-ID', wp_generate_uuid4());
            return true;
        }, 1);
    }
    
    /**
     * Register endpoints
     */
    private function registerEndpoints() {
        $this->endpoints = [
            'quiz' => new QuizEndpoints(),
            'result' => new ResultEndpoints(),
            'user' => new UserEndpoints()
        ];
        
        // Allow extensions
        $this->endpoints = apply_filters('money_quiz_api_endpoints', $this->endpoints);
    }
    
    /**
     * Register API documentation
     */
    public function registerDocumentation() {
        register_rest_route('money-quiz/v1', '/docs', [
            'methods' => 'GET',
            'callback' => [$this, 'getDocumentation'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Register discovery endpoint
     */
    public function registerDiscovery() {
        register_rest_route('money-quiz/v1', '/discover', [
            'methods' => 'GET',
            'callback' => [$this, 'discover'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Get API documentation
     */
    public function getDocumentation() {
        $docs = [
            'name' => 'Money Quiz REST API',
            'version' => '1.0.0',
            'description' => 'RESTful API for Money Quiz WordPress plugin',
            'base_url' => rest_url('money-quiz/v1'),
            'authentication' => [
                'cookie' => 'WordPress cookie with nonce',
                'api_key' => 'X-API-Key header',
                'jwt' => 'Authorization: Bearer <token>'
            ],
            'endpoints' => $this->getEndpointDocs()
        ];
        
        return ResponseFormatter::success($docs);
    }
    
    /**
     * Discover API capabilities
     */
    public function discover() {
        $capabilities = [
            'version' => '1.0.0',
            'features' => [
                'authentication' => true,
                'rate_limiting' => function_exists('money_quiz_check_rate_limit'),
                'webhooks' => class_exists('MoneyQuiz\Webhooks\WebhookManager'),
                'analytics' => class_exists('MoneyQuiz\Analytics\AnalyticsManager')
            ],
            'endpoints' => array_keys($this->endpoints),
            'links' => [
                'documentation' => rest_url('money-quiz/v1/docs'),
                'health' => rest_url('money-quiz/v1/health')
            ]
        ];
        
        return ResponseFormatter::success($capabilities);
    }
    
    /**
     * Get endpoint documentation
     */
    private function getEndpointDocs() {
        return [
            'quizzes' => [
                'GET /quizzes' => 'List quizzes',
                'POST /quizzes' => 'Create quiz',
                'GET /quizzes/{id}' => 'Get quiz',
                'PUT /quizzes/{id}' => 'Update quiz',
                'DELETE /quizzes/{id}' => 'Delete quiz'
            ],
            'results' => [
                'GET /results' => 'List results',
                'POST /results' => 'Submit result',
                'GET /results/{id}' => 'Get result'
            ],
            'users' => [
                'GET /users/me' => 'Get current user',
                'GET /users/{id}/stats' => 'Get user statistics',
                'GET /users/leaderboard' => 'Get leaderboard'
            ]
        ];
    }
}

// Initialize API
add_action('plugins_loaded', [ApiManager::class, 'init'], 5);