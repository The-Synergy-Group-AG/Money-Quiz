<?php
/**
 * Version Migration System for Money Quiz
 * 
 * Handles progressive migration through version history
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Version Migration Class
 */
class Money_Quiz_Version_Migration {
    
    /**
     * @var Money_Quiz_Version_Migration
     */
    private static $instance = null;
    
    /**
     * @var array Migration paths
     */
    private $migration_paths = array();
    
    /**
     * @var array Migration status
     */
    private $migration_status = array();
    
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
        $this->define_migration_paths();
    }
    
    /**
     * Define migration paths
     */
    private function define_migration_paths() {
        $this->migration_paths = array(
            '1.4_to_2.0' => array(
                'from' => '1.4',
                'to' => '2.0',
                'migrations' => array(
                    'update_database_schema_v2',
                    'migrate_settings_v2',
                    'update_file_structure_v2',
                ),
            ),
            '2.0_to_2.5' => array(
                'from' => '2.0',
                'to' => '2.5',
                'migrations' => array(
                    'add_custom_fields',
                    'implement_conditional_logic',
                ),
            ),
            '2.5_to_3.0' => array(
                'from' => '2.5',
                'to' => '3.0',
                'migrations' => array(
                    'update_database_schema_v3',
                    'implement_multi_quiz',
                    'add_analytics_tables',
                ),
            ),
            '3.0_to_3.3' => array(
                'from' => '3.0',
                'to' => '3.3',
                'migrations' => array(
                    'add_api_tables',
                    'implement_webhooks',
                    'update_charset_utf8mb4',
                ),
            ),
            '3.3_to_4.0' => array(
                'from' => '3.3',
                'to' => '4.0',
                'migrations' => array(
                    'implement_modern_architecture',
                    'add_safety_features',
                    'create_version_tracking',
                    'migrate_to_psr4',
                ),
            ),
        );
    }
    
    /**
     * Get migration path
     */
    public function get_migration_path( $from_version, $to_version = '4.0.0' ) {
        $from = $this->normalize_version( $from_version );
        $to = $this->normalize_version( $to_version );
        
        $path = array();
        $current = $from;
        
        while ( version_compare( $current, $to, '<' ) ) {
            $next_step = $this->find_next_migration( $current );
            if ( ! $next_step ) {
                break;
            }
            
            $path[] = $next_step;
            $current = $next_step['to'];
        }
        
        return $path;
    }
    
    /**
     * Find next migration step
     */
    private function find_next_migration( $from_version ) {
        foreach ( $this->migration_paths as $key => $migration ) {
            if ( version_compare( $from_version, $migration['from'], '>=' ) &&
                 version_compare( $from_version, $migration['to'], '<' ) ) {
                return array_merge( array( 'key' => $key ), $migration );
            }
        }
        
        return null;
    }
    
    /**
     * Execute migration
     */
    public function execute_migration( $from_version, $to_version = '4.0.0' ) {
        $this->migration_status = array(
            'from' => $from_version,
            'to' => $to_version,
            'started' => current_time( 'mysql' ),
            'steps' => array(),
            'errors' => array(),
        );
        
        // Get migration path
        $path = $this->get_migration_path( $from_version, $to_version );
        
        if ( empty( $path ) ) {
            $this->migration_status['errors'][] = 'No migration path found';
            return false;
        }
        
        // Execute each migration step
        foreach ( $path as $step ) {
            $step_result = $this->execute_migration_step( $step );
            $this->migration_status['steps'][] = $step_result;
            
            if ( ! $step_result['success'] ) {
                $this->migration_status['errors'][] = $step_result['error'];
                break;
            }
        }
        
        $this->migration_status['completed'] = current_time( 'mysql' );
        $this->migration_status['success'] = empty( $this->migration_status['errors'] );
        
        return $this->migration_status;
    }
    
    /**
     * Execute single migration step
     */
    private function execute_migration_step( $step ) {
        $result = array(
            'step' => $step['key'],
            'from' => $step['from'],
            'to' => $step['to'],
            'migrations' => array(),
            'success' => true,
        );
        
        foreach ( $step['migrations'] as $migration ) {
            try {
                $method = 'migration_' . $migration;
                if ( method_exists( $this, $method ) ) {
                    $migration_result = $this->$method();
                    $result['migrations'][ $migration ] = array(
                        'success' => true,
                        'details' => $migration_result,
                    );
                } else {
                    throw new Exception( "Migration method $method not found" );
                }
            } catch ( Exception $e ) {
                $result['success'] = false;
                $result['error'] = $e->getMessage();
                $result['migrations'][ $migration ] = array(
                    'success' => false,
                    'error' => $e->getMessage(),
                );
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * Migration: Update database schema to v2
     */
    private function migration_update_database_schema_v2() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Add timestamp columns
        $tables = array( 'mq_prospects', 'mq_results' );
        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                $wpdb->query( "ALTER TABLE $table_name 
                    ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
            }
        }
        
        return array( 'tables_updated' => $tables );
    }
    
    /**
     * Migration: Migrate settings to v2
     */
    private function migration_migrate_settings_v2() {
        $old_settings = array(
            'mq_option_1' => 'money_quiz_option_1',
            'mq_option_2' => 'money_quiz_option_2',
        );
        
        $migrated = array();
        foreach ( $old_settings as $old => $new ) {
            $value = get_option( $old );
            if ( $value !== false ) {
                update_option( $new, $value );
                delete_option( $old );
                $migrated[] = $old;
            }
        }
        
        return array( 'settings_migrated' => $migrated );
    }
    
    /**
     * Migration: Update file structure to v2
     */
    private function migration_update_file_structure_v2() {
        // This would handle file reorganization if needed
        return array( 'status' => 'file_structure_checked' );
    }
    
    /**
     * Migration: Add custom fields
     */
    private function migration_add_custom_fields() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mq_custom_fields';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            field_key varchar(50) NOT NULL,
            field_label varchar(100) NOT NULL,
            field_type varchar(20) NOT NULL,
            field_options longtext,
            required tinyint(1) DEFAULT 0,
            position int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_key (field_key)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        return array( 'table_created' => $table_name );
    }
    
    /**
     * Migration: Implement conditional logic
     */
    private function migration_implement_conditional_logic() {
        // Add conditional logic support
        update_option( 'money_quiz_conditional_logic_enabled', true );
        return array( 'feature_enabled' => 'conditional_logic' );
    }
    
    /**
     * Migration: Update database schema to v3
     */
    private function migration_update_database_schema_v3() {
        global $wpdb;
        
        // Add indexes for performance
        $indexes = array(
            'mq_prospects' => array( 'Email', 'created_at' ),
            'mq_results' => array( 'Prospect_ID', 'Date_Taken' ),
        );
        
        foreach ( $indexes as $table => $columns ) {
            $table_name = $wpdb->prefix . $table;
            foreach ( $columns as $column ) {
                $index_name = 'idx_' . strtolower( $column );
                $wpdb->query( "ALTER TABLE $table_name ADD INDEX IF NOT EXISTS $index_name ($column)" );
            }
        }
        
        return array( 'indexes_added' => $indexes );
    }
    
    /**
     * Migration: Implement multi quiz
     */
    private function migration_implement_multi_quiz() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mq_quizzes';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_name varchar(100) NOT NULL,
            quiz_slug varchar(100) NOT NULL,
            quiz_settings longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY quiz_slug (quiz_slug)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        return array( 'table_created' => $table_name );
    }
    
    /**
     * Migration: Add analytics tables
     */
    private function migration_add_analytics_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'mq_analytics';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id bigint(20) UNSIGNED,
            session_id varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        return array( 'table_created' => $table_name );
    }
    
    /**
     * Migration: Add API tables
     */
    private function migration_add_api_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'mq_api_keys';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            api_key varchar(64) NOT NULL,
            api_secret varchar(64) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            permissions text,
            last_used datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        return array( 'table_created' => $table_name );
    }
    
    /**
     * Migration: Implement webhooks
     */
    private function migration_implement_webhooks() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'mq_webhooks';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event varchar(50) NOT NULL,
            url varchar(255) NOT NULL,
            secret varchar(64),
            status varchar(20) DEFAULT 'active',
            last_triggered datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event (event)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        return array( 'table_created' => $table_name );
    }
    
    /**
     * Migration: Update charset to utf8mb4
     */
    private function migration_update_charset_utf8mb4() {
        global $wpdb;
        
        if ( ! $wpdb->has_cap( 'utf8mb4' ) ) {
            return array( 'status' => 'utf8mb4_not_supported' );
        }
        
        $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}mq_%'" );
        $converted = array();
        
        foreach ( $tables as $table ) {
            $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
            $converted[] = $table;
        }
        
        return array( 'tables_converted' => $converted );
    }
    
    /**
     * Migration: Implement modern architecture
     */
    private function migration_implement_modern_architecture() {
        // Create namespaced directory structure
        $directories = array(
            MONEY_QUIZ_PLUGIN_DIR . 'src/',
            MONEY_QUIZ_PLUGIN_DIR . 'src/Core/',
            MONEY_QUIZ_PLUGIN_DIR . 'src/Admin/',
            MONEY_QUIZ_PLUGIN_DIR . 'src/Frontend/',
            MONEY_QUIZ_PLUGIN_DIR . 'src/API/',
        );
        
        foreach ( $directories as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
        
        return array( 'directories_created' => $directories );
    }
    
    /**
     * Migration: Add safety features
     */
    private function migration_add_safety_features() {
        // Enable safe mode by default
        update_option( 'money_quiz_safe_mode', true );
        update_option( 'money_quiz_error_handling', true );
        update_option( 'money_quiz_dependency_checking', true );
        
        return array( 'safety_features_enabled' => true );
    }
    
    /**
     * Migration: Create version tracking
     */
    private function migration_create_version_tracking() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'mq_version_history';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            version varchar(20) NOT NULL,
            component varchar(50) NOT NULL,
            previous_version varchar(20),
            migration_data longtext,
            migrated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY version (version),
            KEY component (component)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Record current versions
        $components = array(
            'plugin' => '4.0.0',
            'database' => '4.0',
            'api' => '1.0',
        );
        
        foreach ( $components as $component => $version ) {
            $wpdb->insert(
                $table_name,
                array(
                    'version' => $version,
                    'component' => $component,
                    'migration_data' => json_encode( array( 'initial_record' => true ) ),
                ),
                array( '%s', '%s', '%s' )
            );
        }
        
        return array( 'table_created' => $table_name, 'components_recorded' => $components );
    }
    
    /**
     * Migration: Migrate to PSR-4
     */
    private function migration_migrate_to_psr4() {
        // Update autoloader configuration
        $composer_json = MONEY_QUIZ_PLUGIN_DIR . 'composer.json';
        
        if ( file_exists( $composer_json ) ) {
            $config = json_decode( file_get_contents( $composer_json ), true );
            
            if ( ! isset( $config['autoload']['psr-4'] ) ) {
                $config['autoload']['psr-4'] = array(
                    'MoneyQuiz\\' => 'src/',
                );
                
                file_put_contents( $composer_json, json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        }
        
        return array( 'psr4_configured' => true );
    }
    
    /**
     * Normalize version
     */
    private function normalize_version( $version ) {
        $version = trim( $version );
        $version = ltrim( $version, 'v' );
        
        // Map common version aliases
        $aliases = array(
            '1.4' => '1.4.0',
            '2.0' => '2.0.0',
            '2.x' => '2.5.0',
            '3.0' => '3.0.0',
            '3.3' => '3.3.0',
            '4.0' => '4.0.0',
        );
        
        return $aliases[ $version ] ?? $version;
    }
    
    /**
     * Get migration status
     */
    public function get_migration_status() {
        return $this->migration_status;
    }
}