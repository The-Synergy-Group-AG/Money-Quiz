<?php
/**
 * CSRF Protection Middleware
 *
 * Validates CSRF tokens for state-changing requests.
 *
 * @package MoneyQuiz\Security\Middleware
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Middleware;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\EnhancedNonceManager;
use WP_REST_Request;
use WP_REST_Response;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * CSRF middleware class.
 *
 * @since 7.0.0
 */
class CSRFMiddleware extends SecurityMiddleware {
    
    /**
     * Nonce manager.
     *
     * @var EnhancedNonceManager
     */
    private EnhancedNonceManager $nonce_manager;
    
    /**
     * Middleware priority.
     *
     * @var int
     */
    protected int $priority = 10;
    
    /**
     * Safe HTTP methods.
     *
     * @var array
     */
    private array $safe_methods = ['GET', 'HEAD', 'OPTIONS'];
    
    /**
     * Routes exempt from CSRF.
     *
     * @var array
     */
    private array $exempt_routes = [
        '/money-quiz/v1/public/*',
        '/money-quiz/v1/webhook/*'
    ];
    
    /**
     * Constructor.
     *
     * @param Logger               $logger        Logger instance.
     * @param EnhancedNonceManager $nonce_manager Nonce manager.
     */
    public function __construct(Logger $logger, EnhancedNonceManager $nonce_manager) {
        parent::__construct($logger);
        $this->nonce_manager = $nonce_manager;
    }
    
    /**
     * Process CSRF validation.
     *
     * @param WP_REST_Request $request The request.
     * @param callable        $next    Next middleware.
     * @return WP_REST_Response Response.
     */
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response {
        // Skip safe methods
        if (in_array($request->get_method(), $this->safe_methods, true)) {
            return $next($request);
        }
        
        // Skip exempt routes
        if ($this->is_exempt_route($request->get_route())) {
            return $next($request);
        }
        
        // Get CSRF token
        $token = $this->get_csrf_token($request);
        if (!$token) {
            return $this->error_response(
                __('CSRF token missing.', 'money-quiz'),
                403,
                ['csrf_error' => 'missing_token']
            );
        }
        
        // Get action for verification
        $action = $this->get_csrf_action($request);
        
        // Verify token
        if (!$this->nonce_manager->verify($token, $action)) {
            $this->log_security_event('csrf_validation_failed', [
                'route' => $request->get_route(),
                'method' => $request->get_method(),
                'action' => $action,
                'ip' => $this->get_client_ip()
            ], 'warning');
            
            return $this->error_response(
                __('CSRF validation failed.', 'money-quiz'),
                403,
                ['csrf_error' => 'invalid_token']
            );
        }
        
        // Additional validation for critical actions
        if ($this->is_critical_action($action)) {
            if (!$this->validate_referrer($request)) {
                return $this->error_response(
                    __('Referrer validation failed.', 'money-quiz'),
                    403,
                    ['csrf_error' => 'invalid_referrer']
                );
            }
        }
        
        // Log successful validation
        $this->log_security_event('csrf_validation_success', [
            'action' => $action,
            'route' => $request->get_route()
        ]);
        
        return $next($request);
    }
    
    /**
     * Get CSRF token from request.
     *
     * @param WP_REST_Request $request The request.
     * @return string|null CSRF token.
     */
    private function get_csrf_token(WP_REST_Request $request): ?string {
        // Check header first (preferred)
        $token = $request->get_header('X-Money-Quiz-Nonce');
        if ($token) {
            return $token;
        }
        
        // Check body parameter
        $token = $request->get_param('_wpnonce');
        if ($token) {
            return $token;
        }
        
        // Check meta parameter (for backward compatibility)
        $token = $request->get_param('_money_quiz_nonce');
        if ($token) {
            return $token;
        }
        
        return null;
    }
    
    /**
     * Get CSRF action for request.
     *
     * @param WP_REST_Request $request The request.
     * @return string CSRF action.
     */
    private function get_csrf_action(WP_REST_Request $request): string {
        // Use route and method as action
        $route = str_replace('/', '_', trim($request->get_route(), '/'));
        $method = strtolower($request->get_method());
        
        return "money_quiz_{$method}_{$route}";
    }
    
    /**
     * Check if route is exempt from CSRF.
     *
     * @param string $route Route to check.
     * @return bool True if exempt.
     */
    private function is_exempt_route(string $route): bool {
        foreach ($this->exempt_routes as $pattern) {
            if ($this->match_route($route, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Match route against pattern.
     *
     * @param string $route   Route to check.
     * @param string $pattern Pattern to match.
     * @return bool True if matches.
     */
    private function match_route(string $route, string $pattern): bool {
        $regex = str_replace(
            ['*', '/'],
            ['.*', '\/'],
            $pattern
        );
        
        return preg_match('/^' . $regex . '$/', $route) === 1;
    }
    
    /**
     * Check if action is critical.
     *
     * @param string $action Action name.
     * @return bool True if critical.
     */
    private function is_critical_action(string $action): bool {
        $critical_patterns = [
            'delete_',
            'reset_',
            'export_',
            'change_settings',
            'update_security'
        ];
        
        foreach ($critical_patterns as $pattern) {
            if (strpos($action, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate referrer header.
     *
     * @param WP_REST_Request $request The request.
     * @return bool True if valid.
     */
    private function validate_referrer(WP_REST_Request $request): bool {
        $referrer = $request->get_header('Referer');
        if (!$referrer) {
            return false;
        }
        
        $site_url = site_url();
        return strpos($referrer, $site_url) === 0;
    }
    
    /**
     * Add CSRF exempt route.
     *
     * @param string $pattern Route pattern.
     */
    public function add_exempt_route(string $pattern): void {
        $this->exempt_routes[] = $pattern;
    }
}