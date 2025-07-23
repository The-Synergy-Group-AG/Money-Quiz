<?php
/**
 * Error Handler
 *
 * Centralized error handling for the application.
 *
 * @package MoneyQuiz\Core\ErrorHandling
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ErrorHandling;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Domain\Exceptions\EntityException;
use MoneyQuiz\Application\Exceptions\ServiceException;
use WP_Error;
use WP_REST_Response;

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
     * Error mappings.
     *
     * @var array
     */
    private array $error_mappings = [
        EntityException::class => [
            'status' => 400,
            'code' => 'entity_error'
        ],
        ServiceException::class => [
            'status' => 400,
            'code' => 'service_error'
        ],
        \InvalidArgumentException::class => [
            'status' => 400,
            'code' => 'invalid_argument'
        ],
        \RuntimeException::class => [
            'status' => 500,
            'code' => 'runtime_error'
        ]
    ];
    
    /**
     * Development mode flag.
     *
     * @var bool
     */
    private bool $debug_mode;
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init_handlers();
    }
    
    /**
     * Initialize error handlers.
     *
     * @return void
     */
    private function init_handlers(): void {
        // Set custom error handler
        set_error_handler([$this, 'handle_error']);
        
        // Set custom exception handler
        set_exception_handler([$this, 'handle_exception']);
        
        // Register shutdown function
        register_shutdown_function([$this, 'handle_shutdown']);
        
        // Add REST API error filter
        add_filter('rest_request_after_callbacks', [$this, 'handle_rest_errors'], 10, 3);
    }
    
    /**
     * Handle PHP errors.
     *
     * @param int    $errno   Error number.
     * @param string $errstr  Error message.
     * @param string $errfile Error file.
     * @param int    $errline Error line.
     * @return bool True if handled.
     */
    public function handle_error(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Check if error reporting is disabled
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $error_type = $this->get_error_type($errno);
        
        $this->logger->error("PHP {$error_type}", [
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ]);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions.
     *
     * @param \Throwable $exception Exception object.
     * @return void
     */
    public function handle_exception(\Throwable $exception): void {
        $this->logger->error('Uncaught exception', [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->debug_mode ? $exception->getTraceAsString() : null
        ]);
        
        // If in REST API context, return proper response
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $response = $this->format_rest_error($exception);
            wp_send_json($response, $response['status']);
        }
    }
    
    /**
     * Handle fatal errors on shutdown.
     *
     * @return void
     */
    public function handle_shutdown(): void {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->logger->critical('Fatal error', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    }
    
    /**
     * Handle REST API errors.
     *
     * @param WP_REST_Response|WP_Error $response Server response.
     * @param array                     $handler  Route handler.
     * @param \WP_REST_Request          $request  Request object.
     * @return WP_REST_Response|WP_Error Modified response.
     */
    public function handle_rest_errors($response, $handler, $request) {
        if (is_wp_error($response)) {
            $this->logger->warning('REST API error', [
                'code' => $response->get_error_code(),
                'message' => $response->get_error_message(),
                'endpoint' => $request->get_route(),
                'method' => $request->get_method()
            ]);
        }
        
        return $response;
    }
    
    /**
     * Convert exception to WP_Error.
     *
     * @param \Throwable $exception Exception object.
     * @return WP_Error WordPress error object.
     */
    public function exception_to_wp_error(\Throwable $exception): WP_Error {
        $mapping = $this->get_error_mapping(get_class($exception));
        
        $data = ['status' => $mapping['status']];
        
        if ($this->debug_mode) {
            $data['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace()
            ];
        }
        
        return new WP_Error(
            $mapping['code'],
            $exception->getMessage(),
            $data
        );
    }
    
    /**
     * Format exception for REST response.
     *
     * @param \Throwable $exception Exception object.
     * @return array Formatted error response.
     */
    public function format_rest_error(\Throwable $exception): array {
        $mapping = $this->get_error_mapping(get_class($exception));
        
        $response = [
            'code' => $mapping['code'],
            'message' => $exception->getMessage(),
            'data' => [
                'status' => $mapping['status']
            ]
        ];
        
        if ($this->debug_mode) {
            $response['data']['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString())
            ];
        }
        
        return $response;
    }
    
    /**
     * Log exception with context.
     *
     * @param \Throwable $exception Exception object.
     * @param array      $context   Additional context.
     * @return void
     */
    public function log_exception(\Throwable $exception, array $context = []): void {
        $this->logger->error('Exception occurred', array_merge([
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->debug_mode ? $exception->getTraceAsString() : null
        ], $context));
    }
    
    /**
     * Get error type string.
     *
     * @param int $errno Error number.
     * @return string Error type.
     */
    private function get_error_type(int $errno): string {
        $types = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $types[$errno] ?? 'Unknown';
    }
    
    /**
     * Get error mapping for exception class.
     *
     * @param string $exception_class Exception class name.
     * @return array Error mapping.
     */
    private function get_error_mapping(string $exception_class): array {
        // Check direct mapping
        if (isset($this->error_mappings[$exception_class])) {
            return $this->error_mappings[$exception_class];
        }
        
        // Check parent classes
        foreach ($this->error_mappings as $class => $mapping) {
            if (is_subclass_of($exception_class, $class)) {
                return $mapping;
            }
        }
        
        // Default mapping
        return [
            'status' => 500,
            'code' => 'internal_error'
        ];
    }
    
    /**
     * Add custom error mapping.
     *
     * @param string $exception_class Exception class name.
     * @param int    $status         HTTP status code.
     * @param string $code           Error code.
     * @return void
     */
    public function add_error_mapping(string $exception_class, int $status, string $code): void {
        $this->error_mappings[$exception_class] = [
            'status' => $status,
            'code' => $code
        ];
    }
}