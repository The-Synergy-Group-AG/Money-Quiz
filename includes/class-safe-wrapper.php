<?php
/**
 * Money Quiz Safe Wrapper
 * 
 * Provides comprehensive safety features and wraps the original plugin
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Safe Wrapper Class
 */
class MoneyQuiz_Safe_Wrapper {
    
    /**
     * @var MoneyQuiz_Safe_Wrapper
     */
    private static $instance = null;
    
    /**
     * @var bool Quarantine mode
     */
    private $quarantine_mode = false;
    
    /**
     * @var array Security violations
     */
    private $violations = array();
    
    /**
     * @var MoneyQuiz_Error_Handler
     */
    private $error_handler;
    
    /**
     * @var MoneyQuiz_Notice_Manager
     */
    private $notice_manager;
    
    /**
     * @var MoneyQuiz_Dependency_Monitor
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
        // Initialize components
        $this->error_handler = new MoneyQuiz_Error_Handler();
        $this->notice_manager = MoneyQuiz_Notice_Manager::instance();
        $this->dependency_monitor = MoneyQuiz_Dependency_Monitor::instance();
    }
    
    /**
     * Initialize the safe wrapper
     */
    public function init() {
        // Run initial safety checks
        $this->run_safety_checks();
        
        // Setup protective measures
        $this->setup_protections();
        
        // Load original plugin if safe
        if ( ! $this->quarantine_mode ) {
            $this->load_original_plugin();
        } else {
            $this->enter_quarantine_mode();
        }
        
        // Setup monitoring
        $this->setup_monitoring();
        
        // Add admin interface
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }
    
    /**
     * Run safety checks
     */
    private function run_safety_checks() {
        $checks = array(
            'sql_injection' => $this->check_for_sql_injection(),
            'file_permissions' => $this->check_file_permissions(),
            'dangerous_functions' => $this->check_dangerous_functions(),
            'external_calls' => $this->check_external_calls(),
            'exposed_secrets' => $this->check_exposed_secrets(),
        );
        
        foreach ( $checks as $check => $result ) {
            if ( ! $result['safe'] ) {
                $this->violations[ $check ] = $result['details'];
                
                // Critical violations trigger quarantine
                if ( $result['critical'] ) {
                    $this->quarantine_mode = true;
                }
            }
        }
        
        // Store check results
        update_option( 'money_quiz_safety_check_results', array(
            'timestamp' => current_time( 'mysql' ),
            'violations' => $this->violations,
            'quarantine' => $this->quarantine_mode,
        ) );
    }
    
    /**
     * Check for SQL injection vulnerabilities
     */
    private function check_for_sql_injection() {
        $vulnerable_patterns = array(
            '\$wpdb->query\s*\(\s*["\'].*\.\s*\$_(GET|POST|REQUEST)' => 'Direct user input in query',
            '\$wpdb->get_results\s*\(\s*["\'].*\.\s*\$_(GET|POST|REQUEST)' => 'Direct user input in get_results',
            'WHERE\s+\w+\s*=\s*["\']?\s*\.\s*\$_(GET|POST|REQUEST)' => 'Unescaped WHERE clause',
        );
        
        $files_to_check = $this->get_plugin_files();
        $vulnerabilities = array();
        
        foreach ( $files_to_check as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $vulnerable_patterns as $pattern => $description ) {
                if ( preg_match( '/' . $pattern . '/i', $content, $matches ) ) {
                    $vulnerabilities[] = array(
                        'file' => basename( $file ),
                        'type' => $description,
                        'pattern' => $matches[0],
                    );
                }
            }
        }
        
        return array(
            'safe' => empty( $vulnerabilities ),
            'critical' => count( $vulnerabilities ) > 5,
            'details' => $vulnerabilities,
        );
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        $issues = array();
        $critical = false;
        
        // Check if plugin files are writable
        $files = $this->get_plugin_files();
        foreach ( $files as $file ) {
            if ( is_writable( $file ) ) {
                $perms = substr( sprintf( '%o', fileperms( $file ) ), -4 );
                if ( $perms == '0777' || $perms == '0666' ) {
                    $issues[] = array(
                        'file' => basename( $file ),
                        'permissions' => $perms,
                        'risk' => 'high',
                    );
                    $critical = true;
                }
            }
        }
        
        return array(
            'safe' => empty( $issues ),
            'critical' => $critical,
            'details' => $issues,
        );
    }
    
    /**
     * Check for dangerous functions
     */
    private function check_dangerous_functions() {
        $dangerous_functions = array(
            'eval' => 'Code execution',
            'exec' => 'System command execution',
            'system' => 'System command execution',
            'shell_exec' => 'Shell command execution',
            'passthru' => 'Command execution',
            'assert' => 'Code assertion',
            'create_function' => 'Dynamic function creation',
            'call_user_func' => 'Dynamic function call',
            'preg_replace.*\/e' => 'Code execution in regex',
        );
        
        $files_to_check = $this->get_plugin_files();
        $found_functions = array();
        
        foreach ( $files_to_check as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $dangerous_functions as $function => $risk ) {
                if ( preg_match( '/\b' . $function . '\s*\(/i', $content ) ) {
                    $found_functions[] = array(
                        'file' => basename( $file ),
                        'function' => $function,
                        'risk' => $risk,
                    );
                }
            }
        }
        
        return array(
            'safe' => empty( $found_functions ),
            'critical' => count( $found_functions ) > 3,
            'details' => $found_functions,
        );
    }
    
    /**
     * Check for external calls
     */
    private function check_external_calls() {
        $external_patterns = array(
            'wp_remote_get\s*\(\s*["\']https?:\/\/(?!www\.wordpress\.org)' => 'External HTTP request',
            'curl_init\s*\(\s*["\']https?:\/\/' => 'CURL request',
            'file_get_contents\s*\(\s*["\']https?:\/\/' => 'Remote file access',
            '101businessinsights\.com' => 'License server call',
        );
        
        $files_to_check = $this->get_plugin_files();
        $external_calls = array();
        
        foreach ( $files_to_check as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $external_patterns as $pattern => $description ) {
                if ( preg_match( '/' . $pattern . '/i', $content, $matches ) ) {
                    $external_calls[] = array(
                        'file' => basename( $file ),
                        'type' => $description,
                        'match' => isset( $matches[0] ) ? substr( $matches[0], 0, 100 ) : '',
                    );
                }
            }
        }
        
        return array(
            'safe' => empty( $external_calls ),
            'critical' => false,
            'details' => $external_calls,
        );
    }
    
    /**
     * Check for exposed secrets
     */
    private function check_exposed_secrets() {
        $secret_patterns = array(
            'define\s*\(\s*["\'][^"\']*SECRET[^"\']*["\'],\s*["\'][^"\']+["\']' => 'Hardcoded secret key',
            'define\s*\(\s*["\'][^"\']*KEY[^"\']*["\'],\s*["\'][^"\']+["\']' => 'Hardcoded API key',
            'define\s*\(\s*["\'][^"\']*PASSWORD[^"\']*["\'],\s*["\'][^"\']+["\']' => 'Hardcoded password',
            '\$[^=]+=\s*["\'][0-9a-f]{32}["\']' => 'Hardcoded hash/token',
        );
        
        $files_to_check = $this->get_plugin_files();
        $exposed_secrets = array();
        
        foreach ( $files_to_check as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $secret_patterns as $pattern => $description ) {
                if ( preg_match( '/' . $pattern . '/i', $content, $matches ) ) {
                    $exposed_secrets[] = array(
                        'file' => basename( $file ),
                        'type' => $description,
                        'match' => $this->redact_secret( $matches[0] ),
                    );
                }
            }
        }
        
        return array(
            'safe' => empty( $exposed_secrets ),
            'critical' => count( $exposed_secrets ) > 2,
            'details' => $exposed_secrets,
        );
    }
    
    /**
     * Get plugin files to check
     */
    private function get_plugin_files() {
        $files = array();
        $dir = MONEY_QUIZ_PLUGIN_DIR;
        
        // Get PHP files in root
        $files = glob( $dir . '*.php' );
        
        // Get PHP files in subdirectories
        $subdirs = array( 'includes', 'admin', 'public', 'assets' );
        foreach ( $subdirs as $subdir ) {
            if ( is_dir( $dir . $subdir ) ) {
                $subfiles = glob( $dir . $subdir . '/*.php' );
                if ( $subfiles ) {
                    $files = array_merge( $files, $subfiles );
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Redact secret values
     */
    private function redact_secret( $secret ) {
        return preg_replace( '/(["\'])([^"\']{4})[^"\']*([^"\']{4})(["\'])/', '$1$2****$3$4', $secret );
    }
    
    /**
     * Setup protections
     */
    private function setup_protections() {
        // Input sanitization
        add_action( 'init', array( $this, 'sanitize_global_inputs' ), 1 );
        
        // SQL injection prevention
        add_filter( 'query', array( $this, 'filter_queries' ), 1 );
        
        // File upload restrictions
        add_filter( 'upload_mimes', array( $this, 'restrict_mime_types' ), 999 );
        
        // Disable dangerous functions if possible
        if ( ! defined( 'MONEY_QUIZ_ALLOW_DANGEROUS' ) || ! MONEY_QUIZ_ALLOW_DANGEROUS ) {
            $this->disable_dangerous_functions();
        }
        
        // Add security headers
        add_action( 'send_headers', array( $this, 'add_security_headers' ) );
        
        // Monitor admin actions
        add_action( 'admin_action_edit', array( $this, 'monitor_admin_actions' ), 1 );
    }
    
    /**
     * Sanitize global inputs
     */
    public function sanitize_global_inputs() {
        // Sanitize $_GET
        if ( ! empty( $_GET ) ) {
            foreach ( $_GET as $key => $value ) {
                $_GET[ $key ] = $this->deep_sanitize( $value );
            }
        }
        
        // Sanitize $_POST
        if ( ! empty( $_POST ) ) {
            foreach ( $_POST as $key => $value ) {
                $_POST[ $key ] = $this->deep_sanitize( $value );
            }
        }
        
        // Sanitize $_REQUEST
        if ( ! empty( $_REQUEST ) ) {
            foreach ( $_REQUEST as $key => $value ) {
                $_REQUEST[ $key ] = $this->deep_sanitize( $value );
            }
        }
    }
    
    /**
     * Deep sanitize values
     */
    private function deep_sanitize( $value ) {
        if ( is_array( $value ) ) {
            return array_map( array( $this, 'deep_sanitize' ), $value );
        }
        
        // Remove null bytes
        $value = str_replace( chr(0), '', $value );
        
        // Sanitize based on content
        if ( is_numeric( $value ) ) {
            return $value + 0; // Convert to proper number type
        } elseif ( is_email( $value ) ) {
            return sanitize_email( $value );
        } elseif ( seems_utf8( $value ) ) {
            return sanitize_text_field( $value );
        } else {
            return esc_sql( $value );
        }
    }
    
    /**
     * Filter queries for safety
     */
    public function filter_queries( $query ) {
        // Log potentially dangerous queries
        $dangerous_patterns = array(
            '/UNION\s+SELECT/i',
            '/SELECT.*FROM.*information_schema/i',
            '/INTO\s+OUTFILE/i',
            '/LOAD_FILE/i',
        );
        
        foreach ( $dangerous_patterns as $pattern ) {
            if ( preg_match( $pattern, $query ) ) {
                $this->log_security_event( 'dangerous_query', array(
                    'query' => $query,
                    'pattern' => $pattern,
                ) );
                
                // In strict mode, block the query
                if ( defined( 'MONEY_QUIZ_STRICT_MODE' ) && MONEY_QUIZ_STRICT_MODE ) {
                    return "SELECT 'Query blocked for security reasons'";
                }
            }
        }
        
        return $query;
    }
    
    /**
     * Restrict MIME types
     */
    public function restrict_mime_types( $mimes ) {
        // Remove potentially dangerous types
        $dangerous_types = array( 'exe', 'com', 'bat', 'cmd', 'pif', 'scr', 'vbs', 'js' );
        
        foreach ( $dangerous_types as $type ) {
            unset( $mimes[ $type ] );
        }
        
        return $mimes;
    }
    
    /**
     * Disable dangerous functions
     */
    private function disable_dangerous_functions() {
        if ( function_exists( 'ini_set' ) ) {
            @ini_set( 'disable_functions', 'exec,system,shell_exec,passthru,eval,assert,create_function' );
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    }
    
    /**
     * Monitor admin actions
     */
    public function monitor_admin_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
        $this->log_security_event( 'admin_action', array(
            'action' => $action,
            'user' => wp_get_current_user()->user_login,
            'time' => current_time( 'mysql' ),
        ) );
    }
    
    /**
     * Load original plugin
     */
    private function load_original_plugin() {
        try {
            // Set flag to indicate safe wrapper is active
            define( 'MONEY_QUIZ_SAFE_WRAPPER_ACTIVE', true );
            
            // Load enhanced components first
            $this->load_enhanced_components();
            
            // Load the original plugin class
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'class.moneyquiz.php' ) ) {
                require_once MONEY_QUIZ_PLUGIN_DIR . 'class.moneyquiz.php';
                
                // Initialize original plugin
                add_action( 'init', array( 'Moneyquiz', 'init' ) );
                
                $this->notice_manager->add_notice(
                    'safe_mode_active',
                    __( 'Money Quiz is running in safe mode with protective wrapper.', 'money-quiz' ),
                    'info'
                );
            } else {
                throw new Exception( 'Original plugin class file not found.' );
            }
            
        } catch ( Exception $e ) {
            $this->error_handler->handle_exception( $e );
            $this->quarantine_mode = true;
            $this->enter_quarantine_mode();
        }
    }
    
    /**
     * Load enhanced components
     */
    private function load_enhanced_components() {
        // Load menu integration
        if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'includes/class-menu-integration.php' ) ) {
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-menu-integration.php';
        }
        
        // Load hybrid integration
        if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'includes/class-hybrid-integration.php' ) ) {
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-hybrid-integration.php';
            \MoneyQuiz\HybridIntegration::instance()->init();
        }
        
        // Load legacy integration
        if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'includes/class-legacy-integration.php' ) ) {
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-legacy-integration.php';
            \MoneyQuiz\Legacy_Integration::init();
        }
    }
    
    /**
     * Enter quarantine mode
     */
    private function enter_quarantine_mode() {
        $this->notice_manager->add_notice(
            'quarantine_mode',
            __( 'Money Quiz is in quarantine mode due to safety violations. Please review the security report.', 'money-quiz' ),
            'error',
            false
        );
        
        // Log quarantine event
        $this->log_security_event( 'quarantine_activated', array(
            'violations' => $this->violations,
            'time' => current_time( 'mysql' ),
        ) );
        
        // Disable plugin functionality
        add_action( 'init', array( $this, 'disable_plugin_functionality' ), 1 );
    }
    
    /**
     * Disable plugin functionality
     */
    public function disable_plugin_functionality() {
        // Remove all plugin actions and filters
        remove_all_actions( 'money_quiz' );
        remove_all_filters( 'money_quiz' );
        
        // Display quarantine message on frontend
        add_shortcode( 'money_quiz', array( $this, 'quarantine_shortcode' ) );
        add_shortcode( 'moneyquiz', array( $this, 'quarantine_shortcode' ) );
    }
    
    /**
     * Quarantine shortcode
     */
    public function quarantine_shortcode() {
        return '<p>' . __( 'This quiz is temporarily unavailable for security reasons.', 'money-quiz' ) . '</p>';
    }
    
    /**
     * Setup monitoring
     */
    private function setup_monitoring() {
        // Monitor for suspicious activity
        add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
        add_action( 'wp_login', array( $this, 'log_successful_login' ), 10, 2 );
        
        // Monitor database changes
        add_action( 'added_option', array( $this, 'log_option_change' ), 10, 2 );
        add_action( 'updated_option', array( $this, 'log_option_change' ), 10, 2 );
        add_action( 'deleted_option', array( $this, 'log_option_change' ), 10, 1 );
        
        // Schedule regular safety checks
        if ( ! wp_next_scheduled( 'money_quiz_safety_check' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_safety_check' );
        }
        add_action( 'money_quiz_safety_check', array( $this, 'run_scheduled_safety_check' ) );
    }
    
    /**
     * Log failed login
     */
    public function log_failed_login( $username ) {
        $this->log_security_event( 'failed_login', array(
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'time' => current_time( 'mysql' ),
        ) );
    }
    
    /**
     * Log successful login
     */
    public function log_successful_login( $user_login, $user ) {
        $this->log_security_event( 'successful_login', array(
            'username' => $user_login,
            'user_id' => $user->ID,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'time' => current_time( 'mysql' ),
        ) );
    }
    
    /**
     * Log option changes
     */
    public function log_option_change( $option, $value = null ) {
        if ( strpos( $option, 'money_quiz' ) !== false || strpos( $option, 'mq_' ) === 0 ) {
            $this->log_security_event( 'option_change', array(
                'option' => $option,
                'action' => current_action(),
                'time' => current_time( 'mysql' ),
            ) );
        }
    }
    
    /**
     * Run scheduled safety check
     */
    public function run_scheduled_safety_check() {
        $this->run_safety_checks();
        
        // Send alert if critical violations found
        if ( $this->quarantine_mode ) {
            $admin_email = get_option( 'admin_email' );
            wp_mail(
                $admin_email,
                __( 'Money Quiz Security Alert', 'money-quiz' ),
                __( 'Critical security violations were detected in the Money Quiz plugin. Please check the security report in the admin panel.', 'money-quiz' )
            );
        }
    }
    
    /**
     * Log security event
     */
    private function log_security_event( $event_type, $details ) {
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'event_type' => $event_type,
            'details' => $details,
            'user_id' => get_current_user_id(),
            'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
        );
        
        // Get existing log
        $log = get_option( 'money_quiz_security_log', array() );
        
        // Add new entry
        $log[] = $log_entry;
        
        // Keep only last 1000 entries
        if ( count( $log ) > 1000 ) {
            $log = array_slice( $log, -1000 );
        }
        
        // Save log
        update_option( 'money_quiz_security_log', $log );
        
        // Also log to error log if debug is enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[Money Quiz Security] %s: %s',
                $event_type,
                json_encode( $details )
            ) );
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'moneyquiz',
            __( 'Security Report', 'money-quiz' ),
            __( 'Security', 'money-quiz' ),
            'manage_options',
            'money-quiz-security',
            array( $this, 'render_security_page' )
        );
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Handle security actions
        if ( isset( $_POST['money_quiz_clear_log'] ) && check_admin_referer( 'money_quiz_clear_log' ) ) {
            delete_option( 'money_quiz_security_log' );
            $this->notice_manager->add_notice(
                'log_cleared',
                __( 'Security log cleared.', 'money-quiz' ),
                'success'
            );
        }
        
        if ( isset( $_POST['money_quiz_exit_quarantine'] ) && check_admin_referer( 'money_quiz_exit_quarantine' ) ) {
            $this->quarantine_mode = false;
            update_option( 'money_quiz_quarantine_override', true );
            $this->notice_manager->add_notice(
                'quarantine_overridden',
                __( 'Quarantine mode overridden. Please fix security issues as soon as possible.', 'money-quiz' ),
                'warning'
            );
        }
    }
    
    /**
     * Render security page
     */
    public function render_security_page() {
        $safety_results = get_option( 'money_quiz_safety_check_results', array() );
        $security_log = get_option( 'money_quiz_security_log', array() );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Money Quiz Security Report', 'money-quiz' ); ?></h1>
            
            <?php if ( $this->quarantine_mode ): ?>
                <div class="notice notice-error">
                    <p><strong><?php _e( 'QUARANTINE MODE ACTIVE', 'money-quiz' ); ?></strong></p>
                    <p><?php _e( 'The plugin is currently in quarantine mode due to critical security violations.', 'money-quiz' ); ?></p>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'money_quiz_exit_quarantine' ); ?>
                        <p>
                            <input type="submit" 
                                   name="money_quiz_exit_quarantine" 
                                   class="button button-secondary" 
                                   value="<?php esc_attr_e( 'Override Quarantine (Use with caution)', 'money-quiz' ); ?>"
                                   onclick="return confirm('<?php esc_attr_e( 'Are you sure? This will expose your site to security risks.', 'money-quiz' ); ?>');">
                        </p>
                    </form>
                </div>
            <?php endif; ?>
            
            <h2><?php _e( 'Safety Check Results', 'money-quiz' ); ?></h2>
            
            <?php if ( ! empty( $safety_results ) ): ?>
                <p><?php printf( __( 'Last check: %s', 'money-quiz' ), $safety_results['timestamp'] ); ?></p>
                
                <?php if ( ! empty( $safety_results['violations'] ) ): ?>
                    <h3><?php _e( 'Violations Found', 'money-quiz' ); ?></h3>
                    
                    <?php foreach ( $safety_results['violations'] as $type => $violations ): ?>
                        <h4><?php echo esc_html( ucwords( str_replace( '_', ' ', $type ) ) ); ?></h4>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e( 'File', 'money-quiz' ); ?></th>
                                    <th><?php _e( 'Issue', 'money-quiz' ); ?></th>
                                    <th><?php _e( 'Details', 'money-quiz' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $violations as $violation ): ?>
                                    <tr>
                                        <td><?php echo esc_html( $violation['file'] ); ?></td>
                                        <td><?php echo esc_html( isset( $violation['type'] ) ? $violation['type'] : $violation['risk'] ); ?></td>
                                        <td><?php echo esc_html( isset( $violation['pattern'] ) ? $violation['pattern'] : ( isset( $violation['match'] ) ? $violation['match'] : '' ) ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <p class="description"><?php _e( 'No violations found in the last check.', 'money-quiz' ); ?></p>
                <?php endif; ?>
                
            <?php else: ?>
                <p class="description"><?php _e( 'No safety check has been performed yet.', 'money-quiz' ); ?></p>
            <?php endif; ?>
            
            <h2><?php _e( 'Recent Security Events', 'money-quiz' ); ?></h2>
            
            <?php if ( ! empty( $security_log ) ): ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'money_quiz_clear_log' ); ?>
                    <p>
                        <input type="submit" 
                               name="money_quiz_clear_log" 
                               class="button button-secondary" 
                               value="<?php esc_attr_e( 'Clear Log', 'money-quiz' ); ?>">
                    </p>
                </form>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e( 'Time', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Event', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Details', 'money-quiz' ); ?></th>
                            <th><?php _e( 'User', 'money-quiz' ); ?></th>
                            <th><?php _e( 'IP', 'money-quiz' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recent_events = array_slice( array_reverse( $security_log ), 0, 50 );
                        foreach ( $recent_events as $event ): 
                        ?>
                            <tr>
                                <td><?php echo esc_html( $event['timestamp'] ); ?></td>
                                <td><?php echo esc_html( $event['event_type'] ); ?></td>
                                <td><?php echo esc_html( json_encode( $event['details'] ) ); ?></td>
                                <td><?php echo $event['user_id'] ? get_userdata( $event['user_id'] )->user_login : '-'; ?></td>
                                <td><?php echo esc_html( $event['ip_address'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="description"><?php _e( 'No security events logged yet.', 'money-quiz' ); ?></p>
            <?php endif; ?>
            
            <h2><?php _e( 'Recommendations', 'money-quiz' ); ?></h2>
            <ul>
                <li><?php _e( 'Regularly update the plugin to the latest version', 'money-quiz' ); ?></li>
                <li><?php _e( 'Review and fix all SQL injection vulnerabilities', 'money-quiz' ); ?></li>
                <li><?php _e( 'Remove hardcoded secrets and API keys', 'money-quiz' ); ?></li>
                <li><?php _e( 'Implement proper input sanitization throughout the plugin', 'money-quiz' ); ?></li>
                <li><?php _e( 'Use WordPress nonces for all forms', 'money-quiz' ); ?></li>
                <li><?php _e( 'Escape all output properly', 'money-quiz' ); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Get instance for external access
     */
    public function get_quarantine_status() {
        return $this->quarantine_mode;
    }
    
    /**
     * Get violations for external access
     */
    public function get_violations() {
        return $this->violations;
    }
}