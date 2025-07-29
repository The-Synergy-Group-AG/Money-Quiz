<?php
/**
 * Version Reconciliation Initializer for Money Quiz
 * 
 * Initializes and manages the version reconciliation system
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Version Reconciliation Init Class
 */
class Money_Quiz_Version_Reconciliation_Init {
    
    /**
     * @var Money_Quiz_Version_Reconciliation_Init
     */
    private static $instance = null;
    
    /**
     * @var array System components
     */
    private $components = array();
    
    /**
     * @var bool Initialization status
     */
    private $initialized = false;
    
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
        $this->load_components();
    }
    
    /**
     * Load reconciliation components
     */
    private function load_components() {
        $component_files = array(
            'version-manager' => 'class-version-manager.php',
            'version-migration' => 'class-version-migration.php',
            'database-tracker' => 'class-database-version-tracker.php',
            'consistency-checker' => 'class-version-consistency-checker.php',
        );
        
        foreach ( $component_files as $component => $file ) {
            $file_path = MONEY_QUIZ_PLUGIN_DIR . 'includes/' . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize version reconciliation system
     */
    public function init() {
        if ( $this->initialized ) {
            return;
        }
        
        // Initialize components
        $this->components['version_manager'] = Money_Quiz_Version_Manager::instance();
        $this->components['migration'] = Money_Quiz_Version_Migration::instance();
        $this->components['database_tracker'] = Money_Quiz_Database_Version_Tracker::instance();
        $this->components['consistency_checker'] = Money_Quiz_Version_Consistency_Checker::instance();
        
        // Initialize database tracking
        $this->components['database_tracker']->init();
        
        // Schedule consistency checks
        $this->components['consistency_checker']->schedule_checks();
        
        // Add hooks
        $this->add_hooks();
        
        // Check if reconciliation is needed on init
        add_action( 'init', array( $this, 'check_reconciliation_needed' ), 5 );
        
        $this->initialized = true;
    }
    
    /**
     * Add hooks
     */
    private function add_hooks() {
        // Admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
        
        // AJAX handlers
        add_action( 'wp_ajax_mq_check_versions', array( $this, 'ajax_check_versions' ) );
        add_action( 'wp_ajax_mq_reconcile_versions', array( $this, 'ajax_reconcile_versions' ) );
        add_action( 'wp_ajax_mq_run_migration', array( $this, 'ajax_run_migration' ) );
        
        // Admin notices
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        
        // Plugin activation
        register_activation_hook( MONEY_QUIZ_PLUGIN_FILE, array( $this, 'on_activation' ) );
        
        // Plugin update
        add_action( 'upgrader_process_complete', array( $this, 'on_plugin_update' ), 10, 2 );
    }
    
    /**
     * Check if reconciliation is needed
     */
    public function check_reconciliation_needed() {
        // Skip on AJAX requests
        if ( wp_doing_ajax() ) {
            return;
        }
        
        // Check if we should auto-reconcile
        $auto_reconcile = get_option( 'money_quiz_auto_reconcile', true );
        if ( ! $auto_reconcile ) {
            return;
        }
        
        // Check version alignment
        $version_manager = $this->components['version_manager'];
        $mismatches = $version_manager->get_mismatches();
        
        if ( ! empty( $mismatches ) ) {
            // Check if we have critical mismatches
            $has_critical = false;
            foreach ( $mismatches as $mismatch ) {
                if ( $mismatch['severity'] === 'critical' ) {
                    $has_critical = true;
                    break;
                }
            }
            
            if ( $has_critical ) {
                // Store notice for admin
                set_transient( 'money_quiz_version_mismatch_notice', true, HOUR_IN_SECONDS );
                
                // If in admin, attempt auto-reconciliation
                if ( is_admin() && current_user_can( 'manage_options' ) ) {
                    $this->attempt_auto_reconciliation();
                }
            }
        }
    }
    
    /**
     * Attempt automatic reconciliation
     */
    private function attempt_auto_reconciliation() {
        // Check if we've already attempted recently
        $last_attempt = get_transient( 'money_quiz_last_reconciliation_attempt' );
        if ( $last_attempt ) {
            return;
        }
        
        // Set flag to prevent loops
        set_transient( 'money_quiz_last_reconciliation_attempt', time(), HOUR_IN_SECONDS );
        
        // Run reconciliation
        $version_manager = $this->components['version_manager'];
        $results = $version_manager->reconcile_versions();
        
        // Log results
        $this->log_reconciliation_results( $results );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'money-quiz',
            __( 'Version Management', 'money-quiz' ),
            __( 'Version Management', 'money-quiz' ),
            'manage_options',
            'money-quiz-versions',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Get current status
        $version_manager = $this->components['version_manager'];
        $consistency_checker = $this->components['consistency_checker'];
        $database_tracker = $this->components['database_tracker'];
        
        $version_report = $version_manager->get_version_report();
        $consistency_results = $consistency_checker->get_last_check_results();
        $database_integrity = $database_tracker->verify_integrity();
        
        // Include admin template
        include MONEY_QUIZ_PLUGIN_DIR . 'templates/admin/version-management.php';
    }
    
    /**
     * AJAX: Check versions
     */
    public function ajax_check_versions() {
        check_ajax_referer( 'money_quiz_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }
        
        $version_manager = $this->components['version_manager'];
        $report = $version_manager->get_version_report();
        
        wp_send_json_success( $report );
    }
    
    /**
     * AJAX: Reconcile versions
     */
    public function ajax_reconcile_versions() {
        check_ajax_referer( 'money_quiz_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }
        
        $version_manager = $this->components['version_manager'];
        $results = $version_manager->reconcile_versions();
        
        // Log results
        $this->log_reconciliation_results( $results );
        
        wp_send_json_success( $results );
    }
    
    /**
     * AJAX: Run migration
     */
    public function ajax_run_migration() {
        check_ajax_referer( 'money_quiz_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }
        
        $from_version = sanitize_text_field( $_POST['from_version'] ?? '' );
        $to_version = sanitize_text_field( $_POST['to_version'] ?? '4.0.0' );
        
        if ( empty( $from_version ) ) {
            wp_send_json_error( 'Missing from_version parameter' );
        }
        
        $migration = $this->components['migration'];
        $results = $migration->execute_migration( $from_version, $to_version );
        
        wp_send_json_success( $results );
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check for version mismatch notice
        if ( get_transient( 'money_quiz_version_mismatch_notice' ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e( 'Money Quiz Version Mismatch Detected', 'money-quiz' ); ?></strong>
                </p>
                <p>
                    <?php _e( 'Version inconsistencies have been detected in the Money Quiz plugin.', 'money-quiz' ); ?>
                    <a href="<?php echo admin_url( 'admin.php?page=money-quiz-versions' ); ?>">
                        <?php _e( 'View Details', 'money-quiz' ); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient( 'money_quiz_version_mismatch_notice' );
        }
        
        // Check for reconciliation success
        if ( get_transient( 'money_quiz_reconciliation_success' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e( 'Version Reconciliation Complete', 'money-quiz' ); ?></strong>
                </p>
                <p>
                    <?php _e( 'All Money Quiz components have been successfully aligned to version 4.0.', 'money-quiz' ); ?>
                </p>
            </div>
            <?php
            delete_transient( 'money_quiz_reconciliation_success' );
        }
    }
    
    /**
     * On plugin activation
     */
    public function on_activation() {
        // Run initial version detection
        $version_manager = $this->components['version_manager'];
        $versions = $version_manager->detect_versions();
        
        // Store activation data
        update_option( 'money_quiz_activation_versions', $versions );
        update_option( 'money_quiz_activation_time', current_time( 'mysql' ) );
        
        // Run consistency check
        $consistency_checker = $this->components['consistency_checker'];
        $consistency_results = $consistency_checker->run_check();
        
        // If issues found, schedule reconciliation
        if ( $consistency_results['summary']['critical_issues'] > 0 ) {
            wp_schedule_single_event( time() + 60, 'money_quiz_delayed_reconciliation' );
        }
    }
    
    /**
     * On plugin update
     */
    public function on_plugin_update( $upgrader_object, $options ) {
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            $our_plugin = plugin_basename( MONEY_QUIZ_PLUGIN_FILE );
            
            if ( isset( $options['plugins'] ) && in_array( $our_plugin, $options['plugins'] ) ) {
                // Clear version caches
                delete_transient( 'money_quiz_version_cache' );
                
                // Schedule post-update reconciliation
                wp_schedule_single_event( time() + 30, 'money_quiz_post_update_reconciliation' );
            }
        }
    }
    
    /**
     * Log reconciliation results
     */
    private function log_reconciliation_results( $results ) {
        global $wpdb;
        
        // Log to activity table if exists
        $activity_table = $wpdb->prefix . 'mq_activity_log';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$activity_table'" ) ) {
            $wpdb->insert(
                $activity_table,
                array(
                    'action' => 'version_reconciliation',
                    'details' => json_encode( $results ),
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%d', '%s', '%s' )
            );
        }
        
        // Set success transient if no errors
        if ( empty( $results['errors'] ) ) {
            set_transient( 'money_quiz_reconciliation_success', true, MINUTE_IN_SECONDS );
        }
        
        // Log to error log if debug enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[Money Quiz Reconciliation] Success: %d, Errors: %d',
                count( $results['success'] ?? array() ),
                count( $results['errors'] ?? array() )
            ) );
        }
    }
    
    /**
     * Get reconciliation status
     */
    public function get_status() {
        return array(
            'initialized' => $this->initialized,
            'components' => array_keys( $this->components ),
            'version_report' => $this->components['version_manager']->get_version_report(),
            'last_consistency_check' => $this->components['consistency_checker']->get_last_check_results(),
        );
    }
    
    /**
     * Force reconciliation
     */
    public function force_reconciliation() {
        // Clear caches
        delete_transient( 'money_quiz_last_reconciliation_attempt' );
        delete_transient( 'money_quiz_version_cache' );
        
        // Run reconciliation
        $version_manager = $this->components['version_manager'];
        return $version_manager->reconcile_versions();
    }
}