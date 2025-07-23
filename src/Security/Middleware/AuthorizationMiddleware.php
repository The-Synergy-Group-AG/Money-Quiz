<?php
/**
 * Authorization Middleware
 *
 * Handles permission checks for authenticated users.
 *
 * @package MoneyQuiz\Security\Middleware
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Middleware;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Authorization\Authorizer;
use WP_REST_Request;
use WP_REST_Response;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Authorization middleware class.
 *
 * @since 7.0.0
 */
class AuthorizationMiddleware extends SecurityMiddleware {
    
    /**
     * Authorizer instance.
     *
     * @var Authorizer
     */
    private Authorizer $authorizer;
    
    /**
     * Middleware priority.
     *
     * @var int
     */
    protected int $priority = 30;
    
    /**
     * Route permissions map.
     *
     * @var array
     */
    private array $route_permissions = [
        'POST:/money-quiz/v1/quiz' => 'create_quiz',
        'PUT:/money-quiz/v1/quiz/*' => 'edit_quiz',
        'DELETE:/money-quiz/v1/quiz/*' => 'delete_quiz',
        'GET:/money-quiz/v1/results/export' => 'export_results',
        'PUT:/money-quiz/v1/settings/*' => 'manage_settings'
    ];
    
    /**
     * Constructor.
     *
     * @param Logger     $logger     Logger instance.
     * @param Authorizer $authorizer Authorizer instance.
     */
    public function __construct(Logger $logger, Authorizer $authorizer) {
        parent::__construct($logger);
        $this->authorizer = $authorizer;
    }
    
    /**
     * Process authorization.
     *
     * @param WP_REST_Request $request The request.
     * @param callable        $next    Next middleware.
     * @return WP_REST_Response Response.
     */
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response {
        // Skip if no authenticated user
        $user_id = $request->get_param('_authenticated_user_id');
        if (!$user_id) {
            return $next($request);
        }
        
        // Get required permission
        $permission = $this->get_required_permission($request);
        if (!$permission) {
            return $next($request);
        }
        
        // Check authorization
        $resource_id = $this->get_resource_id($request);
        if (!$this->authorizer->can($user_id, $permission, $resource_id)) {
            $this->log_security_event('authorization_denied', [
                'user_id' => $user_id,
                'permission' => $permission,
                'resource_id' => $resource_id,
                'route' => $request->get_route()
            ], 'warning');
            
            return $this->error_response(
                __('You do not have permission to perform this action.', 'money-quiz'),
                403,
                ['required_permission' => $permission]
            );
        }
        
        // Log successful authorization
        $this->log_security_event('authorization_granted', [
            'user_id' => $user_id,
            'permission' => $permission,
            'resource_id' => $resource_id
        ]);
        
        // Store authorization info
        $request->set_param('_authorized_permission', $permission);
        $request->set_param('_authorized_resource_id', $resource_id);
        
        return $next($request);
    }
    
    /**
     * Get required permission for request.
     *
     * Determines the permission needed for a request using a three-tier system:
     * 1. Exact route match (e.g., 'POST:/money-quiz/v1/quiz')
     * 2. Pattern match with wildcards (e.g., 'PUT:/money-quiz/v1/quiz/*')
     * 3. Route options metadata (for custom endpoints)
     * 
     * This flexible approach allows both hardcoded and dynamic permission mapping.
     *
     * @since 7.0.0
     * @access private
     *
     * @param WP_REST_Request $request The incoming REST request.
     * 
     * @return string|null The required permission name, or null if no permission required.
     * 
     * @example
     * ```php
     * // Route: DELETE /money-quiz/v1/quiz/123
     * // Matches pattern: 'DELETE:/money-quiz/v1/quiz/*'
     * // Returns: 'delete_quiz'
     * ```
     */
    private function get_required_permission(WP_REST_Request $request): ?string {
        $method = $request->get_method();
        $route = $request->get_route();
        
        // Check exact match first for best performance
        // Format: 'METHOD:exact/route/path'
        $key = "{$method}:{$route}";
        if (isset($this->route_permissions[$key])) {
            return $this->route_permissions[$key];
        }
        
        // Check pattern match for wildcard routes
        // Patterns can use * to match path segments
        foreach ($this->route_permissions as $pattern => $permission) {
            if ($this->match_route_pattern($method, $route, $pattern)) {
                return $permission;
            }
        }
        
        // Check route options for custom endpoints
        // Allows endpoints to define permissions via register_rest_route
        $route_options = $request->get_route_options();
        return $route_options['money_quiz_permission'] ?? null;
    }
    
    /**
     * Match route against pattern.
     *
     * Implements wildcard pattern matching for flexible route authorization.
     * Patterns use * to match any single path segment, allowing rules like:
     * - 'PUT:/money-quiz/v1/quiz/*' matches 'PUT:/money-quiz/v1/quiz/123'
     * - 'GET:/money-quiz/v1/*/export' matches 'GET:/money-quiz/v1/results/export'
     *
     * @since 7.0.0
     * @access private
     *
     * @param string $method  The HTTP method (GET, POST, PUT, DELETE).
     * @param string $route   The actual route path to check.
     * @param string $pattern The pattern to match against (METHOD:path/with/*).
     * 
     * @return bool True if the route matches the pattern, false otherwise.
     */
    private function match_route_pattern(string $method, string $route, string $pattern): bool {
        // Split pattern into method and path
        // Pattern format: 'METHOD:path/pattern'
        if (strpos($pattern, ':') === false) {
            return false;
        }
        
        [$pattern_method, $pattern_path] = explode(':', $pattern, 2);
        
        // Method must match exactly (case-sensitive)
        if ($pattern_method !== $method) {
            return false;
        }
        
        // Convert wildcard pattern to regex
        // * matches any single path segment (not including /)
        // / is escaped for regex
        $regex = str_replace(
            ['*', '/'],
            ['[^/]+', '\\/'],  // * becomes [^/]+, / becomes \/
            $pattern_path
        );
        
        // Anchor pattern to match entire route
        return preg_match('/^' . $regex . '$/', $route) === 1;
    }
    
    /**
     * Get resource ID from request.
     *
     * Extracts the resource ID for authorization checks. This enables
     * resource-based permissions like "users can only edit their own quizzes".
     * 
     * The method tries multiple strategies:
     * 1. Common parameter names (id, quiz_id, result_id, user_id)
     * 2. Numeric ID extracted from the route path
     * 
     * This flexible approach handles various API design patterns.
     *
     * @since 7.0.0
     * @access private
     *
     * @param WP_REST_Request $request The request containing the resource reference.
     * 
     * @return mixed The resource ID (typically integer), or null if not found.
     *               Used by policies to check resource ownership.
     * 
     * @example
     * ```php
     * // From route: /money-quiz/v1/quiz/123/edit
     * // Returns: 123
     * 
     * // From parameter: ?quiz_id=456
     * // Returns: 456
     * ```
     */
    private function get_resource_id(WP_REST_Request $request) {
        // Try common parameter names first
        // These are checked in order of likelihood
        $id_params = ['id', 'quiz_id', 'result_id', 'user_id'];
        
        foreach ($id_params as $param) {
            $value = $request->get_param($param);
            if ($value !== null) {
                return $value;
            }
        }
        
        // Extract numeric ID from route path
        // Matches patterns like /quiz/123 or /results/456/export
        $route = $request->get_route();
        if (preg_match('/\/(\d+)(?:\/|$)/', $route, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Register route permission.
     *
     * @param string $pattern    Route pattern (METHOD:path).
     * @param string $permission Required permission.
     */
    public function register_route_permission(string $pattern, string $permission): void {
        $this->route_permissions[$pattern] = $permission;
    }
}