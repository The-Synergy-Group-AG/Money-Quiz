<?php
/**
 * Database Version Tracker for Money Quiz
 * 
 * Tracks and manages database schema versions
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Version Tracker Class
 */
class Money_Quiz_Database_Version_Tracker {
    
    /**
     * @var Money_Quiz_Database_Version_Tracker
     */
    private static $instance = null;
    
    /**
     * @var string Table name for version tracking
     */
    private $table_name;
    
    /**
     * @var array Schema versions and their structures
     */
    private $schema_versions = array(
        '1.0' => array(
            'tables' => array(
                'mq_master' => array(
                    'columns' => array( 'ID', 'Question', 'A', 'B', 'C', 'D' ),
                    'primary_key' => 'ID',
                ),
                'mq_prospects' => array(
                    'columns' => array( 'ID', 'Name', 'Email', 'FB_ID' ),
                    'primary_key' => 'ID',
                ),
                'mq_taken' => array(
                    'columns' => array( 'ID', 'Prospect_ID', 'Master_ID', 'Selected' ),
                    'primary_key' => 'ID',
                ),
                'mq_results' => array(
                    'columns' => array( 'ID', 'Prospect_ID', 'Type', 'Date_Taken' ),
                    'primary_key' => 'ID',
                ),
            ),
        ),
        '2.0' => array(
            'tables' => array(
                'mq_coach' => array(
                    'columns' => array( 'ID', 'Coach_Name', 'Coach_Email', 'Status' ),
                    'primary_key' => 'ID',
                ),
                'mq_archetypes' => array(
                    'columns' => array( 'ID', 'Type_Name', 'Description' ),
                    'primary_key' => 'ID',
                ),
            ),
            'modifications' => array(
                'mq_prospects' => array(
                    'add_columns' => array( 'created_at', 'updated_at' ),
                ),
                'mq_results' => array(
                    'add_columns' => array( 'ip_address', 'user_agent' ),
                ),
            ),
        ),
        '3.0' => array(
            'tables' => array(
                'mq_cta' => array(
                    'columns' => array( 'ID', 'CTA_Type', 'CTA_Content', 'Active' ),
                    'primary_key' => 'ID',
                ),
                'mq_template_layout' => array(
                    'columns' => array( 'ID', 'Template_Name', 'Template_Content' ),
                    'primary_key' => 'ID',
                ),
            ),
            'indexes' => array(
                'mq_prospects' => array( 'idx_email', 'idx_created' ),
                'mq_results' => array( 'idx_prospect', 'idx_created' ),
            ),
        ),
        '4.0' => array(
            'tables' => array(
                'mq_activity_log' => array(
                    'columns' => array( 'id', 'user_id', 'action', 'object_type', 'object_id', 'details', 'ip_address', 'user_agent', 'created_at' ),
                    'primary_key' => 'id',
                ),
                'mq_settings' => array(
                    'columns' => array( 'id', 'setting_key', 'setting_value', 'autoload', 'updated_at' ),
                    'primary_key' => 'id',
                    'unique_keys' => array( 'setting_key' ),
                ),
                'mq_version_history' => array(
                    'columns' => array( 'id', 'version', 'component', 'previous_version', 'migration_data', 'migrated_at' ),
                    'primary_key' => 'id',
                ),
            ),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ),
    );
    
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mq_version_history';
    }
    
    /**
     * Initialize version tracking
     */
    public function init() {
        $this->ensure_tracking_table();
    }
    
    /**
     * Ensure tracking table exists
     */
    private function ensure_tracking_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            version varchar(20) NOT NULL,
            component varchar(50) NOT NULL,
            previous_version varchar(20),
            migration_data longtext,
            migrated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY version (version),
            KEY component (component),
            KEY migrated_at (migrated_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
    }
    
    /**
     * Get current database version
     */
    public function get_current_version() {
        global $wpdb;
        
        // First check version history table
        if ( $this->table_exists( 'mq_version_history' ) ) {
            $version = $wpdb->get_var( $wpdb->prepare(
                "SELECT version FROM {$this->table_name} 
                WHERE component = %s 
                ORDER BY migrated_at DESC 
                LIMIT 1",
                'database'
            ) );
            
            if ( $version ) {
                return $version;
            }
        }
        
        // Fall back to detection
        return $this->detect_database_version();
    }
    
    /**
     * Detect database version based on schema
     */
    public function detect_database_version() {
        global $wpdb;
        
        $detected_version = '0.0';
        $confidence_scores = array();
        
        foreach ( $this->schema_versions as $version => $schema ) {
            $score = 0;
            $total = 0;
            
            // Check tables
            if ( isset( $schema['tables'] ) ) {
                foreach ( $schema['tables'] as $table => $structure ) {
                    $total++;
                    if ( $this->table_exists( $table ) ) {
                        $score++;
                        
                        // Check columns
                        if ( isset( $structure['columns'] ) ) {
                            $column_score = $this->check_table_columns( $table, $structure['columns'] );
                            $score += $column_score * 0.5; // Partial credit for columns
                        }
                    }
                }
            }
            
            // Check modifications
            if ( isset( $schema['modifications'] ) ) {
                foreach ( $schema['modifications'] as $table => $mods ) {
                    if ( isset( $mods['add_columns'] ) ) {
                        foreach ( $mods['add_columns'] as $column ) {
                            $total++;
                            if ( $this->column_exists( $table, $column ) ) {
                                $score++;
                            }
                        }
                    }
                }
            }
            
            // Check indexes
            if ( isset( $schema['indexes'] ) ) {
                foreach ( $schema['indexes'] as $table => $indexes ) {
                    foreach ( $indexes as $index ) {
                        $total++;
                        if ( $this->index_exists( $table, $index ) ) {
                            $score++;
                        }
                    }
                }
            }
            
            if ( $total > 0 ) {
                $confidence_scores[ $version ] = ( $score / $total ) * 100;
            }
        }
        
        // Find the highest matching version
        arsort( $confidence_scores );
        foreach ( $confidence_scores as $version => $score ) {
            if ( $score >= 80 ) { // 80% confidence threshold
                $detected_version = $version;
                break;
            }
        }
        
        return $detected_version;
    }
    
    /**
     * Check if table exists
     */
    private function table_exists( $table ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        return $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
    }
    
    /**
     * Check table columns
     */
    private function check_table_columns( $table, $expected_columns ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        
        if ( ! $this->table_exists( $table ) ) {
            return 0;
        }
        
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table_name" );
        $found = count( array_intersect( $expected_columns, $columns ) );
        $expected = count( $expected_columns );
        
        return $expected > 0 ? $found / $expected : 0;
    }
    
    /**
     * Check if column exists
     */
    private function column_exists( $table, $column ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        
        if ( ! $this->table_exists( $table ) ) {
            return false;
        }
        
        return $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE '$column'" ) !== null;
    }
    
    /**
     * Check if index exists
     */
    private function index_exists( $table, $index ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        
        if ( ! $this->table_exists( $table ) ) {
            return false;
        }
        
        return $wpdb->get_var( "SHOW INDEX FROM $table_name WHERE Key_name = '$index'" ) !== null;
    }
    
    /**
     * Record version change
     */
    public function record_version_change( $new_version, $component = 'database', $previous_version = null, $migration_data = array() ) {
        global $wpdb;
        
        if ( $previous_version === null ) {
            $previous_version = $this->get_current_version();
        }
        
        return $wpdb->insert(
            $this->table_name,
            array(
                'version' => $new_version,
                'component' => $component,
                'previous_version' => $previous_version,
                'migration_data' => json_encode( $migration_data ),
                'migrated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }
    
    /**
     * Get version history
     */
    public function get_version_history( $component = null, $limit = 10 ) {
        global $wpdb;
        
        if ( ! $this->table_exists( 'mq_version_history' ) ) {
            return array();
        }
        
        $where = $component ? $wpdb->prepare( " WHERE component = %s", $component ) : "";
        
        $results = $wpdb->get_results( 
            "SELECT * FROM {$this->table_name} 
            $where 
            ORDER BY migrated_at DESC 
            LIMIT $limit"
        );
        
        foreach ( $results as &$result ) {
            $result->migration_data = json_decode( $result->migration_data, true );
        }
        
        return $results;
    }
    
    /**
     * Get schema differences
     */
    public function get_schema_differences( $from_version, $to_version ) {
        $differences = array(
            'tables_to_add' => array(),
            'tables_to_remove' => array(),
            'columns_to_add' => array(),
            'columns_to_remove' => array(),
            'indexes_to_add' => array(),
            'indexes_to_remove' => array(),
        );
        
        $from_schema = $this->get_schema_for_version( $from_version );
        $to_schema = $this->get_schema_for_version( $to_version );
        
        // Compare tables
        $from_tables = array_keys( $from_schema['tables'] ?? array() );
        $to_tables = array_keys( $to_schema['tables'] ?? array() );
        
        $differences['tables_to_add'] = array_diff( $to_tables, $from_tables );
        $differences['tables_to_remove'] = array_diff( $from_tables, $to_tables );
        
        // Compare columns for existing tables
        foreach ( array_intersect( $from_tables, $to_tables ) as $table ) {
            $from_columns = $from_schema['tables'][ $table ]['columns'] ?? array();
            $to_columns = $to_schema['tables'][ $table ]['columns'] ?? array();
            
            $add = array_diff( $to_columns, $from_columns );
            if ( ! empty( $add ) ) {
                $differences['columns_to_add'][ $table ] = $add;
            }
            
            $remove = array_diff( $from_columns, $to_columns );
            if ( ! empty( $remove ) ) {
                $differences['columns_to_remove'][ $table ] = $remove;
            }
        }
        
        return $differences;
    }
    
    /**
     * Get schema for version
     */
    private function get_schema_for_version( $version ) {
        $schema = array(
            'tables' => array(),
            'indexes' => array(),
        );
        
        // Accumulate schema up to the specified version
        foreach ( $this->schema_versions as $v => $version_schema ) {
            if ( version_compare( $v, $version, '<=' ) ) {
                // Add new tables
                if ( isset( $version_schema['tables'] ) ) {
                    $schema['tables'] = array_merge( $schema['tables'], $version_schema['tables'] );
                }
                
                // Apply modifications
                if ( isset( $version_schema['modifications'] ) ) {
                    foreach ( $version_schema['modifications'] as $table => $mods ) {
                        if ( isset( $mods['add_columns'] ) && isset( $schema['tables'][ $table ] ) ) {
                            $schema['tables'][ $table ]['columns'] = array_merge(
                                $schema['tables'][ $table ]['columns'] ?? array(),
                                $mods['add_columns']
                            );
                        }
                    }
                }
                
                // Add indexes
                if ( isset( $version_schema['indexes'] ) ) {
                    $schema['indexes'] = array_merge( $schema['indexes'], $version_schema['indexes'] );
                }
            }
        }
        
        return $schema;
    }
    
    /**
     * Verify database integrity
     */
    public function verify_integrity() {
        $current_version = $this->get_current_version();
        $expected_schema = $this->get_schema_for_version( $current_version );
        $issues = array();
        
        // Check tables
        foreach ( $expected_schema['tables'] as $table => $structure ) {
            if ( ! $this->table_exists( $table ) ) {
                $issues[] = array(
                    'type' => 'missing_table',
                    'table' => $table,
                    'severity' => 'critical',
                );
            } else {
                // Check columns
                if ( isset( $structure['columns'] ) ) {
                    foreach ( $structure['columns'] as $column ) {
                        if ( ! $this->column_exists( $table, $column ) ) {
                            $issues[] = array(
                                'type' => 'missing_column',
                                'table' => $table,
                                'column' => $column,
                                'severity' => 'high',
                            );
                        }
                    }
                }
            }
        }
        
        // Check indexes
        foreach ( $expected_schema['indexes'] as $table => $indexes ) {
            foreach ( $indexes as $index ) {
                if ( ! $this->index_exists( $table, $index ) ) {
                    $issues[] = array(
                        'type' => 'missing_index',
                        'table' => $table,
                        'index' => $index,
                        'severity' => 'medium',
                    );
                }
            }
        }
        
        return array(
            'version' => $current_version,
            'issues' => $issues,
            'is_valid' => empty( $issues ),
        );
    }
    
    /**
     * Repair database issues
     */
    public function repair_database( $issues = null ) {
        if ( $issues === null ) {
            $integrity = $this->verify_integrity();
            $issues = $integrity['issues'];
        }
        
        $repaired = array();
        $failed = array();
        
        foreach ( $issues as $issue ) {
            try {
                switch ( $issue['type'] ) {
                    case 'missing_table':
                        $this->create_table( $issue['table'] );
                        $repaired[] = $issue;
                        break;
                        
                    case 'missing_column':
                        $this->add_column( $issue['table'], $issue['column'] );
                        $repaired[] = $issue;
                        break;
                        
                    case 'missing_index':
                        $this->add_index( $issue['table'], $issue['index'] );
                        $repaired[] = $issue;
                        break;
                }
            } catch ( Exception $e ) {
                $issue['error'] = $e->getMessage();
                $failed[] = $issue;
            }
        }
        
        return array(
            'repaired' => $repaired,
            'failed' => $failed,
        );
    }
    
    /**
     * Create table based on schema
     */
    private function create_table( $table ) {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $current_version = $this->get_current_version();
        $schema = $this->get_schema_for_version( $current_version );
        
        if ( ! isset( $schema['tables'][ $table ] ) ) {
            throw new Exception( "Table schema not found for $table" );
        }
        
        // Build CREATE TABLE statement based on schema
        // This is simplified - in production, you'd have more detailed column definitions
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . $table;
        
        $sql = "CREATE TABLE $table_name (";
        $columns = $schema['tables'][ $table ]['columns'];
        
        foreach ( $columns as $i => $column ) {
            if ( $i > 0 ) $sql .= ", ";
            
            // Simplified column definitions
            if ( $column === $schema['tables'][ $table ]['primary_key'] ) {
                $sql .= "$column bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT";
            } elseif ( strpos( $column, '_at' ) !== false ) {
                $sql .= "$column datetime DEFAULT CURRENT_TIMESTAMP";
            } else {
                $sql .= "$column varchar(255)";
            }
        }
        
        $sql .= ", PRIMARY KEY ({$schema['tables'][$table]['primary_key']})";
        $sql .= ") $charset_collate;";
        
        dbDelta( $sql );
    }
    
    /**
     * Add column to table
     */
    private function add_column( $table, $column ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        
        // Simplified column type detection
        $column_type = "varchar(255)";
        if ( strpos( $column, '_at' ) !== false ) {
            $column_type = "datetime DEFAULT CURRENT_TIMESTAMP";
        } elseif ( strpos( $column, '_id' ) !== false ) {
            $column_type = "bigint(20) UNSIGNED";
        }
        
        $wpdb->query( "ALTER TABLE $table_name ADD COLUMN $column $column_type" );
    }
    
    /**
     * Add index to table
     */
    private function add_index( $table, $index ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        
        // Extract column name from index name (simplified)
        $column = str_replace( 'idx_', '', $index );
        
        $wpdb->query( "ALTER TABLE $table_name ADD INDEX $index ($column)" );
    }
}