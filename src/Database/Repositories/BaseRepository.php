<?php
/**
 * Base Repository
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Interfaces\RepositoryInterface;

/**
 * Abstract base repository class
 * 
 * Provides common database operations for all repositories
 */
abstract class BaseRepository implements RepositoryInterface {
    
    /**
     * @var \wpdb WordPress database object
     */
    protected \wpdb $db;
    
    /**
     * @var string Table name (without prefix)
     */
    protected string $table;
    
    /**
     * @var string Primary key column
     */
    protected string $primary_key = 'id';
    
    /**
     * Constructor
     * 
     * @param \wpdb $db WordPress database object
     */
    public function __construct( \wpdb $db ) {
        $this->db = $db;
        $this->table = $db->prefix . $this->table;
    }
    
    /**
     * Find a record by ID
     * 
     * @param int $id Record ID
     * @return object|null
     */
    public function find( int $id ): ?object {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primary_key} = %d",
            $id
        );
        
        return $this->db->get_row( $query );
    }
    
    /**
     * Find a record by column value
     * 
     * @param string $column Column name
     * @param mixed  $value  Column value
     * @return object|null
     */
    public function find_by( string $column, $value ): ?object {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$column} = %s",
            $value
        );
        
        return $this->db->get_row( $query );
    }
    
    /**
     * Get all records
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function all( array $args = [] ): array {
        $defaults = [
            'orderby' => $this->primary_key,
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0,
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $query = "SELECT * FROM {$this->table}";
        
        // Add ordering
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Add limit
        if ( $args['limit'] > 0 ) {
            $query .= $this->db->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }
        
        return $this->db->get_results( $query );
    }
    
    /**
     * Get records with conditions
     * 
     * @param array $where Where conditions
     * @param array $args  Query arguments
     * @return array
     */
    public function where( array $where, array $args = [] ): array {
        $defaults = [
            'orderby' => $this->primary_key,
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0,
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $values = [];
        
        foreach ( $where as $column => $value ) {
            if ( is_array( $value ) ) {
                $placeholders = array_fill( 0, count( $value ), '%s' );
                $query .= " AND {$column} IN (" . implode( ',', $placeholders ) . ")";
                $values = array_merge( $values, $value );
            } else {
                $query .= " AND {$column} = %s";
                $values[] = $value;
            }
        }
        
        // Add ordering
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Add limit
        if ( $args['limit'] > 0 ) {
            $query .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";
        }
        
        if ( ! empty( $values ) ) {
            $query = $this->db->prepare( $query, $values );
        }
        
        return $this->db->get_results( $query );
    }
    
    /**
     * Insert a record
     * 
     * @param array $data Data to insert
     * @return int|false Insert ID or false on failure
     */
    public function insert( array $data ) {
        $result = $this->db->insert( $this->table, $data, $this->get_formats( $data ) );
        
        return false !== $result ? $this->db->insert_id : false;
    }
    
    /**
     * Update a record
     * 
     * @param int   $id   Record ID
     * @param array $data Data to update
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        return false !== $this->db->update(
            $this->table,
            $data,
            [ $this->primary_key => $id ],
            $this->get_formats( $data ),
            [ '%d' ]
        );
    }
    
    /**
     * Delete a record
     * 
     * @param int $id Record ID
     * @return bool
     */
    public function delete( int $id ): bool {
        return false !== $this->db->delete(
            $this->table,
            [ $this->primary_key => $id ],
            [ '%d' ]
        );
    }
    
    /**
     * Count records
     * 
     * @param array $where Where conditions
     * @return int
     */
    public function count( array $where = [] ): int {
        $query = "SELECT COUNT(*) FROM {$this->table}";
        
        if ( ! empty( $where ) ) {
            $query .= " WHERE 1=1";
            $values = [];
            
            foreach ( $where as $column => $value ) {
                $query .= " AND {$column} = %s";
                $values[] = $value;
            }
            
            $query = $this->db->prepare( $query, $values );
        }
        
        return (int) $this->db->get_var( $query );
    }
    
    /**
     * Check if a record exists
     * 
     * @param int $id Record ID
     * @return bool
     */
    public function exists( int $id ): bool {
        $query = $this->db->prepare(
            "SELECT 1 FROM {$this->table} WHERE {$this->primary_key} = %d LIMIT 1",
            $id
        );
        
        return (bool) $this->db->get_var( $query );
    }
    
    /**
     * Get format specifiers for data
     * 
     * @param array $data Data array
     * @return array Format specifiers
     */
    protected function get_formats( array $data ): array {
        $formats = [];
        
        foreach ( $data as $value ) {
            if ( is_int( $value ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $value ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $formats;
    }
    
    /**
     * Begin a database transaction
     * 
     * @return void
     */
    public function begin_transaction(): void {
        $this->db->query( 'START TRANSACTION' );
    }
    
    /**
     * Commit a database transaction
     * 
     * @return void
     */
    public function commit(): void {
        $this->db->query( 'COMMIT' );
    }
    
    /**
     * Rollback a database transaction
     * 
     * @return void
     */
    public function rollback(): void {
        $this->db->query( 'ROLLBACK' );
    }
}