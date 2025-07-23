<?php
/**
 * Authentication Middleware
 *
 * Handles user authentication for requests.
 *
 * @package MoneyQuiz\Security\Middleware
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Middleware;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Authentication\Authenticator;
use WP_REST_Request;
use WP_REST_Response;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Authentication middleware class.
 *
 * @since 7.0.0
 */
class AuthenticationMiddleware extends SecurityMiddleware {
    
    /**
     * Authenticator instance.
     *
     * @var Authenticator
     */
    private Authenticator $authenticator;
    
    /**
     * Routes that require authentication.
     *
     * @var array
     */
    private array $protected_routes = [
        '/money-quiz/v1/admin/*',
        '/money-quiz/v1/quiz/create',
        '/money-quiz/v1/quiz/update/*',
        '/money-quiz/v1/quiz/delete/*',
        '/money-quiz/v1/results/export',
        '/money-quiz/v1/settings/*'
    ];
    
    /**
     *.
     *
     * @var int
     */
    protected int $priority = 20;
    
    /**
     * Constructor.
     *
     * @param Logger        $logger        Logger instance.
     * @param Authenticator $authenticator Authenticator instance.
     */
    public function __construct(Logger $logger, Authenticator $authenticator) {
        parent::__construct($logger);
        $this->authenticator = $authenticator;
    }
    
    /**
     * Maximum authentication attempts before temporary lockout.
     *
     * @var int
     */
    private const MAX_AUTH_ATTEMPTS = 5;
    
    /**
     * Lockout duration in seconds.
     *
     * @var int
     */
    private const LOCKOUT_DURATION = 900; // 15 minutes
    
    /**
     * Process authentication.
     *
     * @param WP_REST_Request $request The request.
     * @param callable        $next    Next middleware.
     * @return WP_REST_Response Response.
     */
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response {
        // Check if route requires authentication
        if (!$this->requires_authentication($request)) {
            return $next($request);
        }
        
        // Edge case: Check for authentication bypass attempts
        if ($this->detect_bypass_attempt($request)) {
            $this->log_security_event('auth_bypass_attempt', [
                'ip' => $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'],
                'route' => $request->get_route()
            ], 'critical');
            
            return $this->error_response(
                __('Security violation detected.', 'money-quiz'),
                403
            );
        }
        
        // Check for brute force protection
        $client_identifier = $this->get_client_identifier($request);
        if ($this->is_locked_out($client_identifier)) {
            return $this->error_response(
                __('Too many failed attempts. Please try again later.', 'money-quiz'),
                429,
                ['retry_after' => $this->get_lockout_remaining($client_identifier)]
            );
        }
        
        // Attempt authentication
        $auth_result = $this->authenticator->authenticate($request);
        
        if (!$auth_result->is_authenticated()) {
            // Track failed attempt for brute force protection
            $this->record_failed_attempt($client_identifier);
            
            // Log with appropriate severity based on attempt count
            $attempt_count = $this->get_attempt_count($client_identifier);
            $log_level = $attempt_count >= 3 ? 'warning' : 'info';
            
            $this->log_security_event('authentication_failed', [
                'method' => $auth_result->get_method(),
                'route' => $request->get_route(),
                'attempt_count' => $attempt_count,
                'client_id' => $client_identifier
            ], $log_level);
            
            return $this->error_response(
                $auth_result->get_error_message(),
                401,
                ['auth_method' => $auth_result->get_method()]
            );
        }
        
        // Clear failed attempts on successful authentication
        $this->clear_failed_attempts($client_identifier);
        
        // Edge case: Validate user ID is positive integer
        $user_id = $auth_result->get_user_id();
        if (!is_int($user_id) || $user_id <= 0) {
            $this->log_security_event('invalid_user_id', [
                'user_id' => $user_id,
                'type' => gettype($user_id)
            ], 'error');
            
            return $this->error_response(
                __('Invalid authentication response.', 'money-quiz'),
                500
            );
        }
        
        // Edge case: Verify user still exists and is not blocked
        $user = get_userdata($user_id);
        if (!$user || !$user->exists()) {
            $this->log_security_event('authenticated_user_not_found', [
                'user_id' => $user_id
            ], 'error');
            
            return $this->error_response(
                __('Authentication failed.', 'money-quiz'),
                401
            );
        }
        
        // Check if user is blocked/suspended
        if (get_user_meta($user_id, 'money_quiz_blocked', true) === 'yes') {
            $this->log_security_event('blocked_user_attempt', [
                'user_id' => $user_id
            ], 'warning');
            
            return $this->error_response(
                __('Your account has been suspended.', 'money-quiz'),
                403
            );
        }
        
        // Set authenticated user
        wp_set_current_user($user_id);
        
        // Log successful authentication with session tracking
        $session_id = wp_generate_password(32, false);
        $this->log_security_event('authentication_success', [
            'user_id' => $user_id,
            'method' => $auth_result->get_method(),
            'route' => $request->get_route(),
            'session_id' => $session_id,
            'user_agent' => $request->get_header('User-Agent')
        ]);
        
        // Store auth info in request with session tracking
        $request->set_param('_authenticated_user_id', $user_id);
        $request->set_param('_authentication_method', $auth_result->get_method());
        $request->set_param('_session_id', $session_id);
        
        return $next($request);
    }
    
    /**
     * Check if route requires authentication.
     *
     * @param WP_REST_Request $request The request.
     * @return bool True if authentication required.
     */
    private function requires_authentication(WP_REST_Request $request): bool {
        $route = $request->get_route();
        
        foreach ($this->protected_routes as $pattern) {
            if ($this->match_route($route, $pattern)) {
                return true;
            }
        }
        
        // Check if route has custom auth requirement
        $route_options = $request->get_route_options();
        return $route_options['money_quiz_auth_required'] ?? false;
    }
    
    /**
     * Match route against pattern.
     *
     * @param string $route   Route to check.
     * @param string $pattern Pattern to match.
     * @return bool True if matches.
     */
    private function match_route(string $route, string $pattern): bool {
        // Convert pattern to regex
        $regex = str_replace(
            ['*', '/'],
            ['.*', '\/'],
            $pattern
        );
        
        return preg_match('/^' . $regex . '$/', $route) === 1;
    }
    
    /**
     * Add protected route.
     *
     * @param string $pattern Route pattern.
     */
    public function add_protected_route(string $pattern): void {
        $this->protected_routes[] = $pattern;
    }
    
    /**
     * Detect authentication bypass attempts.
     *
     * @param WP_REST_Request $request The request.
     * @return bool True if bypass attempt detected.
     */
    private function detect_bypass_attempt(WP_REST_Request $request): bool {
        // Check for null byte injection in auth headers
        $headers_to_check = ['Authorization', 'X-WP-Nonce', 'Cookie'];
        foreach ($headers_to_check as $header) {
            $value = $request->get_header($header);
            if ($value && strpos($value, "\0") !== false) {
                return true;
            }
        }
        
        // Check for authentication parameter pollution
        $auth_params = ['_wpnonce', 'auth_token', 'api_key'];
        foreach ($auth_params as $param) {
            $value = $request->get_param($param);
            if (is_array($value)) {
                // Parameter should not be an array
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get client identifier for rate limiting.
     *
     * @param WP_REST_Request $request The request.
     * @return string Client identifier.
     */
    private function get_client_identifier(WP_REST_Request $request): string {
        // Use combination of IP and User-Agent for better accuracy
        $ip = $request->get_header('X-Forwarded-For') ?: $_SERVER['REMOTE_ADDR'];
        $user_agent = substr($request->get_header('User-Agent') ?? '', 0, 100);
        
        return hash('sha256', $ip . '|' . $user_agent);
    }
    
    /**
     * Check if client is locked out.
     *
     * @param string $client_id Client identifier.
     * @return bool True if locked out.
     */
    private function is_locked_out(string $client_id): bool {
        $lockout_key = 'money_quiz_auth_lockout_' . $client_id;
        $lockout_time = get_transient($lockout_key);
        
        return $lockout_time !== false;
    }
    
    /**
     * Get lockout time remaining.
     *
     * @param string $client_id Client identifier.
     * @return int Seconds remaining.
     */
    private function get_lockout_remaining(string $client_id): int {
        $lockout_key = 'money_quiz_auth_lockout_' . $client_id;
        $lockout_time = get_transient($lockout_key);
        
        if ($lockout_time === false) {
            return 0;
        }
        
        return max(0, $lockout_time - time());
    }
    
    /**
     * Record failed authentication attempt.
     *
     * @param string $client_id Client identifier.
     */
    private function record_failed_attempt(string $client_id): void {
        $attempts_key = 'money_quiz_auth_attempts_' . $client_id;
        $attempts = get_transient($attempts_key) ?: [];
        
        // Add current attempt
        $attempts[] = time();
        
        // Keep only recent attempts (last hour)
        $one_hour_ago = time() - 3600;
        $attempts = array_filter($attempts, fn($time) => $time > $one_hour_ago);
        
        // Check if we need to lock out
        if (count($attempts) >= self::MAX_AUTH_ATTEMPTS) {
            $lockout_key = 'money_quiz_auth_lockout_' . $client_id;
            set_transient($lockout_key, time() + self::LOCKOUT_DURATION, self::LOCKOUT_DURATION);
        }
        
        // Save attempts
        set_transient($attempts_key, $attempts, 3600);
    }
    
    /**
     * Get authentication attempt count.
     *
     * @param string $client_id Client identifier.
     * @return int Attempt count.
     */
    private function get_attempt_count(string $client_id): int {
        $attempts_key = 'money_quiz_auth_attempts_' . $client_id;
        $attempts = get_transient($attempts_key) ?: [];
        
        // Count recent attempts
        $one_hour_ago = time() - 3600;
        $recent_attempts = array_filter($attempts, fn($time) => $time > $one_hour_ago);
        
        return count($recent_attempts);
    }
    
    /**
     * Clear failed attempts after successful authentication.
     *
     * @param string $client_id Client identifier.
     */
    private function clear_failed_attempts(string $client_id): void {
        delete_transient('money_quiz_auth_attempts_' . $client_id);
        delete_transient('money_quiz_auth_lockout_' . $client_id);
    }
}