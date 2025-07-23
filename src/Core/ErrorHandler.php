<?php
/**
 * Centralized Error Handler
 *
 * Manages all error handling and reporting.
 *
 * @package MoneyQuiz\Core
 * @since   7.0.0
 */

namespace MoneyQuiz\Core;

use MoneyQuiz\Core\Logging\Logger;
use Exception;
use Throwable;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Error handler class.
 *
 * @since 7.0.0
 */
class ErrorHandler {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Error levels to handle.
     *
     * @var int
     */
    private int $error_levels;
    
    /**
     * Debug mode.
     *
     * @var bool
     */
    private bool $debug;
    
    /**
     * Registered error handlers.
     *
     * @var array
     */
    private array $handlers = [];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     * @param bool   $debug  Debug mode.
     */
    public function __construct(Logger $logger, bool $debug = false) {
        $this->logger = $logger;
        $this->debug = $debug || (defined('WP_DEBUG') && WP_DEBUG);
        $this->error_levels = E_ALL & ~E_NOTICE & ~E_DEPRECATED;
    }
    
    /**
     * Initialize error handling.
     */
    public function init(): void {
        // Set error handler
        set_error_handler([$this, 'handle_error'], $this->error_levels);
        
        // Set exception handler
        set_exception_handler([$this, 'handle_exception']);
        
        // Register shutdown function
        register_shutdown_function([$this, 'handle_shutdown']);
    }
    
    /**
     * Handle PHP errors.
     *
     * @param int    $severity Error severity.
     * @param string $message  Error message.
     * @param string $file     File where error occurred.
     * @param int    $line     Line number.
     * @return bool True to prevent default handler.
     */
    public function handle_error(int $severity, string $message, string $file, int $line): bool {
        // Check if error reporting is disabled
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        // Convert to exception for consistent handling
        $exception = new \ErrorException($message, 0, $severity, $file, $line);
        
        $this->process_error($exception, $this->severity_to_level($severity));
        
        // Don't execute default PHP error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions.
     *
     * @param Throwable $exception Exception object.
     */
    public function handle_exception(Throwable $exception): void {
        $this->process_error($exception, 'critical');
        
        // Display user-friendly error
        if (!$this->debug) {
            wp_die(
                __('An error occurred. Please try again later.', 'money-quiz'),
                __('Error', 'money-quiz'),
                ['response' => 500]
            );
        }
    }
    
    /**
     * Handle fatal errors on shutdown.
     */
    public function handle_shutdown(): void {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            $this->process_error($exception, 'emergency');
        }
    }
    
    /**
     * Process error through handlers.
     *
     * @param Throwable $exception Exception object.
     * @param string    $level     Log level.
     */
    private function process_error(Throwable $exception, string $level): void {
        // Prepare error data
        $error_data = [
            'message' => $exception->getMessage(),
            'file' => $this->sanitize_path($exception->getFile()),
            'line' => $exception->getLine(),
            'type' => get_class($exception),
            'code' => $exception->getCode(),
            'trace' => $this->debug ? $this->sanitize_trace($exception->getTrace()) : null,
            'request_id' => $this->get_request_id(),
            'user_id' => get_current_user_id(),
            'url' => $this->sanitize_url($_SERVER['REQUEST_URI'] ?? ''),
        ];
        
        // Log error
        $this->logger->log($level, $error_data['message'], $error_data);
        
        // Call registered handlers
        foreach ($this->handlers as $handler) {
            try {
                call_user_func($handler, $exception, $error_data);
            } catch (Throwable $e) {
                // Handler failed, log it
                $this->logger->error('Error handler failed', [
                    'handler' => $this->get_handler_name($handler),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Send to monitoring service
        do_action('money_quiz_error_occurred', $exception, $error_data);
    }
    
    /**
     * Register error handler.
     *
     * @param callable $handler Handler callback.
     * @param string   $name    Handler name.
     */
    public function register_handler(callable $handler, string $name = ''): void {
        $key = $name ?: spl_object_hash((object) $handler);
        $this->handlers[$key] = $handler;
    }
    
    /**
     * Convert PHP error severity to log level.
     *
     * @param int $severity Error severity.
     * @return string Log level.
     */
    private function severity_to_level(int $severity): string {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
                return 'emergency';
                
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return 'critical';
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'warning';
                
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return 'notice';
                
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'info';
                
            default:
                return 'error';
        }
    }
    
    /**
     * Sanitize file path for logging.
     *
     * @param string $path File path.
     * @return string Sanitized path.
     */
    private function sanitize_path(string $path): string {
        $base = ABSPATH;
        if (strpos($path, $base) === 0) {
            return substr($path, strlen($base));
        }
        return basename($path);
    }
    
    /**
     * Sanitize stack trace.
     *
     * @param array $trace Stack trace.
     * @return array Sanitized trace.
     */
    private function sanitize_trace(array $trace): array {
        return array_map(function ($frame) {
            return [
                'file' => isset($frame['file']) ? $this->sanitize_path($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ];
        }, array_slice($trace, 0, 10)); // Limit trace depth
    }
    
    /**
     * Sanitize URL for logging.
     *
     * @param string $url URL to sanitize.
     * @return string Sanitized URL.
     */
    private function sanitize_url(string $url): string {
        $parsed = parse_url($url);
        return $parsed['path'] ?? '/';
    }
    
    /**
     * Get request ID for correlation.
     *
     * @return string Request ID.
     */
    private function get_request_id(): string {
        static $request_id;
        if (!$request_id) {
            $request_id = wp_generate_uuid4();
        }
        return $request_id;
    }
    
    /**
     * Get handler name for logging.
     *
     * @param callable $handler Handler.
     * @return string Handler name.
     */
    private function get_handler_name(callable $handler): string {
        if (is_string($handler)) {
            return $handler;
        }
        if (is_array($handler)) {
            return (is_object($handler[0]) ? get_class($handler[0]) : $handler[0]) . '::' . $handler[1];
        }
        return 'Closure';
    }
}