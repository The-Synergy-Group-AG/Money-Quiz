<?php
/**
 * Worker 1: Frontend Error Handling Implementation
 * Scope: quiz.moneycoach.php and frontend functions
 * Focus: User-facing error handling with graceful degradation
 */

// Error handling framework for Money Quiz frontend
class MoneyQuizErrorHandler {
    
    private static $instance = null;
    private $errors = array();
    private $error_log_path;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->error_log_path = $upload_dir['basedir'] . '/money-quiz-logs/';
        
        // Create log directory if not exists
        if (!file_exists($this->error_log_path)) {
            wp_mkdir_p($this->error_log_path);
        }
        
        // Set custom error handler
        set_error_handler(array($this, 'handleError'));
        set_exception_handler(array($this, 'handleException'));
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line) {
        // Don't handle suppressed errors
        if (error_reporting() === 0) {
            return false;
        }
        
        $error = array(
            'type' => $this->getErrorType($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => current_time('mysql'),
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'user_id' => get_current_user_id()
        );
        
        $this->logError($error);
        
        // Display user-friendly message for fatal errors
        if ($severity === E_ERROR || $severity === E_PARSE) {
            $this->displayErrorPage('A critical error occurred. Please try again later.');
            exit;
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception) {
        $error = array(
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => current_time('mysql'),
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'user_id' => get_current_user_id()
        );
        
        $this->logError($error);
        $this->displayErrorPage('An unexpected error occurred. Please try again later.');
    }
    
    /**
     * Log error with context
     */
    public function logError($error_data, $context = 'frontend') {
        $log_file = $this->error_log_path . 'error-' . date('Y-m-d') . '.log';
        
        $log_entry = sprintf(
            "[%s] %s: %s in %s on line %d | URL: %s | User: %d\n",
            $error_data['timestamp'],
            $error_data['type'],
            $error_data['message'],
            $error_data['file'],
            $error_data['line'],
            $error_data['url'],
            $error_data['user_id']
        );
        
        error_log($log_entry, 3, $log_file);
        
        // Also log to database for admin dashboard
        $this->logToDatabase($error_data, $context);
        
        // Send admin notification for critical errors
        if ($this->isCriticalError($error_data)) {
            $this->notifyAdmin($error_data);
        }
    }
    
    /**
     * Display user-friendly error page
     */
    private function displayErrorPage($message) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php esc_html_e('Error - Money Quiz', 'money-quiz'); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                }
                h1 { color: #d32f2f; }
                p { color: #666; line-height: 1.6; }
                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background: #2196f3;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1><?php esc_html_e('Oops! Something went wrong', 'money-quiz'); ?></h1>
                <p><?php echo esc_html($message); ?></p>
                <a href="<?php echo esc_url(home_url()); ?>">
                    <?php esc_html_e('Return to Homepage', 'money-quiz'); ?>
                </a>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get human-readable error type
     */
    private function getErrorType($severity) {
        $types = array(
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_DEPRECATED => 'Deprecated',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error'
        );
        
        return isset($types[$severity]) ? $types[$severity] : 'Unknown Error';
    }
    
    /**
     * Check if error is critical
     */
    private function isCriticalError($error_data) {
        $critical_types = array('Fatal Error', 'Parse Error', 'Exception');
        return in_array($error_data['type'], $critical_types);
    }
    
    /**
     * Log error to database
     */
    private function logToDatabase($error_data, $context) {
        global $wpdb, $table_prefix;
        
        $table_name = $table_prefix . 'mq_error_log';
        
        // Create table if not exists
        $this->createErrorTable();
        
        $wpdb->insert(
            $table_name,
            array(
                'error_type' => $error_data['type'],
                'error_message' => $error_data['message'],
                'error_file' => $error_data['file'],
                'error_line' => $error_data['line'],
                'error_context' => $context,
                'user_id' => $error_data['user_id'],
                'url' => $error_data['url'],
                'created_at' => $error_data['timestamp']
            ),
            array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Create error log table
     */
    private function createErrorTable() {
        global $wpdb, $table_prefix;
        
        $table_name = $table_prefix . 'mq_error_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            error_type varchar(50) NOT NULL,
            error_message text NOT NULL,
            error_file varchar(255) NOT NULL,
            error_line int(11) NOT NULL,
            error_context varchar(50) NOT NULL,
            user_id int(11) DEFAULT 0,
            url varchar(255) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY error_type (error_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Notify admin of critical errors
     */
    private function notifyAdmin($error_data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Critical Error in Money Quiz', $site_name);
        
        $message = sprintf(
            "A critical error occurred in the Money Quiz plugin:\n\n" .
            "Error Type: %s\n" .
            "Message: %s\n" .
            "File: %s\n" .
            "Line: %d\n" .
            "URL: %s\n" .
            "Time: %s\n\n" .
            "Please check the error logs for more details.",
            $error_data['type'],
            $error_data['message'],
            $error_data['file'],
            $error_data['line'],
            $error_data['url'],
            $error_data['timestamp']
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Initialize error handler
MoneyQuizErrorHandler::getInstance();

// PATCH: quiz.moneycoach.php error handling
function mq_safe_quiz_execution($callback, $error_message = null) {
    try {
        return call_user_func($callback);
    } catch (Exception $e) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'Exception',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => current_time('mysql'),
            'url' => $_SERVER['REQUEST_URI'],
            'user_id' => get_current_user_id()
        ), 'quiz');
        
        if ($error_message) {
            return new WP_Error('quiz_error', $error_message);
        }
        
        return new WP_Error('quiz_error', __('An error occurred while processing your quiz. Please try again.', 'money-quiz'));
    }
}

// PATCH: Database operation wrapper
function mq_safe_db_operation($operation, $default = null) {
    global $wpdb;
    
    // Suppress WordPress database errors temporarily
    $suppress = $wpdb->suppress_errors();
    
    try {
        $result = call_user_func($operation);
        
        // Check for database errors
        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
        
        $wpdb->suppress_errors($suppress);
        return $result;
        
    } catch (Exception $e) {
        $wpdb->suppress_errors($suppress);
        
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'Database Error',
            'message' => $e->getMessage(),
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql'),
            'url' => $_SERVER['REQUEST_URI'],
            'user_id' => get_current_user_id()
        ), 'database');
        
        return $default;
    }
}

// PATCH: Safe array access
function mq_get_array_value($array, $key, $default = null) {
    if (!is_array($array)) {
        return $default;
    }
    
    return isset($array[$key]) ? $array[$key] : $default;
}

// PATCH: Safe object property access
function mq_get_object_property($object, $property, $default = null) {
    if (!is_object($object)) {
        return $default;
    }
    
    return property_exists($object, $property) ? $object->$property : $default;
}

// PATCH: Email sending with error handling
function mq_send_email_safe($to, $subject, $message, $headers = '') {
    try {
        $result = wp_mail($to, $subject, $message, $headers);
        
        if (!$result) {
            throw new Exception('Email sending failed');
        }
        
        return true;
        
    } catch (Exception $e) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'Email Error',
            'message' => sprintf('Failed to send email to %s: %s', $to, $e->getMessage()),
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql'),
            'url' => $_SERVER['REQUEST_URI'],
            'user_id' => get_current_user_id()
        ), 'email');
        
        return false;
    }
}

// PATCH: File operation wrapper
function mq_safe_file_operation($operation, $file_path, $error_message = null) {
    try {
        if (!file_exists(dirname($file_path))) {
            wp_mkdir_p(dirname($file_path));
        }
        
        $result = call_user_func($operation);
        
        if ($result === false) {
            throw new Exception($error_message ?: 'File operation failed');
        }
        
        return $result;
        
    } catch (Exception $e) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'File Error',
            'message' => sprintf('File operation failed for %s: %s', $file_path, $e->getMessage()),
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql'),
            'url' => $_SERVER['REQUEST_URI'],
            'user_id' => get_current_user_id()
        ), 'file');
        
        return false;
    }
}