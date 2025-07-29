<?php
/**
 * Plugin Name: Money Quiz
 * Plugin URI: https://thesynergygroup.ch
 * Description: Enhanced Money Quiz plugin with comprehensive safety features and modern architecture
 * Version: 4.0.0
 * Author: The Synergy Group AG
 * Author URI: https://thesynergygroup.ch
 * License: GPL v2 or later
 * Text Domain: money-quiz
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 * @author Andre@thesynergygroup.ch
 * 
 * This is the single entry point for the Money Quiz plugin.
 * It intelligently loads either the safe wrapper or original plugin based on configuration.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MONEY_QUIZ_VERSION', '4.0.0' );
define( 'MONEY_QUIZ_PLUGIN_FILE', __FILE__ );
define( 'MONEY_QUIZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MONEY_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MONEY_QUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load version bootstrap early
require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/bootstrap/class-version-bootstrap.php';

// Load plugin default configuration
if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'config/plugin-defaults.php' ) ) {
    require_once MONEY_QUIZ_PLUGIN_DIR . 'config/plugin-defaults.php';
}

// Load isolated environment configuration if enabled
if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'config/isolated-environment.php' ) ) {
    require_once MONEY_QUIZ_PLUGIN_DIR . 'config/isolated-environment.php';
}

// Define safety mode (can be toggled via wp-config.php)
if ( ! defined( 'MONEY_QUIZ_SAFE_MODE' ) ) {
    define( 'MONEY_QUIZ_SAFE_MODE', true ); // Default to safe mode
}

// Define legacy mode (for backwards compatibility)
if ( ! defined( 'MONEY_QUIZ_LEGACY_MODE' ) ) {
    define( 'MONEY_QUIZ_LEGACY_MODE', false );
}

/**
 * Main plugin loader class
 * 
 * This class determines which version of the plugin to load based on
 * configuration and safety checks.
 */
class Money_Quiz_Loader {
    
    /**
     * @var Money_Quiz_Loader The single instance of the class
     */
    private static $instance = null;
    
    /**
     * @var string The active mode (safe, legacy, or hybrid)
     */
    private $mode = 'safe';
    
    /**
     * @var array Load status and messages
     */
    private $status = array();
    
    /**
     * Main instance
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
        // Run version reconciliation first
        $this->reconcile_versions();
        
        $this->determine_mode();
        $this->init();
    }
    
    /**
     * Determine which mode to run in
     */
    private function determine_mode() {
        // Check if we should force legacy mode
        if ( MONEY_QUIZ_LEGACY_MODE ) {
            $this->mode = 'legacy';
            return;
        }
        
        // Check if safe mode is explicitly disabled
        if ( defined( 'MONEY_QUIZ_SAFE_MODE' ) && ! MONEY_QUIZ_SAFE_MODE ) {
            $this->mode = 'legacy';
            return;
        }
        
        // Check environment capabilities
        if ( $this->can_run_safe_mode() ) {
            $this->mode = 'safe';
        } else {
            $this->mode = 'hybrid'; // Fallback with some protections
        }
    }
    
    /**
     * Check if environment supports safe mode
     */
    private function can_run_safe_mode() {
        $requirements = array(
            'php_version' => version_compare( PHP_VERSION, '7.4', '>=' ),
            'wp_version' => version_compare( get_bloginfo( 'version' ), '5.8', '>=' ),
            'safe_files' => $this->check_safe_files(),
        );
        
        foreach ( $requirements as $check => $result ) {
            if ( ! $result ) {
                $this->status[ $check ] = false;
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if safe mode files exist
     */
    private function check_safe_files() {
        $required_files = array(
            'includes/class-error-handler.php',
            'includes/class-notice-manager.php',
            'includes/class-dependency-monitor.php',
            'includes/class-safe-wrapper.php',
        );
        
        foreach ( $required_files as $file ) {
            if ( ! file_exists( MONEY_QUIZ_PLUGIN_DIR . $file ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Initialize the appropriate version
     */
    private function init() {
        // Register activation/deactivation hooks
        register_activation_hook( MONEY_QUIZ_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( MONEY_QUIZ_PLUGIN_FILE, array( $this, 'deactivate' ) );
        register_uninstall_hook( MONEY_QUIZ_PLUGIN_FILE, array( __CLASS__, 'uninstall' ) );
        
        // Load the appropriate version
        add_action( 'plugins_loaded', array( $this, 'load_plugin' ), 5 );
        
        // Add admin notices if needed
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }
    
    /**
     * Load the plugin
     */
    public function load_plugin() {
        // Load common utilities first
        $this->load_utilities();
        
        switch ( $this->mode ) {
            case 'safe':
                $this->load_safe_mode();
                break;
                
            case 'legacy':
                $this->load_legacy_mode();
                break;
                
            case 'hybrid':
                $this->load_hybrid_mode();
                break;
        }
        
        // Fire action for extenders
        do_action( 'money_quiz_loaded', $this->mode );
    }
    
    /**
     * Load common utilities
     */
    private function load_utilities() {
        // Load upgrade handler
        require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-upgrade-handler.php';
        
        // Load version reconciliation system
        require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-version-reconciliation-init.php';
        
        // Initialize version reconciliation
        $reconciliation = Money_Quiz_Version_Reconciliation_Init::instance();
        $reconciliation->init();
        
        // Check for upgrades
        Money_Quiz_Upgrade_Handler::instance()->maybe_upgrade();
    }
    
    /**
     * Load safe mode
     */
    private function load_safe_mode() {
        try {
            // Load error handler first
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-error-handler.php';
            $error_handler = new MoneyQuiz_Error_Handler();
            $error_handler->register();
            
            // Load notice manager
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-notice-manager.php';
            
            // Load dependency monitor
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-dependency-monitor.php';
            
            // Load safe wrapper
            require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-safe-wrapper.php';
            
            // Initialize safe wrapper
            MoneyQuiz_Safe_Wrapper::instance()->init();
            
            $this->status['loaded'] = 'safe';
            
        } catch ( Exception $e ) {
            $this->status['error'] = $e->getMessage();
            $this->fallback_to_hybrid();
        }
    }
    
    /**
     * Load legacy mode
     */
    private function load_legacy_mode() {
        // Add basic protections even in legacy mode
        $this->add_legacy_protections();
        
        // Load original plugin file
        if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'legacy/moneyquiz-original.php' ) ) {
            require_once MONEY_QUIZ_PLUGIN_DIR . 'legacy/moneyquiz-original.php';
            $this->status['loaded'] = 'legacy';
        } else {
            $this->status['error'] = 'Legacy plugin file not found';
        }
    }
    
    /**
     * Load hybrid mode
     */
    private function load_hybrid_mode() {
        // Load minimal protections
        $this->add_minimal_protections();
        
        // Load legacy with basic wrapper
        $this->load_legacy_mode();
        
        $this->status['loaded'] = 'hybrid';
        $this->status['warning'] = 'Running in hybrid mode with limited protections';
    }
    
    /**
     * Fallback to hybrid mode
     */
    private function fallback_to_hybrid() {
        $this->mode = 'hybrid';
        $this->load_hybrid_mode();
    }
    
    /**
     * Add legacy protections
     */
    private function add_legacy_protections() {
        // Basic input sanitization
        add_action( 'init', array( $this, 'sanitize_inputs' ), 1 );
        
        // Basic error suppression
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            @ini_set( 'display_errors', 0 );
        }
    }
    
    /**
     * Add minimal protections
     */
    private function add_minimal_protections() {
        // Very basic protections for hybrid mode
        add_action( 'init', array( $this, 'basic_security' ), 1 );
    }
    
    /**
     * Sanitize inputs
     */
    public function sanitize_inputs() {
        if ( ! empty( $_POST ) ) {
            $_POST = $this->sanitize_array( $_POST );
        }
        if ( ! empty( $_GET ) ) {
            $_GET = $this->sanitize_array( $_GET );
        }
        if ( ! empty( $_REQUEST ) ) {
            $_REQUEST = $this->sanitize_array( $_REQUEST );
        }
    }
    
    /**
     * Sanitize array recursively
     */
    private function sanitize_array( $array ) {
        foreach ( $array as $key => $value ) {
            if ( is_array( $value ) ) {
                $array[ $key ] = $this->sanitize_array( $value );
            } else {
                $array[ $key ] = sanitize_text_field( $value );
            }
        }
        return $array;
    }
    
    /**
     * Basic security measures
     */
    public function basic_security() {
        // Remove version from generator
        remove_action( 'wp_head', 'wp_generator' );
        
        // Basic nonce verification helper
        if ( ! function_exists( 'mq_verify_nonce' ) ) {
            function mq_verify_nonce( $nonce, $action ) {
                return wp_verify_nonce( $nonce, $action );
            }
        }
    }
    
    /**
     * Reconcile plugin versions
     */
    private function reconcile_versions() {
        // Use the version bootstrap
        $bootstrap_result = \MoneyQuiz\Bootstrap\VersionBootstrap::run();
        
        if ( ! $bootstrap_result ) {
            $results = \MoneyQuiz\Bootstrap\VersionBootstrap::get_results();
            
            // Log version issues
            if ( isset( $results['error'] ) ) {
                error_log( 'Money Quiz Version Error: ' . $results['error'] );
            }
            
            // Store reconciliation status
            $this->status['version_issues'] = $results;
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Version issues notification
        if ( isset( $this->status['version_issues'] ) && ! $this->status['version_issues']['consistent'] ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e( 'Money Quiz Version Issue:', 'money-quiz' ); ?></strong>
                    <?php _e( 'Version inconsistencies detected across plugin components.', 'money-quiz' ); ?>
                    <a href="<?php echo admin_url( 'admin.php?page=money-quiz-version-management' ); ?>">
                        <?php _e( 'Fix Version Issues', 'money-quiz' ); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Mode notification
        if ( $this->mode === 'hybrid' ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e( 'Money Quiz:', 'money-quiz' ); ?></strong>
                    <?php _e( 'Running in hybrid mode with limited safety features. Some protections may not be available.', 'money-quiz' ); ?>
                </p>
            </div>
            <?php
        }
        
        // Error notification
        if ( isset( $this->status['error'] ) ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e( 'Money Quiz Error:', 'money-quiz' ); ?></strong>
                    <?php echo esc_html( $this->status['error'] ); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary directories
        $directories = array(
            MONEY_QUIZ_PLUGIN_DIR . 'logs',
            MONEY_QUIZ_PLUGIN_DIR . 'cache',
            MONEY_QUIZ_PLUGIN_DIR . 'temp',
        );
        
        foreach ( $directories as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
        
        // Set default options
        add_option( 'money_quiz_version', MONEY_QUIZ_VERSION );
        add_option( 'money_quiz_mode', $this->mode );
        add_option( 'money_quiz_activated', current_time( 'mysql' ) );
        
        // Run mode-specific activation
        if ( $this->mode === 'safe' || $this->mode === 'hybrid' ) {
            // Safe activation
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'includes/class-safe-activator.php' ) ) {
                require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-safe-activator.php';
                Money_Quiz_Safe_Activator::activate();
            }
        } else {
            // Legacy activation
            if ( class_exists( 'Moneyquiz' ) && method_exists( 'Moneyquiz', 'mq_plugin_activation' ) ) {
                Moneyquiz::mq_plugin_activation();
            }
        }
        
        // Clear any caches
        wp_cache_flush();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Run mode-specific deactivation
        if ( $this->mode === 'safe' || $this->mode === 'hybrid' ) {
            // Safe deactivation
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'includes/class-safe-deactivator.php' ) ) {
                require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-safe-deactivator.php';
                Money_Quiz_Safe_Deactivator::deactivate();
            }
        } else {
            // Legacy deactivation
            if ( class_exists( 'Moneyquiz' ) && method_exists( 'Moneyquiz', 'mq_plugin_deactivation' ) ) {
                Moneyquiz::mq_plugin_deactivation();
            }
        }
        
        // Clean up transients
        delete_transient( 'money_quiz_notices' );
        delete_transient( 'money_quiz_dependency_check' );
        
        // Clear scheduled events
        wp_clear_scheduled_hook( 'money_quiz_daily_maintenance' );
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Check if we should preserve data
        $preserve_data = get_option( 'money_quiz_preserve_data_on_uninstall', false );
        
        if ( ! $preserve_data ) {
            // Load uninstaller
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . 'includes/class-uninstaller.php' ) ) {
                require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-uninstaller.php';
                Money_Quiz_Uninstaller::uninstall();
            }
        }
    }
    
    /**
     * Get current mode
     */
    public function get_mode() {
        return $this->mode;
    }
    
    /**
     * Get status
     */
    public function get_status() {
        return $this->status;
    }
}

// Load legacy integration components first
require_once __DIR__ . '/includes/class-legacy-integration.php';

// Check if Composer autoloader exists
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
    
    // Initialize autoloader optimizer
    if ( class_exists( '\MoneyQuiz\Core\AutoloaderOptimizer' ) ) {
        \MoneyQuiz\Core\AutoloaderOptimizer::init();
    }
    
    // Initialize the modern architecture
    add_action( 'plugins_loaded', function() {
        try {
            // Initialize modern architecture
            $plugin = \MoneyQuiz\Core\Plugin::instance();
            $plugin->run();
            
            // Also load legacy functionality until fully migrated
            Money_Quiz_Loader::instance();
            
            // Initialize legacy integration
            if ( class_exists( '\MoneyQuiz\Integration\Legacy_Integration' ) ) {
                \MoneyQuiz\Integration\Legacy_Integration::instance()->init();
            }
            
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Money Quiz Modern Architecture Error: ' . $e->getMessage() );
            }
            // Fall back to legacy loader only if modern architecture fails
            Money_Quiz_Loader::instance();
        }
    }, 5 );
} else {
    // Fall back to legacy loader if autoloader doesn't exist
    add_action( 'plugins_loaded', function() {
        Money_Quiz_Loader::instance();
        
        // Still try to load legacy integration
        if ( class_exists( '\MoneyQuiz\Integration\Legacy_Integration' ) ) {
            \MoneyQuiz\Integration\Legacy_Integration::instance()->init();
        }
    }, 1 );
    
    // Show admin notice about missing dependencies
    add_action( 'admin_notices', function() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e( 'Money Quiz:', 'money-quiz' ); ?></strong>
                <?php _e( 'Composer dependencies not installed. Running in compatibility mode. Run "composer install" for full functionality.', 'money-quiz' ); ?>
            </p>
        </div>
        <?php
    });
}

// Provide global access function
if ( ! function_exists( 'money_quiz' ) ) {
    function money_quiz() {
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            return \MoneyQuiz\Core\Plugin::instance();
        }
        return Money_Quiz_Loader::instance();
    }
}