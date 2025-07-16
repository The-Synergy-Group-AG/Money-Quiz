<?php
/**
 * REST API Error Handler
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Error Handler
 */
class ErrorHandler {
    
    /**
     * Initialize error handling
     */
    public static function init() {
        add_filter('rest_request_after_callbacks', [__CLASS__, 'handleErrors'], 10, 3);
        add_action('rest_api_init', [__CLASS__, 'registerErrorHandlers']);
    }
    
    /**
     * Handle API errors
     */
    public static function handleErrors($response, $handler, $request) {
        if (is_wp_error($response)) {
            return self::formatError($response);
        }
        
        return $response;
    }
    
    /**
     * Register error handlers
     */
    public static function registerErrorHandlers() {
        set_exception_handler([__CLASS__, 'handleException']);
        set_error_handler([__CLASS__, 'handleError']);
    }
    
    /**
     * Format error response
     */
    public static function formatError($error) {
        $code = $error->get_error_code();
        $message = $error->get_error_message();
        $data = $error->get_error_data();
        $status = isset($data['status']) ? $data['status'] : 500;
        
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'status' => $status
            ],
            'timestamp' => current_time('c')
        ];
        
        // Add debug info in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['error']['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        
        // Log error
        self::logError($error);
        
        $wp_response = rest_ensure_response($response);
        $wp_response->set_status($status);
        
        return $wp_response;
    }
    
    /**
     * Handle exceptions
     */
    public static function handleException($exception) {
        $error = new \WP_Error(
            'api_exception',
            $exception->getMessage(),
            ['status' => 500]
        );
        
        wp_send_json_error(self::formatError($error)->data, 500);
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = new \WP_Error(
            'api_error',
            $message,
            [
                'status' => 500,
                'severity' => $severity,
                'file' => $file,
                'line' => $line
            ]
        );
        
        self::logError($error);
        
        return true;
    }
    
    /**
     * Log error
     */
    private static function logError($error) {
        if (function_exists('money_quiz_log_error')) {
            money_quiz_log_error('api_error', [
                'code' => $error->get_error_code(),
                'message' => $error->get_error_message(),
                'data' => $error->get_error_data(),
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'endpoint' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        }
    }
    
    /**
     * Common error responses
     */
    public static function unauthorized($message = 'Unauthorized') {
        return new \WP_Error('unauthorized', $message, ['status' => 401]);
    }
    
    public static function forbidden($message = 'Forbidden') {
        return new \WP_Error('forbidden', $message, ['status' => 403]);
    }
    
    public static function notFound($message = 'Not found') {
        return new \WP_Error('not_found', $message, ['status' => 404]);
    }
    
    public static function badRequest($message = 'Bad request') {
        return new \WP_Error('bad_request', $message, ['status' => 400]);
    }
    
    public static function serverError($message = 'Internal server error') {
        return new \WP_Error('server_error', $message, ['status' => 500]);
    }
}