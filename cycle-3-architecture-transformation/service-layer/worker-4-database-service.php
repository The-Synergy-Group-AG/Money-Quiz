<?php
/**
 * Money Quiz Plugin - Database Service
 * Worker 4: Service Layer - Database Operations
 * 
 * Provides a clean abstraction layer for all database operations,
 * ensuring security and consistency across the application.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use wpdb;
use Exception;

/**
 * Database Service Class
 * 
 * Handles all database interactions with proper security
 */
class DatabaseService {
    
    /**
     * WordPress database instance
     * 
     * @var wpdb
     */
    protected $wpdb;
    
    /**
     * Table names cache
     * 
     * @var array
     */
    protected $tables = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_tables();
    }
    
    /**
     * Initialize table names
     */
    protected function init_tables() {
        $prefix = $this->wpdb->prefix;
        
        $this->tables = array(
            'prospects' => $prefix . 'mq_prospects',
            'taken' => $prefix . 'mq_taken',
            'results' => $prefix . 'mq_results',
            'master' => $prefix . 'mq_master',
            'archetypes' => $prefix . 'mq_archetypes',
            'questions' => $prefix . 'mq_questions',
            'answers' => $prefix . 'mq_answers',
            'cta' => $prefix . 'mq_cta',
            'blacklist' => $prefix . 'mq_blacklist',
            'activity_log' => $prefix . 'mq_activitylog',
            'error_log' => $prefix . 'mq_error_log',
            'mailings' => $prefix . 'mq_mailings',
            'mailing_runs' => $prefix . 'mq_mailing_runs',
            'mailing_messages' => $prefix . 'mq_mailing_messages',
            'version' => $prefix . 'mq_version'
        );
    }
    
    /**
     * Get table name
     * 
     * @param string $table Table identifier
     * @return string Full table name
     * @throws Exception
     */
    public function get_table( $table ) {
        if ( ! isset( $this->tables[ $table ] ) ) {
            throw new Exception( "Unknown table: {$table}" );
        }
        
        return $this->tables[ $table ];
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Prospects table
        $sql = "CREATE TABLE {$this->tables['prospects']} (
            Prospect_ID int(11) NOT NULL AUTO_INCREMENT,
            Email varchar(255) NOT NULL,
            FirstName varchar(100) DEFAULT NULL,
            LastName varchar(100) DEFAULT NULL,
            Phone varchar(50) DEFAULT NULL,
            IP_Address varchar(45) DEFAULT NULL,
            User_Agent text,
            Referrer varchar(500) DEFAULT NULL,
            Created datetime NOT NULL,
            Updated datetime DEFAULT NULL,
            Status varchar(20) DEFAULT 'active',
            PRIMARY KEY (Prospect_ID),
            UNIQUE KEY Email (Email),
            KEY Status (Status),
            KEY Created (Created)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Quiz taken table
        $sql = "CREATE TABLE {$this->tables['taken']} (
            Taken_ID int(11) NOT NULL AUTO_INCREMENT,
            Prospect_ID int(11) NOT NULL,
            Quiz_ID int(11) NOT NULL DEFAULT 1,
            Started datetime NOT NULL,
            Completed datetime DEFAULT NULL,
            Duration int(11) DEFAULT NULL,
            Score_Total decimal(5,2) DEFAULT NULL,
            Archetype_ID int(11) DEFAULT NULL,
            Status varchar(20) DEFAULT 'incomplete',
            PRIMARY KEY (Taken_ID),
            KEY Prospect_ID (Prospect_ID),
            KEY Quiz_ID (Quiz_ID),
            KEY Status (Status),
            KEY Completed (Completed)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Results table
        $sql = "CREATE TABLE {$this->tables['results']} (
            Result_ID int(11) NOT NULL AUTO_INCREMENT,
            Taken_ID int(11) NOT NULL,
            Prospect_ID int(11) NOT NULL,
            Question_ID int(11) NOT NULL,
            Answer_Value int(11) NOT NULL,
            Answer_Text text,
            Weight decimal(5,2) DEFAULT 1.00,
            Created datetime NOT NULL,
            PRIMARY KEY (Result_ID),
            KEY Taken_ID (Taken_ID),
            KEY Prospect_ID (Prospect_ID),
            KEY Question_ID (Question_ID)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Questions table
        $sql = "CREATE TABLE {$this->tables['questions']} (
            Question_ID int(11) NOT NULL AUTO_INCREMENT,
            Question_Text text NOT NULL,
            Question_Category varchar(100) DEFAULT NULL,
            Question_Type varchar(50) DEFAULT 'scale',
            Display_Order int(11) DEFAULT 0,
            Is_Active tinyint(1) DEFAULT 1,
            Created datetime NOT NULL,
            Updated datetime DEFAULT NULL,
            PRIMARY KEY (Question_ID),
            KEY Is_Active (Is_Active),
            KEY Display_Order (Display_Order)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Archetypes table
        $sql = "CREATE TABLE {$this->tables['archetypes']} (
            Archetype_ID int(11) NOT NULL AUTO_INCREMENT,
            Name varchar(100) NOT NULL,
            Description text,
            Score_Range_Min decimal(5,2) NOT NULL,
            Score_Range_Max decimal(5,2) NOT NULL,
            Color varchar(7) DEFAULT NULL,
            Icon varchar(50) DEFAULT NULL,
            Recommendations text,
            Display_Order int(11) DEFAULT 0,
            Is_Active tinyint(1) DEFAULT 1,
            PRIMARY KEY (Archetype_ID),
            KEY Score_Range (Score_Range_Min, Score_Range_Max),
            KEY Is_Active (Is_Active)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Error log table
        $sql = "CREATE TABLE {$this->tables['error_log']} (
            Error_ID int(11) NOT NULL AUTO_INCREMENT,
            Error_Type varchar(50) NOT NULL,
            Error_Message text NOT NULL,
            Error_File varchar(255) DEFAULT NULL,
            Error_Line int(11) DEFAULT NULL,
            Error_Context text,
            User_ID int(11) DEFAULT NULL,
            URL varchar(500) DEFAULT NULL,
            Created datetime NOT NULL,
            PRIMARY KEY (Error_ID),
            KEY Error_Type (Error_Type),
            KEY Created (Created)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Add more tables as needed...
    }
    
    /**
     * Insert data
     * 
     * @param string $table Table name
     * @param array  $data  Data to insert
     * @param array  $format Data formats
     * @return int|false Insert ID or false on failure
     */
    public function insert( $table, $data, $format = null ) {
        try {
            $table_name = $this->get_table( $table );
            
            // Add created timestamp if not set
            if ( ! isset( $data['Created'] ) && $this->has_column( $table, 'Created' ) ) {
                $data['Created'] = current_time( 'mysql' );
            }
            
            $result = $this->wpdb->insert( $table_name, $data, $format );
            
            if ( $result === false ) {
                throw new Exception( $this->wpdb->last_error );
            }
            
            return $this->wpdb->insert_id;
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database insert error', $e );
            return false;
        }
    }
    
    /**
     * Update data
     * 
     * @param string $table Table name
     * @param array  $data  Data to update
     * @param array  $where Where conditions
     * @param array  $format Data formats
     * @param array  $where_format Where formats
     * @return int|false Number of rows updated or false on failure
     */
    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        try {
            $table_name = $this->get_table( $table );
            
            // Add updated timestamp if not set
            if ( ! isset( $data['Updated'] ) && $this->has_column( $table, 'Updated' ) ) {
                $data['Updated'] = current_time( 'mysql' );
            }
            
            $result = $this->wpdb->update( $table_name, $data, $where, $format, $where_format );
            
            if ( $result === false ) {
                throw new Exception( $this->wpdb->last_error );
            }
            
            return $result;
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database update error', $e );
            return false;
        }
    }
    
    /**
     * Delete data
     * 
     * @param string $table Table name
     * @param array  $where Where conditions
     * @param array  $where_format Where formats
     * @return int|false Number of rows deleted or false on failure
     */
    public function delete( $table, $where, $where_format = null ) {
        try {
            $table_name = $this->get_table( $table );
            
            $result = $this->wpdb->delete( $table_name, $where, $where_format );
            
            if ( $result === false ) {
                throw new Exception( $this->wpdb->last_error );
            }
            
            return $result;
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database delete error', $e );
            return false;
        }
    }
    
    /**
     * Get single row
     * 
     * @param string $table Table name
     * @param array  $where Where conditions
     * @param string $output Output type
     * @return mixed
     */
    public function get_row( $table, $where = array(), $output = OBJECT ) {
        try {
            $table_name = $this->get_table( $table );
            
            $query = "SELECT * FROM {$table_name}";
            $query .= $this->build_where_clause( $where );
            $query .= " LIMIT 1";
            
            return $this->wpdb->get_row( $query, $output );
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database get_row error', $e );
            return null;
        }
    }
    
    /**
     * Get multiple rows
     * 
     * @param string $table Table name
     * @param array  $args Query arguments
     * @param string $output Output type
     * @return array
     */
    public function get_results( $table, $args = array(), $output = OBJECT ) {
        try {
            $table_name = $this->get_table( $table );
            
            $defaults = array(
                'where' => array(),
                'orderby' => '',
                'order' => 'ASC',
                'limit' => 0,
                'offset' => 0,
                'fields' => '*'
            );
            
            $args = wp_parse_args( $args, $defaults );
            
            $query = "SELECT {$args['fields']} FROM {$table_name}";
            $query .= $this->build_where_clause( $args['where'] );
            
            if ( $args['orderby'] ) {
                $query .= " ORDER BY {$args['orderby']} {$args['order']}";
            }
            
            if ( $args['limit'] > 0 ) {
                $query .= $this->wpdb->prepare( " LIMIT %d", $args['limit'] );
                
                if ( $args['offset'] > 0 ) {
                    $query .= $this->wpdb->prepare( " OFFSET %d", $args['offset'] );
                }
            }
            
            return $this->wpdb->get_results( $query, $output );
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database get_results error', $e );
            return array();
        }
    }
    
    /**
     * Get single value
     * 
     * @param string $table Table name
     * @param string $column Column name
     * @param array  $where Where conditions
     * @return mixed
     */
    public function get_var( $table, $column, $where = array() ) {
        try {
            $table_name = $this->get_table( $table );
            
            $query = "SELECT {$column} FROM {$table_name}";
            $query .= $this->build_where_clause( $where );
            $query .= " LIMIT 1";
            
            return $this->wpdb->get_var( $query );
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database get_var error', $e );
            return null;
        }
    }
    
    /**
     * Count rows
     * 
     * @param string $table Table name
     * @param array  $where Where conditions
     * @return int
     */
    public function count( $table, $where = array() ) {
        return (int) $this->get_var( $table, 'COUNT(*)', $where );
    }
    
    /**
     * Check if row exists
     * 
     * @param string $table Table name
     * @param array  $where Where conditions
     * @return bool
     */
    public function exists( $table, $where ) {
        return $this->count( $table, $where ) > 0;
    }
    
    /**
     * Build WHERE clause from array
     * 
     * @param array $where Where conditions
     * @return string
     */
    protected function build_where_clause( $where ) {
        if ( empty( $where ) ) {
            return '';
        }
        
        $conditions = array();
        
        foreach ( $where as $column => $value ) {
            if ( is_null( $value ) ) {
                $conditions[] = "{$column} IS NULL";
            } elseif ( is_array( $value ) ) {
                // Handle IN clause
                $placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
                $conditions[] = $this->wpdb->prepare( "{$column} IN ({$placeholders})", $value );
            } else {
                $conditions[] = $this->wpdb->prepare( "{$column} = %s", $value );
            }
        }
        
        return ' WHERE ' . implode( ' AND ', $conditions );
    }
    
    /**
     * Check if table has column
     * 
     * @param string $table Table name
     * @param string $column Column name
     * @return bool
     */
    protected function has_column( $table, $column ) {
        static $cache = array();
        
        $cache_key = "{$table}_{$column}";
        if ( isset( $cache[ $cache_key ] ) ) {
            return $cache[ $cache_key ];
        }
        
        $table_name = $this->get_table( $table );
        $result = $this->wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE '{$column}'" );
        
        $cache[ $cache_key ] = ! empty( $result );
        return $cache[ $cache_key ];
    }
    
    /**
     * Execute custom query
     * 
     * @param string $query SQL query
     * @param mixed  $args Query arguments for prepare
     * @return mixed
     */
    public function query( $query, $args = null ) {
        try {
            if ( ! is_null( $args ) ) {
                $query = $this->wpdb->prepare( $query, $args );
            }
            
            return $this->wpdb->query( $query );
            
        } catch ( Exception $e ) {
            $this->log_error( 'Database query error', $e );
            return false;
        }
    }
    
    /**
     * Start transaction
     */
    public function start_transaction() {
        $this->wpdb->query( 'START TRANSACTION' );
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->wpdb->query( 'COMMIT' );
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->wpdb->query( 'ROLLBACK' );
    }
    
    /**
     * Log database error
     * 
     * @param string    $message Error message
     * @param Exception $exception Exception object
     */
    protected function log_error( $message, $exception ) {
        error_log( sprintf(
            '[Money Quiz Database Error] %s: %s in %s on line %d',
            $message,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
        
        // Also log to our error table if it exists
        if ( isset( $this->tables['error_log'] ) ) {
            $this->wpdb->insert(
                $this->tables['error_log'],
                array(
                    'Error_Type' => 'database',
                    'Error_Message' => $exception->getMessage(),
                    'Error_File' => $exception->getFile(),
                    'Error_Line' => $exception->getLine(),
                    'Error_Context' => $message,
                    'Created' => current_time( 'mysql' )
                )
            );
        }
    }
    
    /**
     * Get last insert ID
     * 
     * @return int
     */
    public function get_insert_id() {
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get last error
     * 
     * @return string
     */
    public function get_last_error() {
        return $this->wpdb->last_error;
    }
}