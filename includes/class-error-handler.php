<?php
/**
 * Error Handler for Money Quiz Safe Wrapper
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Error Handler Class
 */
class MoneyQuiz_Error_Handler {
    
    /**
     * @var array Collected errors
     */
    private $errors = array();
    
    /**
     * @var bool Whether to display errors
     */
    private $display_errors;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->display_errors = defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
    
    /**
     * Register error handlers
     */
    public function register() {
        set_error_handler( array( $this, 'handle_error' ) );
        set_exception_handler( array( $this, 'handle_exception' ) );
        register_shutdown_function( array( $this, 'handle_shutdown' ) );
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_error( $errno, $errstr, $errfile, $errline ) {
        // Don't handle suppressed errors
        if ( ! ( error_reporting() & $errno ) ) {
            return false;
        }
        
        // Check if error is from Money Quiz plugin
        if ( strpos( $errfile, 'money-quiz' ) === false ) {
            return false; // Let WordPress handle it
        }
        
        $error = array(
            'type' => $this->get_error_type( $errno ),
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'time' => current_time( 'mysql' ),
        );
        
        $this->errors[] = $error;
        
        // Log error
        $this->log_error( $error );
        
        // Convert to exception for better handling
        throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handle_exception( $exception ) {
        $error = array(
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'time' => current_time( 'mysql' ),
        );
        
        $this->errors[] = $error;
        $this->log_error( $error );
        
        // Display friendly error message
        if ( is_admin() ) {
            add_action( 'admin_notices', function() use ( $exception ) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e( 'Money Quiz Error:', 'money-quiz' ); ?></strong>
                        <?php
                        if ( $this->display_errors ) {
                            echo esc_html( $exception->getMessage() );
                            echo '<br><small>' . esc_html( $exception->getFile() . ':' . $exception->getLine() ) . '</small>';
                        } else {
                            _e( 'An error occurred. Please check the error logs.', 'money-quiz' );
                        }
                        ?>
                    </p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ( null !== $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) {
            // Check if it's from Money Quiz
            if ( strpos( $error['file'], 'money-quiz' ) !== false ) {
                $this->handle_exception( new ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ) );
            }
        }
    }
    
    /**
     * Get error type string
     */
    private function get_error_type( $errno ) {
        $types = array(
            E_ERROR => 'Fatal Error',
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
            E_USER_DEPRECATED => 'User Deprecated',
        );
        
        return isset( $types[ $errno ] ) ? $types[ $errno ] : 'Unknown Error';
    }
    
    /**
     * Log error
     */
    private function log_error( $error ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf(
                '[Money Quiz Safe Wrapper] %s: %s in %s:%d',
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            ) );
            
            if ( isset( $error['trace'] ) ) {
                error_log( '[Money Quiz Safe Wrapper] Stack trace: ' . $error['trace'] );
            }
        }
    }
    
    /**
     * Get collected errors
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Clear errors
     */
    public function clear_errors() {
        $this->errors = array();
    }
    
    /**
     * Safe file loading
     */
    public static function safe_require( $file ) {
        if ( ! file_exists( $file ) ) {
            throw new Exception( sprintf(
                __( 'Required file not found: %s', 'money-quiz' ),
                $file
            ) );
        }
        
        if ( ! is_readable( $file ) ) {
            throw new Exception( sprintf(
                __( 'Required file not readable: %s', 'money-quiz' ),
                $file
            ) );
        }
        
        // Check file size (prevent loading huge files)
        $size = filesize( $file );
        if ( $size > 5 * 1024 * 1024 ) { // 5MB limit
            throw new Exception( sprintf(
                __( 'File too large: %s (%s bytes)', 'money-quiz' ),
                $file,
                $size
            ) );
        }
        
        try {
            require_once $file;
            return true;
        } catch ( Exception $e ) {
            throw new Exception( sprintf(
                __( 'Failed to load file %s: %s', 'money-quiz' ),
                $file,
                $e->getMessage()
            ) );
        }
    }
}