<?php
/**
 * Safe Mode Activator
 * 
 * Handles plugin activation in safe mode
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Safe Activator Class
 */
class Money_Quiz_Safe_Activator {
    
    /**
     * Activate the plugin in safe mode
     */
    public static function activate() {
        // Set minimum requirements
        $min_wp_version = '5.8';
        $min_php_version = '7.4';
        
        // Check WordPress version
        global $wp_version;
        if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
            deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
            wp_die( sprintf(
                __( 'Money Quiz requires WordPress %s or higher. Please upgrade WordPress and try again.', 'money-quiz' ),
                $min_wp_version
            ) );
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, $min_php_version, '<' ) ) {
            deactivate_plugins( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) );
            wp_die( sprintf(
                __( 'Money Quiz requires PHP %s or higher. Please upgrade PHP and try again.', 'money-quiz' ),
                $min_php_version
            ) );
        }
        
        // Create necessary database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create necessary directories
        self::create_directories();
        
        // Schedule events
        self::schedule_events();
        
        // Clear caches
        wp_cache_flush();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check if we're upgrading or fresh install
        $existing_version = get_option( 'money_quiz_version' );
        
        if ( ! $existing_version ) {
            // Fresh install - create all tables with safe structure
            
            // Master questions table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_master (
                Master_ID bigint(20) NOT NULL AUTO_INCREMENT,
                Question text NOT NULL,
                Answer1 text NOT NULL,
                Answer2 text NOT NULL,
                Answer3 text NOT NULL,
                Answer4 text NOT NULL,
                Priority int(11) DEFAULT 0,
                Status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (Master_ID),
                KEY idx_priority (Priority),
                KEY idx_status (Status)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Prospects table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_prospects (
                Prospect_ID bigint(20) NOT NULL AUTO_INCREMENT,
                First_Name varchar(100),
                Last_Name varchar(100),
                Email varchar(255) NOT NULL,
                Phone varchar(50),
                IP_Address varchar(45),
                User_Agent text,
                Referrer text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (Prospect_ID),
                UNIQUE KEY idx_email (Email),
                KEY idx_created (created_at)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Quiz taken tracking
            $sql = "CREATE TABLE {$wpdb->prefix}mq_taken (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                Master_ID bigint(20) NOT NULL,
                Prospect_ID bigint(20) NOT NULL,
                Answer_Given int(11),
                Time_Taken datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (ID),
                KEY idx_master (Master_ID),
                KEY idx_prospect (Prospect_ID),
                KEY idx_time (Time_Taken)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Results table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_results (
                Result_ID bigint(20) NOT NULL AUTO_INCREMENT,
                Prospect_ID bigint(20) NOT NULL,
                Archetype_ID int(11),
                Score decimal(10,2),
                Date_Taken datetime DEFAULT CURRENT_TIMESTAMP,
                Completion_Time int(11),
                ip_address VARCHAR(45),
                user_agent TEXT,
                PRIMARY KEY (Result_ID),
                KEY idx_prospect (Prospect_ID),
                KEY idx_archetype (Archetype_ID),
                KEY idx_date (Date_Taken)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Coach table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_coach (
                Coach_ID bigint(20) NOT NULL AUTO_INCREMENT,
                Name varchar(255),
                Email varchar(255),
                Phone varchar(50),
                Website varchar(255),
                Description text,
                Photo_URL varchar(500),
                Status varchar(20) DEFAULT 'active',
                PRIMARY KEY (Coach_ID),
                KEY idx_status (Status)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Archetypes table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_archetypes (
                Archetype_ID int(11) NOT NULL AUTO_INCREMENT,
                Name varchar(100) NOT NULL,
                Description text,
                Characteristics text,
                Recommendations text,
                Sort_Order int(11) DEFAULT 0,
                PRIMARY KEY (Archetype_ID),
                KEY idx_sort (Sort_Order)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // CTA table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_cta (
                CTA_ID bigint(20) NOT NULL AUTO_INCREMENT,
                Title varchar(255),
                Content text,
                Button_Text varchar(100),
                Button_URL varchar(500),
                Position varchar(50),
                Status varchar(20) DEFAULT 'active',
                PRIMARY KEY (CTA_ID),
                KEY idx_position (Position),
                KEY idx_status (Status)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Template layout table
            $sql = "CREATE TABLE {$wpdb->prefix}mq_template_layout (
                Layout_ID bigint(20) NOT NULL AUTO_INCREMENT,
                Template_Name varchar(100),
                Section varchar(50),
                Content longtext,
                Settings text,
                Sort_Order int(11) DEFAULT 0,
                PRIMARY KEY (Layout_ID),
                KEY idx_template (Template_Name),
                KEY idx_section (Section)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Safe mode activity log
            $sql = "CREATE TABLE {$wpdb->prefix}mq_activity_log (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED,
                action varchar(50) NOT NULL,
                object_type varchar(50),
                object_id bigint(20) UNSIGNED,
                details longtext,
                ip_address varchar(45),
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY action (action),
                KEY created_at (created_at)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Safe mode settings
            $sql = "CREATE TABLE {$wpdb->prefix}mq_settings (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                setting_key varchar(100) NOT NULL,
                setting_value longtext,
                autoload varchar(20) DEFAULT 'yes',
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) $charset_collate;";
            dbDelta( $sql );
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        // Version info
        add_option( 'money_quiz_version', MONEY_QUIZ_VERSION );
        add_option( 'money_quiz_db_version', MONEY_QUIZ_VERSION );
        
        // Safe mode settings
        add_option( 'money_quiz_safe_mode', true );
        add_option( 'money_quiz_safe_mode_strict', false );
        add_option( 'money_quiz_quarantine_override', false );
        
        // Security settings
        add_option( 'money_quiz_log_security_events', true );
        add_option( 'money_quiz_block_dangerous_queries', true );
        add_option( 'money_quiz_sanitize_inputs', true );
        
        // Email settings
        add_option( 'money_quiz_admin_email_alerts', true );
        add_option( 'money_quiz_alert_email', get_option( 'admin_email' ) );
        
        // Performance settings
        add_option( 'money_quiz_enable_caching', true );
        add_option( 'money_quiz_cache_lifetime', 3600 );
        
        // Feature flags
        add_option( 'money_quiz_enable_monitoring', true );
        add_option( 'money_quiz_enable_auto_updates', false );
        
        // Installation tracking
        add_option( 'money_quiz_installed_at', current_time( 'mysql' ) );
        add_option( 'money_quiz_activation_count', 1 );
        
        // Legacy compatibility
        add_option( 'mq_money_coach_status', 'installed' );
        add_option( 'mq_money_coach_plugin_version', MONEY_QUIZ_VERSION );
    }
    
    /**
     * Create necessary directories
     */
    private static function create_directories() {
        $directories = array(
            MONEY_QUIZ_PLUGIN_DIR . 'logs',
            MONEY_QUIZ_PLUGIN_DIR . 'cache',
            MONEY_QUIZ_PLUGIN_DIR . 'temp',
            MONEY_QUIZ_PLUGIN_DIR . 'backups',
        );
        
        foreach ( $directories as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
                
                // Add .htaccess for security
                $htaccess_content = "Order deny,allow\nDeny from all";
                file_put_contents( $dir . '/.htaccess', $htaccess_content );
                
                // Add index.php for extra security
                $index_content = "<?php // Silence is golden";
                file_put_contents( $dir . '/index.php', $index_content );
            }
        }
    }
    
    /**
     * Schedule events
     */
    private static function schedule_events() {
        // Daily safety check
        if ( ! wp_next_scheduled( 'money_quiz_daily_safety_check' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_daily_safety_check' );
        }
        
        // Hourly monitoring
        if ( ! wp_next_scheduled( 'money_quiz_hourly_monitoring' ) ) {
            wp_schedule_event( time(), 'hourly', 'money_quiz_hourly_monitoring' );
        }
        
        // Weekly cleanup
        if ( ! wp_next_scheduled( 'money_quiz_weekly_cleanup' ) ) {
            wp_schedule_event( time(), 'weekly', 'money_quiz_weekly_cleanup' );
        }
    }
}