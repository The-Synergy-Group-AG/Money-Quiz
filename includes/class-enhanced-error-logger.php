<?php
/**
 * Enhanced Error Logger
 * 
 * Comprehensive error logging and monitoring
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Debug;

class Enhanced_Error_Logger {
    
    /**
     * @var string Log directory
     */
    private $log_dir;
    
    /**
     * @var array Error statistics
     */
    private $error_stats = [];
    
    /**
     * @var array Critical errors that need immediate attention
     */
    private $critical_patterns = [
        'Fatal error',
        'Uncaught Exception',
        'Maximum execution time',
        'Allowed memory size',
        'Class .* not found',
        'Call to undefined function',
        'Cannot redeclare',
        'Headers already sent'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/money-quiz-logs/';
        $this->ensure_log_directory();
        $this->init_error_handlers();
    }
    
    /**
     * Initialize error handlers
     */
    private function init_error_handlers() {
        // Set error handler for non-fatal errors
        set_error_handler( [ $this, 'handle_error' ] );
        
        // Set exception handler
        set_exception_handler( [ $this, 'handle_exception' ] );
        
        // Register shutdown function for fatal errors
        register_shutdown_function( [ $this, 'handle_shutdown' ] );
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_error( $errno, $errstr, $errfile, $errline ) {
        // Check if error reporting is disabled for this error
        if ( ! ( error_reporting() & $errno ) ) {
            return false;
        }
        
        $error_type = $this->get_error_type( $errno );
        
        $error_data = [
            'type' => $error_type,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => current_time( 'mysql' ),
            'url' => $this->get_current_url(),
            'user_id' => get_current_user_id(),
            'backtrace' => $this->get_safe_backtrace()
        ];
        
        // Log the error
        $this->log_error( $error_data );
        
        // Track statistics
        $this->update_stats( $error_type );
        
        // Check if it's critical
        if ( $this->is_critical( $errstr ) ) {
            $this->handle_critical_error( $error_data );
        }
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handle_exception( $exception ) {
        $error_data = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => current_time( 'mysql' ),
            'url' => $this->get_current_url(),
            'user_id' => get_current_user_id(),
            'trace' => $exception->getTraceAsString()
        ];
        
        $this->log_error( $error_data );
        $this->handle_critical_error( $error_data );
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
            $error_data = [
                'type' => 'Fatal Error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => current_time( 'mysql' ),
                'url' => $this->get_current_url(),
                'user_id' => get_current_user_id()
            ];
            
            $this->log_error( $error_data );
            $this->handle_critical_error( $error_data );
        }
    }
    
    /**
     * Log error to file
     */
    private function log_error( $error_data ) {
        // Determine log file
        $log_file = $this->get_log_file( $error_data['type'] );
        
        // Format log entry
        $log_entry = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            $error_data['timestamp'],
            $error_data['type'],
            $error_data['message'],
            $error_data['file'],
            $error_data['line']
        );
        
        // Add additional context
        if ( ! empty( $error_data['url'] ) ) {
            $log_entry .= "URL: {$error_data['url']}\n";
        }
        
        if ( ! empty( $error_data['user_id'] ) ) {
            $log_entry .= "User ID: {$error_data['user_id']}\n";
        }
        
        if ( ! empty( $error_data['backtrace'] ) ) {
            $log_entry .= "Backtrace:\n{$error_data['backtrace']}\n";
        }
        
        if ( ! empty( $error_data['trace'] ) ) {
            $log_entry .= "Stack Trace:\n{$error_data['trace']}\n";
        }
        
        $log_entry .= str_repeat( '-', 80 ) . "\n\n";
        
        // Write to log file
        error_log( $log_entry, 3, $log_file );
        
        // Also log to database for analysis
        $this->log_to_database( $error_data );
    }
    
    /**
     * Log error to database
     */
    private function log_to_database( $error_data ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'money_quiz_error_log';
        
        // Create table if it doesn't exist
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            $this->create_error_log_table();
        }
        
        // Insert error record
        $wpdb->insert(
            $table,
            [
                'error_type' => $error_data['type'],
                'error_message' => $error_data['message'],
                'error_file' => $error_data['file'],
                'error_line' => $error_data['line'],
                'error_url' => $error_data['url'] ?? '',
                'user_id' => $error_data['user_id'] ?? 0,
                'created_at' => $error_data['timestamp']
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%d', '%s' ]
        );
    }
    
    /**
     * Create error log table
     */
    private function create_error_log_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'money_quiz_error_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `error_type` varchar(50) NOT NULL,
            `error_message` text NOT NULL,
            `error_file` varchar(255) NOT NULL,
            `error_line` int(11) NOT NULL,
            `error_url` varchar(255) DEFAULT NULL,
            `user_id` int(11) DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `error_type` (`error_type`),
            KEY `created_at` (`created_at`)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Handle critical errors
     */
    private function handle_critical_error( $error_data ) {
        // Send email notification to admin
        $this->send_critical_error_notification( $error_data );
        
        // Log to special critical errors file
        $critical_log = $this->log_dir . 'critical-errors.log';
        $log_entry = sprintf(
            "[CRITICAL] [%s] %s in %s:%d\n",
            $error_data['timestamp'],
            $error_data['message'],
            $error_data['file'],
            $error_data['line']
        );
        error_log( $log_entry, 3, $critical_log );
    }
    
    /**
     * Send critical error notification
     */
    private function send_critical_error_notification( $error_data ) {
        // Check if we should send notification (rate limiting)
        $last_notification = get_transient( 'mq_last_error_notification' );
        if ( $last_notification ) {
            return; // Don't spam notifications
        }
        
        $admin_email = get_option( 'admin_email' );
        $subject = '[Money Quiz] Critical Error Detected';
        
        $message = "A critical error has been detected in the Money Quiz plugin:\n\n";
        $message .= "Error Type: {$error_data['type']}\n";
        $message .= "Message: {$error_data['message']}\n";
        $message .= "File: {$error_data['file']}\n";
        $message .= "Line: {$error_data['line']}\n";
        $message .= "Time: {$error_data['timestamp']}\n";
        $message .= "URL: {$error_data['url']}\n\n";
        $message .= "Please check the error logs for more details.";
        
        wp_mail( $admin_email, $subject, $message );
        
        // Set rate limit (1 hour)
        set_transient( 'mq_last_error_notification', true, HOUR_IN_SECONDS );
    }
    
    /**
     * Get error type string
     */
    private function get_error_type( $errno ) {
        $types = [
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
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $types[ $errno ] ?? 'Unknown Error';
    }
    
    /**
     * Check if error is critical
     */
    private function is_critical( $error_message ) {
        foreach ( $this->critical_patterns as $pattern ) {
            if ( preg_match( '/' . $pattern . '/i', $error_message ) ) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get current URL
     */
    private function get_current_url() {
        if ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $protocol = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        return '';
    }
    
    /**
     * Get safe backtrace
     */
    private function get_safe_backtrace() {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
        $safe_backtrace = [];
        
        foreach ( $backtrace as $i => $trace ) {
            if ( $i > 10 ) break; // Limit depth
            
            $safe_backtrace[] = sprintf(
                '#%d %s%s%s() %s:%d',
                $i,
                $trace['class'] ?? '',
                $trace['type'] ?? '',
                $trace['function'] ?? '',
                $trace['file'] ?? '',
                $trace['line'] ?? 0
            );
        }
        
        return implode( "\n", $safe_backtrace );
    }
    
    /**
     * Get log file path
     */
    private function get_log_file( $error_type ) {
        $date = date( 'Y-m-d' );
        $type_slug = sanitize_file_name( strtolower( str_replace( ' ', '-', $error_type ) ) );
        return $this->log_dir . "{$type_slug}-{$date}.log";
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        if ( ! file_exists( $this->log_dir ) ) {
            wp_mkdir_p( $this->log_dir );
            
            // Add .htaccess to prevent direct access
            $htaccess = $this->log_dir . '.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, 'Deny from all' );
            }
            
            // Add index.php for extra security
            $index = $this->log_dir . 'index.php';
            if ( ! file_exists( $index ) ) {
                file_put_contents( $index, '<?php // Silence is golden' );
            }
        }
    }
    
    /**
     * Update error statistics
     */
    private function update_stats( $error_type ) {
        if ( ! isset( $this->error_stats[ $error_type ] ) ) {
            $this->error_stats[ $error_type ] = 0;
        }
        $this->error_stats[ $error_type ]++;
        
        // Update persistent stats
        $stats = get_option( 'money_quiz_error_stats', [] );
        $today = date( 'Y-m-d' );
        
        if ( ! isset( $stats[ $today ] ) ) {
            $stats[ $today ] = [];
        }
        
        if ( ! isset( $stats[ $today ][ $error_type ] ) ) {
            $stats[ $today ][ $error_type ] = 0;
        }
        
        $stats[ $today ][ $error_type ]++;
        
        // Keep only last 30 days
        $stats = array_slice( $stats, -30, null, true );
        
        update_option( 'money_quiz_error_stats', $stats );
    }
    
    /**
     * Get error statistics
     */
    public function get_stats( $days = 7 ) {
        $stats = get_option( 'money_quiz_error_stats', [] );
        $recent_stats = [];
        
        $start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        
        foreach ( $stats as $date => $errors ) {
            if ( $date >= $start_date ) {
                $recent_stats[ $date ] = $errors;
            }
        }
        
        return $recent_stats;
    }
    
    /**
     * Get recent errors from database
     */
    public function get_recent_errors( $limit = 50 ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'money_quiz_error_log';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `$table` ORDER BY created_at DESC LIMIT %d",
            $limit
        ) );
    }
    
    /**
     * Clear old error logs
     */
    public function cleanup_old_logs( $days = 30 ) {
        // Clean database
        global $wpdb;
        $table = $wpdb->prefix . 'money_quiz_error_log';
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM `$table` WHERE created_at < %s",
            $cutoff_date
        ) );
        
        // Clean log files
        $files = glob( $this->log_dir . '*.log' );
        $cutoff_time = strtotime( "-{$days} days" );
        
        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $cutoff_time ) {
                unlink( $file );
            }
        }
    }
}

// Initialize logger
add_action( 'init', function() {
    if ( defined( 'MONEY_QUIZ_ERROR_LOGGING' ) && MONEY_QUIZ_ERROR_LOGGING ) {
        new Enhanced_Error_Logger();
    }
});