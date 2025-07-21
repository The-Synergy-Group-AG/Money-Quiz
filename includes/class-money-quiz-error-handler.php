<?php
/**
 * Error Handler for MoneyQuiz Plugin
 * 
 * Enhanced error handling and secure logging
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Error_Handler {
    
    /**
     * Error severity levels
     */
    const SEVERITY_LOW = 1;
    const SEVERITY_MEDIUM = 2;
    const SEVERITY_HIGH = 3;
    const SEVERITY_CRITICAL = 4;
    
    /**
     * Initialize error handler
     */
    public static function init() {
        // Set custom error handler
        set_error_handler([__CLASS__, 'handle_error']);
        set_exception_handler([__CLASS__, 'handle_exception']);
        
        // Register shutdown function for fatal errors
        register_shutdown_function([__CLASS__, 'handle_shutdown']);
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number
     * @return bool True if error was handled
     */
    public static function handle_error($errno, $errstr, $errfile, $errline) {
        // Don't handle errors if error reporting is disabled
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $severity = self::get_severity_from_error_level($errno);
        
        self::log_error($errstr, $severity, [
            'file' => $errfile,
            'line' => $errline,
            'error_type' => $errno
        ]);
        
        // For critical errors, show user-friendly message
        if ($severity >= self::SEVERITY_CRITICAL) {
            self::display_user_error();
        }
        
        return true;
    }
    
    /**
     * Handle exceptions
     * 
     * @param Exception $exception The exception
     */
    public static function handle_exception($exception) {
        $severity = self::SEVERITY_HIGH;
        
        if ($exception instanceof Error) {
            $severity = self::SEVERITY_CRITICAL;
        }
        
        self::log_error($exception->getMessage(), $severity, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'exception_class' => get_class($exception)
        ]);
        
        if ($severity >= self::SEVERITY_CRITICAL) {
            self::display_user_error();
        }
    }
    
    /**
     * Handle fatal errors
     */
    public static function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::log_error($error['message'], self::SEVERITY_CRITICAL, [
                'file' => $error['file'],
                'line' => $error['line'],
                'error_type' => $error['type']
            ]);
            
            self::display_user_error();
        }
    }
    
    /**
     * Log error securely
     * 
     * @param string $message Error message
     * @param int $severity Error severity
     * @param array $context Additional context
     */
    public static function log_error($message, $severity = self::SEVERITY_MEDIUM, $context = []) {
        // Sanitize message to prevent log injection
        $message = sanitize_text_field($message);
        
        // Remove sensitive information from context
        $context = self::sanitize_context($context);
        
        // Create log entry
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
        ];
        
        // Log to WordPress error log
        $log_message = sprintf(
            '[MoneyQuiz] [%s] %s - %s',
            self::get_severity_name($severity),
            $message,
            json_encode($context)
        );
        
        error_log($log_message);
        
        // Store in database for admin review
        self::store_error_log($log_entry);
    }
    
    /**
     * Store error log in database
     * 
     * @param array $log_entry Log entry data
     */
    private static function store_error_log($log_entry) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'moneyquiz_error_logs';
        
        // Create table if it doesn't exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                severity tinyint(1) NOT NULL,
                message text NOT NULL,
                context longtext,
                user_id bigint(20),
                ip_address varchar(45),
                user_agent text,
                PRIMARY KEY (id),
                KEY severity (severity),
                KEY timestamp (timestamp)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        // Insert log entry
        $wpdb->insert(
            $table_name,
            [
                'timestamp' => $log_entry['timestamp'],
                'severity' => $log_entry['severity'],
                'message' => $log_entry['message'],
                'context' => json_encode($log_entry['context']),
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['ip_address'],
                'user_agent' => $log_entry['user_agent']
            ],
            ['%s', '%d', '%s', '%s', '%d', '%s', '%s']
        );
    }
    
    /**
     * Sanitize context data
     * 
     * @param array $context Context data
     * @return array Sanitized context
     */
    private static function sanitize_context($context) {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized[$sanitized_key] = self::sanitize_context($value);
            } else {
                $sanitized[$sanitized_key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Get severity from error level
     * 
     * @param int $error_level PHP error level
     * @return int Severity level
     */
    private static function get_severity_from_error_level($error_level) {
        switch ($error_level) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                return self::SEVERITY_CRITICAL;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return self::SEVERITY_HIGH;
            case E_NOTICE:
            case E_USER_NOTICE:
                return self::SEVERITY_MEDIUM;
            default:
                return self::SEVERITY_LOW;
        }
    }
    
    /**
     * Get severity name
     * 
     * @param int $severity Severity level
     * @return string Severity name
     */
    private static function get_severity_name($severity) {
        switch ($severity) {
            case self::SEVERITY_CRITICAL:
                return 'CRITICAL';
            case self::SEVERITY_HIGH:
                return 'HIGH';
            case self::SEVERITY_MEDIUM:
                return 'MEDIUM';
            case self::SEVERITY_LOW:
                return 'LOW';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Display user-friendly error message
     */
    private static function display_user_error() {
        if (is_admin()) {
            wp_die(
                'A critical error occurred in the MoneyQuiz plugin. Please check the error logs for details.',
                'MoneyQuiz Error',
                ['response' => 500]
            );
        } else {
            wp_die(
                'We apologize, but an error occurred. Please try again later.',
                'Error',
                ['response' => 500]
            );
        }
    }
    
    /**
     * Get error logs for admin display
     * 
     * @param int $limit Number of logs to retrieve
     * @param int $severity Minimum severity level
     * @return array Error logs
     */
    public static function get_error_logs($limit = 100, $severity = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'moneyquiz_error_logs';
        
        $where = '';
        $params = [];
        
        if ($severity !== null) {
            $where = 'WHERE severity >= %d';
            $params[] = $severity;
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name $where ORDER BY timestamp DESC LIMIT %d",
            array_merge($params, [$limit])
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Clear old error logs
     * 
     * @param int $days_to_keep Number of days to keep logs
     */
    public static function clear_old_logs($days_to_keep = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'moneyquiz_error_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %s",
            $cutoff_date
        ));
    }
} 