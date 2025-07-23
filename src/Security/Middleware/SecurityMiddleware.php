<?php
/**
 * Base Security Middleware
 *
 * Abstract base class for all security middleware.
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
 * Abstract security middleware class.
 *
 * @since 7.0.0
 */
abstract class SecurityMiddleware {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    protected Logger $logger;
    
    /**
     * Middleware priority.
     *
     * @var int
     */
    protected int $priority = 10;
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Process the request.
     *
     * @param WP_REST_Request $request  The request.
     * @param callable        $next     Next middleware.
     * @return WP_REST_Response Response.
     */
    abstract public function process(WP_REST_Request $request, callable $next): WP_REST_Response;
    
    /**
     * Get middleware priority.
     *
     * @return int Priority (lower runs first).
     */
    public function get_priority(): int {
        return $this->priority;
    }
    
    /**
     * Check if middleware should run for request.
     *
     * @param WP_REST_Request $request The request.
     * @return bool True if should run.
     */
    public function should_process(WP_REST_Request $request): bool {
        // By default, process all requests
        return true;
    }
    
    /**
     * Log security event.
     *
     * @param string $event   Event name.
     * @param array  $context Context data.
     * @param string $level   Log level.
     */
    protected function log_security_event(string $event, array $context = [], string $level = 'info'): void {
        $context['middleware'] = get_class($this);
        $context['request_id'] = $this->get_request_id();
        
        $this->logger->log($level, "Security event: {$event}", $context);
    }
    
    /**
     * Get or generate request ID.
     *
     * @return string Request ID.
     */
    protected function get_request_id(): string {
        static $request_id;
        
        if (!$request_id) {
            $request_id = wp_generate_uuid4();
        }
        
        return $request_id;
    }
    
    /**
     * Create error response.
     *
     * @param string $message Error message.
     * @param int    $code    HTTP status code.
     * @param array  $data    Additional data.
     * @return WP_REST_Response Error response.
     */
    protected function error_response(string $message, int $code = 403, array $data = []): WP_REST_Response {
        $this->log_security_event('request_blocked', [
            'reason' => $message,
            'code' => $code,
            'data' => $data
        ], 'warning');
        
        return new WP_REST_Response([
            'code' => 'security_error',
            'message' => $message,
            'data' => $data
        ], $code);
    }
    
    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    protected function get_client_ip(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get user identifier for logging.
     *
     * @return string User identifier.
     */
    protected function get_user_identifier(): string {
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            return "user_{$user_id}";
        }
        
        return 'anonymous_' . substr(hash('sha256', $this->get_client_ip()), 0, 8);
    }
}