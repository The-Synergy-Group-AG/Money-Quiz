<?php
/**
 * Worker 2: Admin Panel Error Handling Implementation
 * Scope: All admin files and AJAX handlers
 * Focus: Administrative error handling with detailed logging
 */

// Admin-specific error handling for Money Quiz
class MoneyQuizAdminErrorHandler {
    
    private static $instance = null;
    private $admin_errors = array();
    private $ajax_errors = array();
    
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
        // Hook into admin initialization
        add_action('admin_init', array($this, 'initializeAdminErrorHandling'));
        add_action('wp_ajax_mq_log_js_error', array($this, 'logJavaScriptError'));
        
        // Add admin notices for errors
        add_action('admin_notices', array($this, 'displayAdminErrors'));
    }
    
    /**
     * Initialize admin error handling
     */
    public function initializeAdminErrorHandling() {
        // Set stricter error reporting in admin
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        }
    }
    
    /**
     * Handle admin operation with error catching
     */
    public function safeAdminOperation($operation, $error_message = null, $redirect_on_error = true) {
        try {
            return call_user_func($operation);
        } catch (Exception $e) {
            $this->logAdminError($e, 'admin_operation');
            
            $message = $error_message ?: $e->getMessage();
            $this->addAdminError($message);
            
            if ($redirect_on_error && !wp_doing_ajax()) {
                wp_safe_redirect(wp_get_referer());
                exit;
            }
            
            return new WP_Error('admin_error', $message);
        }
    }
    
    /**
     * Safe AJAX handler wrapper
     */
    public function safeAjaxHandler($handler, $capability = 'manage_options') {
        try {
            // Check user capability
            if (!current_user_can($capability)) {
                throw new Exception(__('Insufficient permissions', 'money-quiz'));
            }
            
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mq_ajax_nonce')) {
                throw new Exception(__('Security verification failed', 'money-quiz'));
            }
            
            // Execute handler
            $result = call_user_func($handler);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            $this->logAdminError($e, 'ajax');
            
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ), 500);
        }
    }
    
    /**
     * Log admin error with context
     */
    private function logAdminError($exception, $context = 'admin') {
        $error_data = array(
            'type' => 'Admin Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'user' => wp_get_current_user()->user_login,
            'timestamp' => current_time('mysql'),
            'page' => isset($_GET['page']) ? $_GET['page'] : 'unknown'
        );
        
        // Log to file
        $this->logToFile($error_data);
        
        // Log to database
        $this->logToDatabase($error_data);
        
        // Track for session
        if ($context !== 'ajax') {
            $this->admin_errors[] = $error_data;
        } else {
            $this->ajax_errors[] = $error_data;
        }
    }
    
    /**
     * Add admin error notice
     */
    public function addAdminError($message, $type = 'error') {
        $notices = get_transient('mq_admin_notices') ?: array();
        $notices[] = array(
            'message' => $message,
            'type' => $type,
            'time' => current_time('timestamp')
        );
        set_transient('mq_admin_notices', $notices, 60);
    }
    
    /**
     * Display admin error notices
     */
    public function displayAdminErrors() {
        $notices = get_transient('mq_admin_notices');
        
        if (!$notices || !is_array($notices)) {
            return;
        }
        
        foreach ($notices as $notice) {
            $class = 'notice notice-' . esc_attr($notice['type']) . ' is-dismissible';
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                $class,
                esc_html($notice['message'])
            );
        }
        
        // Clear notices after display
        delete_transient('mq_admin_notices');
    }
    
    /**
     * Log JavaScript errors from admin
     */
    public function logJavaScriptError() {
        try {
            if (!current_user_can('manage_options')) {
                wp_die();
            }
            
            $error_data = array(
                'type' => 'JavaScript Error',
                'message' => sanitize_text_field($_POST['message']),
                'file' => sanitize_text_field($_POST['source']),
                'line' => absint($_POST['lineno']),
                'column' => absint($_POST['colno']),
                'stack' => sanitize_textarea_field($_POST['stack']),
                'context' => 'admin_js',
                'user' => wp_get_current_user()->user_login,
                'timestamp' => current_time('mysql'),
                'url' => sanitize_url($_POST['url'])
            );
            
            $this->logToFile($error_data);
            $this->logToDatabase($error_data);
            
            wp_send_json_success('Error logged');
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to log error');
        }
    }
    
    /**
     * Log error to file
     */
    private function logToFile($error_data) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/money-quiz-logs/admin/';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_file = $log_dir . 'admin-error-' . date('Y-m-d') . '.log';
        
        $log_entry = sprintf(
            "[%s] [%s] %s: %s in %s on line %d | User: %s | Page: %s\n",
            $error_data['timestamp'],
            $error_data['context'],
            $error_data['type'],
            $error_data['message'],
            isset($error_data['file']) ? $error_data['file'] : 'unknown',
            isset($error_data['line']) ? $error_data['line'] : 0,
            isset($error_data['user']) ? $error_data['user'] : 'unknown',
            isset($error_data['page']) ? $error_data['page'] : 'unknown'
        );
        
        if (isset($error_data['trace'])) {
            $log_entry .= "Stack trace:\n" . $error_data['trace'] . "\n";
        }
        
        $log_entry .= str_repeat('-', 80) . "\n";
        
        error_log($log_entry, 3, $log_file);
    }
    
    /**
     * Log error to database
     */
    private function logToDatabase($error_data) {
        global $wpdb, $table_prefix;
        
        $table_name = $table_prefix . 'mq_admin_error_log';
        
        // Ensure table exists
        $this->createAdminErrorTable();
        
        $wpdb->insert(
            $table_name,
            array(
                'error_type' => $error_data['type'],
                'error_message' => $error_data['message'],
                'error_context' => $error_data['context'],
                'error_file' => isset($error_data['file']) ? $error_data['file'] : '',
                'error_line' => isset($error_data['line']) ? $error_data['line'] : 0,
                'user_login' => isset($error_data['user']) ? $error_data['user'] : '',
                'page' => isset($error_data['page']) ? $error_data['page'] : '',
                'stack_trace' => isset($error_data['trace']) ? $error_data['trace'] : '',
                'created_at' => $error_data['timestamp']
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Create admin error log table
     */
    private function createAdminErrorTable() {
        global $wpdb, $table_prefix;
        
        $table_name = $table_prefix . 'mq_admin_error_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            error_type varchar(50) NOT NULL,
            error_message text NOT NULL,
            error_context varchar(50) NOT NULL,
            error_file varchar(255) DEFAULT '',
            error_line int(11) DEFAULT 0,
            user_login varchar(60) DEFAULT '',
            page varchar(100) DEFAULT '',
            stack_trace text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY error_type (error_type),
            KEY created_at (created_at),
            KEY user_login (user_login)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize admin error handler
MoneyQuizAdminErrorHandler::getInstance();

// PATCH: Safe admin page rendering
function mq_render_admin_page_safe($page_callback, $page_title = 'Money Quiz') {
    try {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($page_title); ?></h1>
            <?php call_user_func($page_callback); ?>
        </div>
        <?php
    } catch (Exception $e) {
        MoneyQuizAdminErrorHandler::getInstance()->safeAdminOperation(function() use ($e) {
            throw $e;
        }, __('Failed to load admin page', 'money-quiz'));
    }
}

// PATCH: Safe settings save
function mq_save_settings_safe($settings_data) {
    return MoneyQuizAdminErrorHandler::getInstance()->safeAdminOperation(function() use ($settings_data) {
        // Validate settings
        if (!is_array($settings_data)) {
            throw new Exception(__('Invalid settings data', 'money-quiz'));
        }
        
        // Sanitize and save
        $sanitized = array();
        foreach ($settings_data as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($value);
        }
        
        $result = update_option('money_quiz_settings', $sanitized);
        
        if (!$result) {
            throw new Exception(__('Failed to save settings', 'money-quiz'));
        }
        
        return true;
    }, __('Settings could not be saved. Please try again.', 'money-quiz'));
}

// PATCH: Safe data import
function mq_import_data_safe($import_file) {
    return MoneyQuizAdminErrorHandler::getInstance()->safeAdminOperation(function() use ($import_file) {
        if (!file_exists($import_file)) {
            throw new Exception(__('Import file not found', 'money-quiz'));
        }
        
        $data = file_get_contents($import_file);
        if ($data === false) {
            throw new Exception(__('Failed to read import file', 'money-quiz'));
        }
        
        $parsed = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('Invalid import file format', 'money-quiz'));
        }
        
        // Process import...
        return $parsed;
    }, __('Import failed. Please check your file and try again.', 'money-quiz'));
}

// PATCH: JavaScript error logging
add_action('admin_footer', function() {
    ?>
    <script type="text/javascript">
    window.addEventListener('error', function(e) {
        var data = {
            action: 'mq_log_js_error',
            nonce: '<?php echo wp_create_nonce('mq_ajax_nonce'); ?>',
            message: e.message,
            source: e.filename,
            lineno: e.lineno,
            colno: e.colno,
            stack: e.error ? e.error.stack : '',
            url: window.location.href
        };
        
        jQuery.post(ajaxurl, data);
    });
    </script>
    <?php
});

// PATCH: Admin AJAX error boundaries
function mq_ajax_error_boundary($action, $handler) {
    add_action('wp_ajax_' . $action, function() use ($handler) {
        MoneyQuizAdminErrorHandler::getInstance()->safeAjaxHandler($handler);
    });
}

// Example usage:
// mq_ajax_error_boundary('mq_save_question', 'handle_save_question');

// PATCH: Database operation error handling for admin
function mq_admin_db_operation($operation, $error_message = null) {
    global $wpdb;
    
    $wpdb->hide_errors();
    $result = false;
    
    try {
        $result = call_user_func($operation);
        
        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
        
    } catch (Exception $e) {
        MoneyQuizAdminErrorHandler::getInstance()->addAdminError(
            $error_message ?: __('Database operation failed', 'money-quiz'),
            'error'
        );
        
        // Log detailed error
        $error = new Exception($e->getMessage() . ' | Query: ' . $wpdb->last_query);
        MoneyQuizAdminErrorHandler::getInstance()->safeAdminOperation(function() use ($error) {
            throw $error;
        }, null, false);
    }
    
    $wpdb->show_errors();
    return $result;
}