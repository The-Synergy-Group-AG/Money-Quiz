<?php
/**
 * Money Quiz Plugin - Component Integration
 * Worker 10: Final Integration and Bootstrap
 * 
 * Integrates all architectural components and provides the main
 * plugin bootstrap file for the new MVC architecture.
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MONEY_QUIZ_VERSION', '4.0.0' );
define( 'MONEY_QUIZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MONEY_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MONEY_QUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-autoloader.php';

// Initialize autoloader
Autoloader::init();

/**
 * Main Plugin Bootstrap Class
 * 
 * Initializes and coordinates all plugin components
 */
class MoneyQuizPlugin {
    
    /**
     * Plugin instance
     * 
     * @var MoneyQuizPlugin
     */
    private static $instance = null;
    
    /**
     * Plugin container
     * 
     * @var Core\Container
     */
    private $container;
    
    /**
     * Plugin core
     * 
     * @var Core\Plugin
     */
    private $plugin;
    
    /**
     * Get plugin instance
     * 
     * @return MoneyQuizPlugin
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_container();
        $this->register_services();
        $this->init_plugin();
    }
    
    /**
     * Initialize dependency injection container
     */
    private function init_container() {
        $this->container = new Core\Container();
        
        // Register container as singleton
        $this->container->singleton( 'container', function() {
            return $this->container;
        });
    }
    
    /**
     * Register all services
     */
    private function register_services() {
        // Database Service
        $this->container->singleton( 'database', function() {
            return new Services\DatabaseService();
        });
        
        // Validation Service
        $this->container->singleton( 'validation', function() {
            return new Services\ValidationService();
        });
        
        // Email Service
        $this->container->singleton( 'email', function( $container ) {
            return new Services\EmailService( 
                $container->get( 'validation' )
            );
        });
        
        // Quiz Service
        $this->container->singleton( 'quiz', function( $container ) {
            return new Services\QuizService(
                $container->get( 'database' ),
                $container->get( 'validation' )
            );
        });
        
        // Settings Service
        $this->container->singleton( 'settings', function( $container ) {
            return new Services\SettingsService(
                $container->get( 'database' )
            );
        });
        
        // Logger Service
        $this->container->singleton( 'logger', function( $container ) {
            return new Services\LoggerService(
                $container->get( 'database' )
            );
        });
    }
    
    /**
     * Initialize plugin core
     */
    private function init_plugin() {
        $this->plugin = new Core\Plugin( __FILE__ );
        $this->plugin->set_container( $this->container );
        
        // Initialize models with database service
        Models\BaseModel::set_database( $this->container->get( 'database' ) );
        
        // Register hooks
        $this->register_activation_hooks();
        $this->register_deactivation_hooks();
        
        // Initialize plugin
        $this->plugin->init();
    }
    
    /**
     * Register activation hooks
     */
    private function register_activation_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }
    
    /**
     * Register deactivation hooks
     */
    private function register_deactivation_hooks() {
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create/update database tables
        $installer = new Core\Installer();
        $installer->install();
        
        // Flush rewrite rules
        $this->plugin->flush_rewrite_rules();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Set default options
        $this->set_default_options();
        
        // Log activation
        $this->container->get( 'logger' )->log( 'plugin_activated', array(
            'version' => MONEY_QUIZ_VERSION
        ));
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        $this->clear_cron_jobs();
        
        // Flush cache
        Utilities\CacheUtil::clear();
        
        // Log deactivation
        $this->container->get( 'logger' )->log( 'plugin_deactivated', array(
            'version' => MONEY_QUIZ_VERSION
        ));
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Daily cleanup
        if ( ! wp_next_scheduled( 'money_quiz_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_daily_cleanup' );
        }
        
        // Hourly email queue processing
        if ( ! wp_next_scheduled( 'money_quiz_process_email_queue' ) ) {
            wp_schedule_event( time(), 'hourly', 'money_quiz_process_email_queue' );
        }
    }
    
    /**
     * Clear cron jobs
     */
    private function clear_cron_jobs() {
        wp_clear_scheduled_hook( 'money_quiz_daily_cleanup' );
        wp_clear_scheduled_hook( 'money_quiz_process_email_queue' );
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'money_quiz_version' => MONEY_QUIZ_VERSION,
            'money_quiz_email_provider' => 'default',
            'money_quiz_require_email' => 'yes',
            'money_quiz_collect_name' => 'yes',
            'money_quiz_collect_phone' => 'no',
            'money_quiz_show_progress' => 'yes',
            'money_quiz_randomize_questions' => 'no',
            'money_quiz_enable_retakes' => 'yes',
            'money_quiz_retake_delay' => 24,
            'money_quiz_debug_mode' => 'no'
        );
        
        foreach ( $defaults as $option => $value ) {
            add_option( $option, $value );
        }
    }
    
    /**
     * Get container
     * 
     * @return Core\Container
     */
    public function get_container() {
        return $this->container;
    }
    
    /**
     * Get service
     * 
     * @param string $service Service name
     * @return mixed
     */
    public function get_service( $service ) {
        return $this->container->get( $service );
    }
}

/**
 * Plugin Installer Class
 * 
 * Handles database installation and updates
 */
namespace MoneyQuiz\Core;

class Installer {
    
    /**
     * Install plugin database
     */
    public function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Get table names
        $tables = $this->get_table_schemas();
        
        // Create tables
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        foreach ( $tables as $table ) {
            dbDelta( $table );
        }
        
        // Insert default data
        $this->insert_default_data();
        
        // Update version
        update_option( 'money_quiz_db_version', MONEY_QUIZ_VERSION );
    }
    
    /**
     * Get table schemas
     * 
     * @return array
     */
    private function get_table_schemas() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = array();
        
        // Prospects table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_prospects (
            Prospect_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            Email varchar(255) NOT NULL,
            FirstName varchar(100) DEFAULT '',
            LastName varchar(100) DEFAULT '',
            Phone varchar(50) DEFAULT '',
            IP_Address varchar(45) DEFAULT '',
            User_Agent text,
            Referrer text,
            Status varchar(20) DEFAULT 'active',
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Prospect_ID),
            UNIQUE KEY email (Email),
            KEY status (Status),
            KEY created (Created)
        ) $charset_collate;";
        
        // Quiz taken table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_taken (
            Taken_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            Prospect_ID bigint(20) UNSIGNED NOT NULL,
            Quiz_ID int(11) DEFAULT 1,
            Started datetime DEFAULT CURRENT_TIMESTAMP,
            Completed datetime DEFAULT NULL,
            Score_Total decimal(10,2) DEFAULT 0,
            Archetype_ID int(11) DEFAULT NULL,
            Status varchar(20) DEFAULT 'incomplete',
            Duration int(11) DEFAULT NULL,
            PRIMARY KEY (Taken_ID),
            KEY prospect_id (Prospect_ID),
            KEY archetype_id (Archetype_ID),
            KEY status (Status),
            KEY completed (Completed)
        ) $charset_collate;";
        
        // Questions table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_questions (
            Question_ID int(11) NOT NULL AUTO_INCREMENT,
            Question_Text text NOT NULL,
            Question_Category varchar(100) DEFAULT '',
            Question_Type varchar(50) DEFAULT 'scale',
            Display_Order int(11) DEFAULT 0,
            Is_Active tinyint(1) DEFAULT 1,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Question_ID),
            KEY category (Question_Category),
            KEY is_active (Is_Active),
            KEY display_order (Display_Order)
        ) $charset_collate;";
        
        // Results table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_results (
            Result_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            Taken_ID bigint(20) UNSIGNED NOT NULL,
            Prospect_ID bigint(20) UNSIGNED NOT NULL,
            Question_ID int(11) NOT NULL,
            Answer_Value int(11) NOT NULL,
            Answer_Text text,
            Weight decimal(5,2) DEFAULT 1.00,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (Result_ID),
            KEY taken_id (Taken_ID),
            KEY prospect_id (Prospect_ID),
            KEY question_id (Question_ID)
        ) $charset_collate;";
        
        // Archetypes table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_archetypes (
            Archetype_ID int(11) NOT NULL AUTO_INCREMENT,
            Name varchar(100) NOT NULL,
            Description text,
            Score_Range_Min decimal(10,2) DEFAULT 0,
            Score_Range_Max decimal(10,2) DEFAULT 100,
            Color varchar(7) DEFAULT '#000000',
            Icon varchar(100) DEFAULT '',
            Recommendations text,
            Display_Order int(11) DEFAULT 0,
            Is_Active tinyint(1) DEFAULT 1,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Archetype_ID),
            KEY is_active (Is_Active),
            KEY score_range (Score_Range_Min, Score_Range_Max)
        ) $charset_collate;";
        
        // Email log table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_email_log (
            Log_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            Email_To varchar(255) NOT NULL,
            Email_Type varchar(50) NOT NULL,
            Subject varchar(255) DEFAULT '',
            Result_ID bigint(20) UNSIGNED DEFAULT NULL,
            Provider varchar(50) DEFAULT 'default',
            Status varchar(20) DEFAULT 'pending',
            Error_Message text,
            Sent_At datetime DEFAULT NULL,
            Opened_At datetime DEFAULT NULL,
            Clicked_At datetime DEFAULT NULL,
            Metadata text,
            PRIMARY KEY (Log_ID),
            KEY email_to (Email_To),
            KEY email_type (Email_Type),
            KEY status (Status),
            KEY sent_at (Sent_At)
        ) $charset_collate;";
        
        // Activity log table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_activity_log (
            Activity_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            Action varchar(100) NOT NULL,
            Object_Type varchar(50) DEFAULT NULL,
            Object_ID bigint(20) UNSIGNED DEFAULT NULL,
            User_ID bigint(20) UNSIGNED DEFAULT NULL,
            Prospect_ID bigint(20) UNSIGNED DEFAULT NULL,
            Data text,
            IP_Address varchar(45) DEFAULT '',
            User_Agent text,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (Activity_ID),
            KEY action (Action),
            KEY object (Object_Type, Object_ID),
            KEY user_id (User_ID),
            KEY prospect_id (Prospect_ID),
            KEY created (Created)
        ) $charset_collate;";
        
        // Settings table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_settings (
            Setting_ID int(11) NOT NULL AUTO_INCREMENT,
            Setting_Key varchar(100) NOT NULL,
            Setting_Value longtext,
            Setting_Type varchar(20) DEFAULT 'string',
            Is_Autoloaded tinyint(1) DEFAULT 0,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Setting_ID),
            UNIQUE KEY setting_key (Setting_Key),
            KEY is_autoloaded (Is_Autoloaded)
        ) $charset_collate;";
        
        // CTA table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_cta (
            CTA_ID int(11) NOT NULL AUTO_INCREMENT,
            Name varchar(100) NOT NULL,
            Type varchar(50) DEFAULT 'button',
            Content text,
            Button_Text varchar(255) DEFAULT '',
            Button_URL text,
            Target_Archetype int(11) DEFAULT NULL,
            Display_Rules text,
            Style_Settings text,
            Conversion_Count int(11) DEFAULT 0,
            View_Count int(11) DEFAULT 0,
            Is_Active tinyint(1) DEFAULT 1,
            Display_Order int(11) DEFAULT 0,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (CTA_ID),
            KEY target_archetype (Target_Archetype),
            KEY is_active (Is_Active)
        ) $charset_collate;";
        
        // Error log table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_error_log (
            Error_ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            Error_Type varchar(100) NOT NULL,
            Error_Message text NOT NULL,
            Error_File varchar(255) DEFAULT '',
            Error_Line int(11) DEFAULT 0,
            Error_Context text,
            User_ID bigint(20) UNSIGNED DEFAULT NULL,
            URL text,
            Stack_Trace text,
            Is_Resolved tinyint(1) DEFAULT 0,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Resolved_At datetime DEFAULT NULL,
            PRIMARY KEY (Error_ID),
            KEY error_type (Error_Type),
            KEY is_resolved (Is_Resolved),
            KEY created (Created)
        ) $charset_collate;";
        
        // Blacklist table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mq_blacklist (
            Blacklist_ID int(11) NOT NULL AUTO_INCREMENT,
            Type varchar(20) NOT NULL,
            Value varchar(255) NOT NULL,
            Reason text,
            Added_By bigint(20) UNSIGNED DEFAULT NULL,
            Is_Active tinyint(1) DEFAULT 1,
            Created datetime DEFAULT CURRENT_TIMESTAMP,
            Updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Blacklist_ID),
            UNIQUE KEY type_value (Type, Value),
            KEY is_active (Is_Active)
        ) $charset_collate;";
        
        return $tables;
    }
    
    /**
     * Insert default data
     */
    private function insert_default_data() {
        global $wpdb;
        
        // Check if data already exists
        $has_archetypes = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mq_archetypes" );
        
        if ( $has_archetypes ) {
            return;
        }
        
        // Insert default archetypes
        $archetypes = array(
            array(
                'Name' => 'The Spender',
                'Description' => 'You enjoy spending money and living in the moment.',
                'Score_Range_Min' => 0,
                'Score_Range_Max' => 25,
                'Color' => '#e74c3c',
                'Display_Order' => 1
            ),
            array(
                'Name' => 'The Saver',
                'Description' => 'You prioritize saving and financial security.',
                'Score_Range_Min' => 26,
                'Score_Range_Max' => 50,
                'Color' => '#27ae60',
                'Display_Order' => 2
            ),
            array(
                'Name' => 'The Investor',
                'Description' => 'You focus on growing wealth through investments.',
                'Score_Range_Min' => 51,
                'Score_Range_Max' => 75,
                'Color' => '#3498db',
                'Display_Order' => 3
            ),
            array(
                'Name' => 'The Balancer',
                'Description' => 'You maintain a healthy balance between spending and saving.',
                'Score_Range_Min' => 76,
                'Score_Range_Max' => 100,
                'Color' => '#9b59b6',
                'Display_Order' => 4
            )
        );
        
        foreach ( $archetypes as $archetype ) {
            $wpdb->insert( "{$wpdb->prefix}mq_archetypes", $archetype );
        }
        
        // Insert sample questions
        $questions = array(
            array(
                'Question_Text' => 'I prefer to save money rather than spend it',
                'Question_Category' => 'Saving',
                'Display_Order' => 1
            ),
            array(
                'Question_Text' => 'I regularly invest in stocks or other financial instruments',
                'Question_Category' => 'Investing',
                'Display_Order' => 2
            ),
            array(
                'Question_Text' => 'I have a budget and stick to it',
                'Question_Category' => 'Budgeting',
                'Display_Order' => 3
            ),
            array(
                'Question_Text' => 'I enjoy shopping and buying new things',
                'Question_Category' => 'Spending',
                'Display_Order' => 4
            ),
            array(
                'Question_Text' => 'I have an emergency fund for unexpected expenses',
                'Question_Category' => 'Security',
                'Display_Order' => 5
            )
        );
        
        foreach ( $questions as $question ) {
            $wpdb->insert( "{$wpdb->prefix}mq_questions", $question );
        }
    }
}

/**
 * Autoloader Class
 */
namespace MoneyQuiz;

class Autoloader {
    
    /**
     * Initialize autoloader
     */
    public static function init() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }
    
    /**
     * Autoload classes
     * 
     * @param string $class Class name
     */
    public static function autoload( $class ) {
        // Check if it's our namespace
        if ( strpos( $class, 'MoneyQuiz\\' ) !== 0 ) {
            return;
        }
        
        // Remove namespace prefix
        $class = str_replace( 'MoneyQuiz\\', '', $class );
        
        // Convert namespace separators to directory separators
        $class = str_replace( '\\', '/', $class );
        
        // Map namespaces to directories
        $mappings = array(
            'Core/' => 'includes/core/',
            'Controllers/' => 'includes/controllers/',
            'Services/' => 'includes/services/',
            'Models/' => 'includes/models/',
            'Utilities/' => 'includes/utilities/',
            'Admin/' => 'admin/',
            'Frontend/' => 'frontend/',
            'API/' => 'includes/api/'
        );
        
        // Find the right directory
        foreach ( $mappings as $namespace => $directory ) {
            if ( strpos( $class, rtrim( $namespace, '/' ) ) === 0 ) {
                $file = MONEY_QUIZ_PLUGIN_DIR . $directory . 'class-' . strtolower( str_replace( $namespace, '', $class ) ) . '.php';
                
                if ( file_exists( $file ) ) {
                    require_once $file;
                    return;
                }
            }
        }
        
        // Try generic includes directory
        $file = MONEY_QUIZ_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '/', '-', $class ) ) . '.php';
        
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}

// Initialize plugin
function money_quiz_init() {
    return MoneyQuizPlugin::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'money_quiz_init' );

// Global access function
if ( ! function_exists( 'money_quiz' ) ) {
    /**
     * Get plugin instance
     * 
     * @return MoneyQuizPlugin
     */
    function money_quiz() {
        return MoneyQuizPlugin::get_instance();
    }
}

// Service access helpers
if ( ! function_exists( 'money_quiz_service' ) ) {
    /**
     * Get service from container
     * 
     * @param string $service Service name
     * @return mixed
     */
    function money_quiz_service( $service ) {
        return money_quiz()->get_service( $service );
    }
}