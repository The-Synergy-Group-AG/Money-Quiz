<?php
/**
 * Upgrade Handler for Money Quiz
 * 
 * Handles all version upgrades and database migrations
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Upgrade Handler Class
 */
class Money_Quiz_Upgrade_Handler {
    
    /**
     * @var Money_Quiz_Upgrade_Handler
     */
    private static $instance = null;
    
    /**
     * @var string Current database version
     */
    private $current_version;
    
    /**
     * @var string Target version
     */
    private $target_version;
    
    /**
     * @var array Upgrade routines
     */
    private $upgrades = array();
    
    /**
     * @var array Upgrade log
     */
    private $log = array();
    
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
        $this->target_version = MONEY_QUIZ_VERSION;
        $this->init_upgrades();
    }
    
    /**
     * Initialize upgrade routines
     */
    private function init_upgrades() {
        // Define upgrade paths
        $this->upgrades = array(
            '1.4' => array( $this, 'upgrade_to_2_0' ),
            '2.0' => array( $this, 'upgrade_to_3_0' ),
            '3.0' => array( $this, 'upgrade_to_3_3' ),
            '3.3' => array( $this, 'upgrade_to_4_0' ),
        );
    }
    
    /**
     * Check and run upgrades if needed
     */
    public function maybe_upgrade() {
        // Get current version from database
        $this->current_version = $this->get_current_version();
        
        // If no version or older version, run upgrades
        if ( ! $this->current_version || version_compare( $this->current_version, $this->target_version, '<' ) ) {
            $this->run_upgrades();
        }
    }
    
    /**
     * Get current version from database
     */
    private function get_current_version() {
        // Check multiple possible version storage locations
        $version_checks = array(
            'money_quiz_version' => get_option( 'money_quiz_version' ),
            'mq_money_coach_plugin_version' => get_option( 'mq_money_coach_plugin_version' ),
            'moneyquiz_version' => get_option( 'moneyquiz_version' ),
        );
        
        // Try to determine the most likely version
        foreach ( $version_checks as $key => $version ) {
            if ( $version ) {
                $this->log( sprintf( 'Found version %s in option %s', $version, $key ) );
                
                // Normalize version numbers
                $version = $this->normalize_version( $version );
                
                if ( $version ) {
                    return $version;
                }
            }
        }
        
        // Check if tables exist to determine if this is an existing installation
        if ( $this->check_legacy_tables() ) {
            $this->log( 'Legacy tables found, assuming version 1.4' );
            return '1.4';
        }
        
        return false;
    }
    
    /**
     * Normalize version string
     */
    private function normalize_version( $version ) {
        // Handle various version formats
        $version = trim( $version );
        
        // Remove 'v' prefix if present
        $version = ltrim( $version, 'v' );
        
        // Ensure it's a valid version
        if ( preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
            return $version;
        }
        
        return false;
    }
    
    /**
     * Check if legacy tables exist
     */
    private function check_legacy_tables() {
        global $wpdb;
        
        $legacy_tables = array(
            'mq_master',
            'mq_prospects',
            'mq_taken',
            'mq_results',
            'mq_coach',
            'mq_archetypes',
        );
        
        foreach ( $legacy_tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) );
            
            if ( $exists ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Run upgrades
     */
    private function run_upgrades() {
        $this->log( sprintf( 'Starting upgrade from %s to %s', $this->current_version ?: 'fresh install', $this->target_version ) );
        
        // Set flag to prevent timeouts
        @set_time_limit( 300 );
        
        // Start with current version or 0
        $current = $this->current_version ?: '0';
        
        // Run each upgrade in sequence
        foreach ( $this->upgrades as $version => $upgrade_function ) {
            if ( version_compare( $current, $version, '<' ) ) {
                $this->log( sprintf( 'Running upgrade to %s', $version ) );
                
                try {
                    call_user_func( $upgrade_function );
                    $current = $version;
                    
                    // Update version in database after each successful upgrade
                    $this->update_version( $version );
                    
                } catch ( Exception $e ) {
                    $this->log( sprintf( 'Upgrade to %s failed: %s', $version, $e->getMessage() ), 'error' );
                    
                    // Stop further upgrades on error
                    break;
                }
            }
        }
        
        // Final version update
        if ( version_compare( $current, $this->target_version, '<' ) ) {
            $this->update_version( $this->target_version );
        }
        
        // Clean up old version options
        $this->cleanup_version_options();
        
        // Clear caches
        wp_cache_flush();
        
        $this->log( 'Upgrade complete' );
    }
    
    /**
     * Upgrade from 1.4 to 2.0
     */
    private function upgrade_to_2_0() {
        global $wpdb;
        
        // Add missing columns to existing tables
        $table_updates = array(
            'mq_prospects' => array(
                'created_at' => "ALTER TABLE {$wpdb->prefix}mq_prospects ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE {$wpdb->prefix}mq_prospects ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ),
            'mq_results' => array(
                'ip_address' => "ALTER TABLE {$wpdb->prefix}mq_results ADD COLUMN ip_address VARCHAR(45)",
                'user_agent' => "ALTER TABLE {$wpdb->prefix}mq_results ADD COLUMN user_agent TEXT",
            ),
        );
        
        foreach ( $table_updates as $table => $columns ) {
            $table_name = $wpdb->prefix . $table;
            
            // Check if table exists
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                foreach ( $columns as $column => $sql ) {
                    // Check if column exists
                    $column_exists = $wpdb->get_var( 
                        "SHOW COLUMNS FROM $table_name LIKE '$column'" 
                    );
                    
                    if ( ! $column_exists ) {
                        $wpdb->query( $sql );
                        $this->log( sprintf( 'Added column %s to table %s', $column, $table_name ) );
                    }
                }
            }
        }
        
        // Update any hardcoded URLs in the database
        $this->update_hardcoded_urls();
    }
    
    /**
     * Upgrade from 2.0 to 3.0
     */
    private function upgrade_to_3_0() {
        // Add indexes for better performance
        global $wpdb;
        
        $indexes = array(
            'mq_prospects' => array(
                'idx_email' => "ALTER TABLE {$wpdb->prefix}mq_prospects ADD INDEX idx_email (Email)",
                'idx_created' => "ALTER TABLE {$wpdb->prefix}mq_prospects ADD INDEX idx_created (created_at)",
            ),
            'mq_results' => array(
                'idx_prospect' => "ALTER TABLE {$wpdb->prefix}mq_results ADD INDEX idx_prospect (Prospect_ID)",
                'idx_created' => "ALTER TABLE {$wpdb->prefix}mq_results ADD INDEX idx_created (Date_Taken)",
            ),
        );
        
        foreach ( $indexes as $table => $table_indexes ) {
            $table_name = $wpdb->prefix . $table;
            
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                foreach ( $table_indexes as $index_name => $sql ) {
                    // Check if index exists
                    $index_exists = $wpdb->get_var( 
                        "SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'" 
                    );
                    
                    if ( ! $index_exists ) {
                        $wpdb->query( $sql );
                        $this->log( sprintf( 'Added index %s to table %s', $index_name, $table_name ) );
                    }
                }
            }
        }
        
        // Migrate settings to new structure
        $this->migrate_settings();
    }
    
    /**
     * Upgrade from 3.0 to 3.3
     */
    private function upgrade_to_3_3() {
        // Fix character encoding issues
        global $wpdb;
        
        $tables = array(
            'mq_master',
            'mq_prospects', 
            'mq_taken',
            'mq_results',
            'mq_coach',
            'mq_archetypes',
            'mq_cta',
            'mq_template_layout',
        );
        
        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                // Convert to utf8mb4 if supported
                if ( $wpdb->has_cap( 'utf8mb4' ) ) {
                    $wpdb->query( 
                        "ALTER TABLE $table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 
                    );
                    $this->log( sprintf( 'Converted table %s to utf8mb4', $table_name ) );
                }
            }
        }
    }
    
    /**
     * Upgrade from 3.3 to 4.0
     */
    private function upgrade_to_4_0() {
        // Create new tables for version 4.0
        $this->create_v4_tables();
        
        // Migrate data to new structure
        $this->migrate_to_v4();
        
        // Update options
        update_option( 'money_quiz_safe_mode', true );
        update_option( 'money_quiz_upgraded_from', $this->current_version );
        
        // Schedule cleanup of old data
        wp_schedule_single_event( time() + WEEK_IN_SECONDS, 'money_quiz_cleanup_old_data' );
    }
    
    /**
     * Create v4 tables
     */
    private function create_v4_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Activity log table
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
        
        // Settings table
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
        
        $this->log( 'Created v4 tables' );
    }
    
    /**
     * Migrate to v4 structure
     */
    private function migrate_to_v4() {
        // Migrate settings from options to settings table
        $settings_to_migrate = array(
            'mq_money_coach_status',
            'mq_money_coach_plugin_version',
            'moneyquiz_license_key',
        );
        
        global $wpdb;
        
        foreach ( $settings_to_migrate as $option_name ) {
            $value = get_option( $option_name );
            if ( $value !== false ) {
                $wpdb->insert(
                    $wpdb->prefix . 'mq_settings',
                    array(
                        'setting_key' => $option_name,
                        'setting_value' => maybe_serialize( $value ),
                    ),
                    array( '%s', '%s' )
                );
            }
        }
        
        $this->log( 'Migrated settings to v4 structure' );
    }
    
    /**
     * Update hardcoded URLs
     */
    private function update_hardcoded_urls() {
        global $wpdb;
        
        // Update plugin URLs in database
        $site_url = get_site_url();
        $tables_to_check = array(
            'mq_template_layout',
            'mq_coach',
            'mq_cta',
        );
        
        foreach ( $tables_to_check as $table ) {
            $table_name = $wpdb->prefix . $table;
            
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                // Update URLs to use current site URL
                $wpdb->query( 
                    $wpdb->prepare(
                        "UPDATE $table_name SET value = REPLACE(value, %s, %s) WHERE value LIKE %s",
                        'http://money.reverinfotech.com',
                        $site_url,
                        '%http://money.reverinfotech.com%'
                    )
                );
            }
        }
    }
    
    /**
     * Migrate settings
     */
    private function migrate_settings() {
        // Consolidate scattered settings
        $old_settings = array(
            'mq_setting_1' => 'money_quiz_setting_1',
            'mq_setting_2' => 'money_quiz_setting_2',
        );
        
        foreach ( $old_settings as $old => $new ) {
            $value = get_option( $old );
            if ( $value !== false ) {
                update_option( $new, $value );
                delete_option( $old );
            }
        }
    }
    
    /**
     * Update version in database
     */
    private function update_version( $version ) {
        update_option( 'money_quiz_version', $version );
        
        // Also update legacy version options for compatibility
        update_option( 'mq_money_coach_plugin_version', $version );
    }
    
    /**
     * Clean up old version options
     */
    private function cleanup_version_options() {
        // Keep only the main version option
        $old_options = array(
            'moneyquiz_version',
            'mq_version',
            'money_quiz_db_version',
        );
        
        foreach ( $old_options as $option ) {
            delete_option( $option );
        }
    }
    
    /**
     * Log upgrade messages
     */
    private function log( $message, $type = 'info' ) {
        $this->log[] = array(
            'time' => current_time( 'mysql' ),
            'type' => $type,
            'message' => $message,
        );
        
        // Also log to error log if debug is enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[Money Quiz Upgrade] %s: %s', $type, $message ) );
        }
    }
    
    /**
     * Get upgrade log
     */
    public function get_log() {
        return $this->log;
    }
}