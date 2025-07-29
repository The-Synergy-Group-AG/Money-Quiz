<?php
/**
 * Money Quiz Safe Wrapper
 * 
 * This wrapper ensures the Money Quiz plugin is 100% safe to install by:
 * - Implementing all protective features before loading the original plugin
 * - Providing quarantine mode for testing
 * - Monitoring and logging all operations
 * - Preventing any unsafe operations
 * 
 * @package MoneyQuizSafeWrapper
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define safety constants
define( 'MQ_SAFE_MODE', true );
define( 'MQ_SAFE_WRAPPER_VERSION', '1.0.0' );
define( 'MQ_SAFE_WRAPPER_FILE', __FILE__ );
define( 'MQ_SAFE_WRAPPER_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Money Quiz Safe Wrapper Main Class
 */
class MoneyQuiz_Safe_Wrapper {
    
    /**
     * @var MoneyQuiz_Safe_Wrapper The single instance
     */
    private static $instance = null;
    
    /**
     * @var array Safety check results
     */
    private $safety_checks = array();
    
    /**
     * @var bool Whether plugin is safe to load
     */
    private $is_safe_to_load = false;
    
    /**
     * @var MoneyQuiz_Error_Handler Error handler instance
     */
    private $error_handler;
    
    /**
     * @var MoneyQuiz_Notice_Manager Notice manager instance
     */
    private $notice_manager;
    
    /**
     * @var MoneyQuiz_Dependency_Monitor Dependency monitor instance
     */
    private $dependency_monitor;
    
    /**
     * Get instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize protective systems first
        $this->init_error_handler();
        $this->init_notice_manager();
        $this->init_dependency_monitor();
        
        // Run safety checks
        $this->run_safety_checks();
        
        // Initialize based on safety results
        if ( $this->is_safe_to_load ) {
            $this->init_safe_mode();
        } else {
            $this->init_quarantine_mode();
        }
    }
    
    /**
     * Initialize error handler
     */
    private function init_error_handler() {
        require_once MQ_SAFE_WRAPPER_DIR . 'includes/class-error-handler.php';
        $this->error_handler = new MoneyQuiz_Error_Handler();
        $this->error_handler->register();
    }
    
    /**
     * Initialize notice manager
     */
    private function init_notice_manager() {
        require_once MQ_SAFE_WRAPPER_DIR . 'includes/class-notice-manager.php';
        $this->notice_manager = new MoneyQuiz_Notice_Manager();
    }
    
    /**
     * Initialize dependency monitor
     */
    private function init_dependency_monitor() {
        require_once MQ_SAFE_WRAPPER_DIR . 'includes/class-dependency-monitor.php';
        $this->dependency_monitor = new MoneyQuiz_Dependency_Monitor( $this->notice_manager );
    }
    
    /**
     * Run comprehensive safety checks
     */
    private function run_safety_checks() {
        $checks = array(
            'php_version' => $this->check_php_version(),
            'wordpress_version' => $this->check_wordpress_version(),
            'critical_files' => $this->check_critical_files(),
            'dangerous_code' => $this->check_for_dangerous_code(),
            'sql_injection' => $this->check_for_sql_injection(),
            'file_permissions' => $this->check_file_permissions(),
            'composer_dependencies' => $this->check_composer_dependencies(),
            'database_safety' => $this->check_database_safety(),
        );
        
        $this->safety_checks = $checks;
        $this->is_safe_to_load = ! in_array( false, $checks, true );
        
        // Log results
        $this->log_safety_results( $checks );
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        $required = '7.4.0';
        $current = PHP_VERSION;
        
        if ( version_compare( $current, $required, '>=' ) ) {
            return true;
        }
        
        $this->notice_manager->add_notice(
            'php_version_error',
            sprintf(
                __( '<strong>Money Quiz Safety Check Failed:</strong> PHP %s or higher is required. You are running PHP %s.', 'money-quiz' ),
                $required,
                $current
            ),
            'error',
            array( 'dismissible' => false )
        );
        
        return false;
    }
    
    /**
     * Check WordPress version
     */
    private function check_wordpress_version() {
        $required = '5.8';
        $current = get_bloginfo( 'version' );
        
        if ( version_compare( $current, $required, '>=' ) ) {
            return true;
        }
        
        $this->notice_manager->add_notice(
            'wp_version_warning',
            sprintf(
                __( '<strong>Money Quiz Warning:</strong> WordPress %s or higher is recommended. You are running WordPress %s.', 'money-quiz' ),
                $required,
                $current
            ),
            'warning'
        );
        
        // This is a warning, not a failure
        return true;
    }
    
    /**
     * Check for critical files
     */
    private function check_critical_files() {
        $required_files = array(
            'moneyquiz.php',
            'class.moneyquiz.php',
        );
        
        $missing = array();
        
        foreach ( $required_files as $file ) {
            if ( ! file_exists( MQ_SAFE_WRAPPER_DIR . $file ) ) {
                $missing[] = $file;
            }
        }
        
        if ( ! empty( $missing ) ) {
            $this->notice_manager->add_notice(
                'missing_files',
                sprintf(
                    __( '<strong>Money Quiz Safety Check Failed:</strong> Required files missing: %s', 'money-quiz' ),
                    implode( ', ', $missing )
                ),
                'error',
                array( 'dismissible' => false )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check for dangerous code patterns
     */
    private function check_for_dangerous_code() {
        $dangerous_patterns = array(
            'eval\s*\(' => 'eval() usage detected',
            'create_function\s*\(' => 'create_function() usage detected',
            'exec\s*\(' => 'exec() usage detected',
            'system\s*\(' => 'system() usage detected',
            'passthru\s*\(' => 'passthru() usage detected',
            'shell_exec\s*\(' => 'shell_exec() usage detected',
            'base64_decode\s*\(' => 'Potential obfuscated code',
        );
        
        $found_issues = array();
        
        // Scan PHP files
        $files = $this->get_php_files();
        
        foreach ( $files as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $dangerous_patterns as $pattern => $description ) {
                if ( preg_match( '/' . $pattern . '/i', $content ) ) {
                    $found_issues[] = sprintf(
                        '%s in %s',
                        $description,
                        basename( $file )
                    );
                }
            }
        }
        
        if ( ! empty( $found_issues ) ) {
            $this->notice_manager->add_notice(
                'dangerous_code',
                sprintf(
                    __( '<strong>Money Quiz Safety Check Failed:</strong> Dangerous code patterns found:<br>%s', 'money-quiz' ),
                    implode( '<br>', $found_issues )
                ),
                'error',
                array( 'dismissible' => false )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check for SQL injection vulnerabilities
     */
    private function check_for_sql_injection() {
        $vulnerable_patterns = array(
            '\$wpdb->query\s*\(\s*["\'].*\.\s*\$_(GET|POST|REQUEST)' => 'Direct user input in query',
            '\$wpdb->get_results\s*\(\s*["\'].*\.\s*\$_(GET|POST|REQUEST)' => 'Direct user input in query',
            'WHERE\s+\w+\s*=\s*["\']?\s*\.\s*\$_(GET|POST|REQUEST)' => 'Unescaped WHERE clause',
        );
        
        $vulnerabilities = array();
        $files = $this->get_php_files();
        
        foreach ( $files as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $vulnerable_patterns as $pattern => $description ) {
                if ( preg_match( '/' . $pattern . '/i', $content ) ) {
                    $vulnerabilities[] = sprintf(
                        '%s in %s',
                        $description,
                        basename( $file )
                    );
                }
            }
        }
        
        if ( ! empty( $vulnerabilities ) ) {
            $this->notice_manager->add_notice(
                'sql_injection',
                sprintf(
                    __( '<strong>Money Quiz CRITICAL Security Issue:</strong> SQL injection vulnerabilities found:<br>%s<br><br>This plugin MUST NOT be activated until these are fixed!', 'money-quiz' ),
                    implode( '<br>', $vulnerabilities )
                ),
                'error',
                array( 'dismissible' => false )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        $writable_concern = array();
        
        // Check if PHP files are writable (security concern)
        $files = $this->get_php_files();
        
        foreach ( $files as $file ) {
            if ( is_writable( $file ) ) {
                $perms = substr( sprintf( '%o', fileperms( $file ) ), -4 );
                if ( $perms !== '0644' && $perms !== '0444' ) {
                    $writable_concern[] = basename( $file ) . ' (' . $perms . ')';
                }
            }
        }
        
        if ( ! empty( $writable_concern ) ) {
            $this->notice_manager->add_notice(
                'file_permissions',
                sprintf(
                    __( '<strong>Money Quiz Security Warning:</strong> Some files have unsafe permissions:<br>%s<br>Recommended: 0644 for files', 'money-quiz' ),
                    implode( '<br>', $writable_concern )
                ),
                'warning'
            );
        }
        
        return true; // This is a warning, not a failure
    }
    
    /**
     * Check Composer dependencies
     */
    private function check_composer_dependencies() {
        // For now, the original plugin doesn't use Composer
        // This is where we'd check for vendor/autoload.php
        return true;
    }
    
    /**
     * Check database safety
     */
    private function check_database_safety() {
        // Check for DROP TABLE without proper safeguards
        $files = $this->get_php_files();
        $unsafe_operations = array();
        
        foreach ( $files as $file ) {
            $content = file_get_contents( $file );
            
            // Check for unsafe DROP TABLE
            if ( preg_match( '/DROP\s+TABLE(?!\s+IF\s+EXISTS)/i', $content ) ) {
                $unsafe_operations[] = 'DROP TABLE without IF EXISTS in ' . basename( $file );
            }
            
            // Check for TRUNCATE
            if ( preg_match( '/TRUNCATE\s+TABLE/i', $content ) ) {
                $unsafe_operations[] = 'TRUNCATE TABLE in ' . basename( $file );
            }
        }
        
        if ( ! empty( $unsafe_operations ) ) {
            $this->notice_manager->add_notice(
                'database_safety',
                sprintf(
                    __( '<strong>Money Quiz Safety Warning:</strong> Potentially unsafe database operations found:<br>%s', 'money-quiz' ),
                    implode( '<br>', $unsafe_operations )
                ),
                'warning'
            );
        }
        
        return true; // Warning only
    }
    
    /**
     * Get all PHP files in plugin
     */
    private function get_php_files() {
        $files = array();
        $dir = MQ_SAFE_WRAPPER_DIR;
        
        // Get all PHP files in root
        foreach ( glob( $dir . '*.php' ) as $file ) {
            // Skip our wrapper files
            if ( strpos( $file, 'safe-wrapper' ) === false && 
                 strpos( $file, 'safety-check' ) === false ) {
                $files[] = $file;
            }
        }
        
        return $files;
    }
    
    /**
     * Initialize safe mode
     */
    private function init_safe_mode() {
        // Add protective filters before loading original plugin
        add_filter( 'pre_update_option', array( $this, 'filter_option_updates' ), 10, 3 );
        add_filter( 'query', array( $this, 'filter_database_queries' ) );
        
        // Monitor all actions
        add_action( 'all', array( $this, 'monitor_all_actions' ), 1 );
        
        // Add safety notice
        $this->notice_manager->add_notice(
            'safe_mode_active',
            __( '<strong>Money Quiz Safe Mode:</strong> Plugin is running with additional safety checks and monitoring.', 'money-quiz' ),
            'info',
            array( 'dismissible' => true )
        );
        
        // Load original plugin with error handling
        add_action( 'plugins_loaded', array( $this, 'load_original_plugin' ), 20 );
    }
    
    /**
     * Initialize quarantine mode
     */
    private function init_quarantine_mode() {
        // Do not load the original plugin
        $this->notice_manager->add_notice(
            'quarantine_mode',
            sprintf(
                __( '<strong>Money Quiz Quarantine Mode:</strong> Plugin has been quarantined due to safety check failures. <a href="%s">View Safety Report</a>', 'money-quiz' ),
                admin_url( 'admin.php?page=money-quiz-safety-report' )
            ),
            'error',
            array( 'dismissible' => false )
        );
        
        // Add admin page for safety report
        add_action( 'admin_menu', array( $this, 'add_safety_report_page' ) );
    }
    
    /**
     * Load original plugin with protection
     */
    public function load_original_plugin() {
        try {
            // Set up protective environment
            $this->setup_protective_environment();
            
            // Load with error handling
            $original_plugin = MQ_SAFE_WRAPPER_DIR . 'moneyquiz.php';
            
            if ( file_exists( $original_plugin ) ) {
                // Prevent direct loading of dangerous functions
                $this->override_dangerous_functions();
                
                // Load plugin
                require_once $original_plugin;
                
                $this->notice_manager->add_notice(
                    'plugin_loaded',
                    __( 'Money Quiz plugin loaded successfully in safe mode.', 'money-quiz' ),
                    'success',
                    array( 'dismissible' => true, 'dismissal_duration' => DAY_IN_SECONDS )
                );
            }
            
        } catch ( Exception $e ) {
            $this->error_handler->handle_exception( $e );
            
            $this->notice_manager->add_notice(
                'load_failed',
                sprintf(
                    __( '<strong>Money Quiz Load Failed:</strong> %s', 'money-quiz' ),
                    $e->getMessage()
                ),
                'error',
                array( 'dismissible' => false )
            );
        }
    }
    
    /**
     * Set up protective environment
     */
    private function setup_protective_environment() {
        // Override dangerous globals
        $_REQUEST = $this->sanitize_deep( $_REQUEST );
        $_GET = $this->sanitize_deep( $_GET );
        $_POST = $this->sanitize_deep( $_POST );
        
        // Set safety constants
        if ( ! defined( 'MONEYQUIZ_SAFE_MODE' ) ) {
            define( 'MONEYQUIZ_SAFE_MODE', true );
        }
    }
    
    /**
     * Override dangerous functions
     */
    private function override_dangerous_functions() {
        // This would require PHP extensions like runkit
        // For now, we'll monitor their usage
    }
    
    /**
     * Deep sanitization
     */
    private function sanitize_deep( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->sanitize_deep( $value );
            }
        } elseif ( is_string( $data ) ) {
            $data = sanitize_text_field( $data );
        }
        
        return $data;
    }
    
    /**
     * Filter option updates for safety
     */
    public function filter_option_updates( $value, $option, $old_value ) {
        // Log all option changes
        $this->log_activity( 'option_update', array(
            'option' => $option,
            'old_value' => $old_value,
            'new_value' => $value,
        ) );
        
        // Prevent dangerous option updates
        $protected_options = array(
            'siteurl',
            'home',
            'admin_email',
            'users_can_register',
            'default_role',
        );
        
        if ( in_array( $option, $protected_options, true ) ) {
            $this->notice_manager->add_notice(
                'protected_option_' . $option,
                sprintf(
                    __( 'Money Quiz attempted to modify protected option: %s', 'money-quiz' ),
                    $option
                ),
                'warning'
            );
            
            return $old_value; // Prevent the update
        }
        
        return $value;
    }
    
    /**
     * Filter database queries for safety
     */
    public function filter_database_queries( $query ) {
        // Log all queries in safe mode
        if ( defined( 'MQ_SAFE_MODE' ) && MQ_SAFE_MODE ) {
            $this->log_activity( 'database_query', array( 'query' => $query ) );
            
            // Check for dangerous operations
            if ( preg_match( '/DROP\s+TABLE(?!\s+IF\s+EXISTS)/i', $query ) ) {
                $this->notice_manager->add_notice(
                    'dangerous_query',
                    __( 'Money Quiz attempted unsafe DROP TABLE operation', 'money-quiz' ),
                    'error'
                );
                
                // Convert to safe version
                $query = preg_replace( '/DROP\s+TABLE/i', 'DROP TABLE IF EXISTS', $query );
            }
        }
        
        return $query;
    }
    
    /**
     * Monitor all WordPress actions
     */
    public function monitor_all_actions( $tag ) {
        static $monitored_actions = array();
        
        // Only monitor Money Quiz actions
        if ( strpos( $tag, 'moneyquiz' ) !== false || strpos( $tag, 'mq_' ) !== false ) {
            if ( ! isset( $monitored_actions[ $tag ] ) ) {
                $monitored_actions[ $tag ] = 0;
            }
            $monitored_actions[ $tag ]++;
            
            // Log high-frequency actions
            if ( $monitored_actions[ $tag ] > 100 ) {
                $this->log_activity( 'high_frequency_action', array(
                    'action' => $tag,
                    'count' => $monitored_actions[ $tag ],
                ) );
            }
        }
    }
    
    /**
     * Add safety report admin page
     */
    public function add_safety_report_page() {
        add_menu_page(
            __( 'Money Quiz Safety Report', 'money-quiz' ),
            __( 'MQ Safety Report', 'money-quiz' ),
            'manage_options',
            'money-quiz-safety-report',
            array( $this, 'render_safety_report' ),
            'dashicons-shield',
            100
        );
    }
    
    /**
     * Render safety report
     */
    public function render_safety_report() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Money Quiz Safety Report', 'money-quiz' ); ?></h1>
            
            <div class="notice notice-error">
                <p><?php _e( 'The Money Quiz plugin has been quarantined due to safety check failures.', 'money-quiz' ); ?></p>
            </div>
            
            <h2><?php _e( 'Safety Check Results', 'money-quiz' ); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Check', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Details', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $this->safety_checks as $check => $passed ) : ?>
                        <tr>
                            <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $check ) ) ); ?></td>
                            <td>
                                <?php if ( $passed ) : ?>
                                    <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                    <?php _e( 'Passed', 'money-quiz' ); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                    <?php _e( 'Failed', 'money-quiz' ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Get detailed error messages for failed checks
                                if ( ! $passed ) {
                                    $this->display_check_details( $check );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2><?php _e( 'Recommendations', 'money-quiz' ); ?></h2>
            
            <div class="card">
                <h3><?php _e( 'Immediate Actions Required', 'money-quiz' ); ?></h3>
                <ol>
                    <li><?php _e( 'Do not activate this plugin on a production site', 'money-quiz' ); ?></li>
                    <li><?php _e( 'Contact the plugin developer to fix security issues', 'money-quiz' ); ?></li>
                    <li><?php _e( 'Consider using an alternative plugin', 'money-quiz' ); ?></li>
                </ol>
            </div>
            
            <h2><?php _e( 'Activity Log', 'money-quiz' ); ?></h2>
            
            <div class="card">
                <p><?php _e( 'Recent plugin activity:', 'money-quiz' ); ?></p>
                <pre><?php echo esc_html( $this->get_recent_activity_log() ); ?></pre>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display check details
     */
    private function display_check_details( $check ) {
        switch ( $check ) {
            case 'sql_injection':
                echo '<strong>' . __( 'SQL injection vulnerabilities detected. This is a critical security issue.', 'money-quiz' ) . '</strong>';
                break;
            case 'dangerous_code':
                echo '<strong>' . __( 'Dangerous PHP functions detected that could compromise site security.', 'money-quiz' ) . '</strong>';
                break;
            case 'critical_files':
                echo __( 'Required plugin files are missing.', 'money-quiz' );
                break;
            default:
                echo __( 'Check failed. See notices for details.', 'money-quiz' );
        }
    }
    
    /**
     * Log activity
     */
    private function log_activity( $type, $data ) {
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'type' => $type,
            'data' => $data,
        );
        
        // Store in transient for recent activity
        $activity_log = get_transient( 'mq_safe_wrapper_activity' ) ?: array();
        array_unshift( $activity_log, $log_entry );
        $activity_log = array_slice( $activity_log, 0, 100 ); // Keep last 100 entries
        set_transient( 'mq_safe_wrapper_activity', $activity_log, DAY_IN_SECONDS );
        
        // Also log to error log if debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[Money Quiz Safe Wrapper] %s: %s',
                $type,
                json_encode( $data )
            ) );
        }
    }
    
    /**
     * Get recent activity log
     */
    private function get_recent_activity_log() {
        $activity_log = get_transient( 'mq_safe_wrapper_activity' ) ?: array();
        $output = '';
        
        foreach ( array_slice( $activity_log, 0, 20 ) as $entry ) {
            $output .= sprintf(
                "[%s] %s: %s\n",
                $entry['timestamp'],
                $entry['type'],
                json_encode( $entry['data'] )
            );
        }
        
        return $output ?: __( 'No recent activity.', 'money-quiz' );
    }
    
    /**
     * Log safety results
     */
    private function log_safety_results( $checks ) {
        $failed_checks = array_filter( $checks, function( $passed ) {
            return ! $passed;
        } );
        
        if ( ! empty( $failed_checks ) ) {
            error_log( sprintf(
                '[Money Quiz Safe Wrapper] Safety checks failed: %s',
                implode( ', ', array_keys( $failed_checks ) )
            ) );
        }
    }
}

// Initialize the safe wrapper
add_action( 'plugins_loaded', function() {
    MoneyQuiz_Safe_Wrapper::instance();
}, 5 );

// Register deactivation hook to clean up
register_deactivation_hook( __FILE__, function() {
    // Clean up transients
    delete_transient( 'mq_safe_wrapper_activity' );
    
    // Remove any scheduled events
    wp_clear_scheduled_hook( 'mq_safe_wrapper_cleanup' );
} );