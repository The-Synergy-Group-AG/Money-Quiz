<?php
/**
 * Optimized Base Repository
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database\Repositories;

/**
 * Optimized base repository with query caching and performance monitoring
 */
abstract class BaseRepositoryOptimized extends BaseRepository {
    
    /**
     * @var array Query cache
     */
    private array $query_cache = [];
    
    /**
     * @var bool Enable query monitoring
     */
    protected bool $monitor_queries = false;
    
    /**
     * Find record by ID with caching
     * 
     * @param int $id Record ID
     * @return object|null
     */
    public function find( int $id ): ?object {
        $cache_key = $this->get_cache_key( 'find', $id );
        
        if ( isset( $this->query_cache[ $cache_key ] ) ) {
            return $this->query_cache[ $cache_key ];
        }
        
        $start = microtime( true );
        $result = parent::find( $id );
        $this->log_query_performance( 'find', microtime( true ) - $start );
        
        $this->query_cache[ $cache_key ] = $result;
        
        return $result;
    }
    
    /**
     * Get all records with limit and caching
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function all( array $args = [] ): array {
        $defaults = [
            'limit' => 100, // Default limit to prevent memory issues
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'ASC',
        ];
        
        $args = wp_parse_args( $args, $defaults );
        $cache_key = $this->get_cache_key( 'all', $args );
        
        if ( isset( $this->query_cache[ $cache_key ] ) ) {
            return $this->query_cache[ $cache_key ];
        }
        
        $start = microtime( true );
        
        $query = "SELECT * FROM {$this->get_table_name()}";
        $query .= $this->build_order_clause( $args );
        $query .= $this->build_limit_clause( $args );
        
        $results = $this->db->get_results( $query );
        
        $this->log_query_performance( 'all', microtime( true ) - $start );
        $this->query_cache[ $cache_key ] = $results;
        
        return $results;
    }
    
    /**
     * Optimized where query with prepared statements
     * 
     * @param array $conditions Where conditions
     * @param array $args       Additional arguments
     * @return array
     */
    public function where( array $conditions, array $args = [] ): array {
        $cache_key = $this->get_cache_key( 'where', [ $conditions, $args ] );
        
        if ( isset( $this->query_cache[ $cache_key ] ) ) {
            return $this->query_cache[ $cache_key ];
        }
        
        $start = microtime( true );
        
        // Build WHERE clause with prepared statements
        $where_parts = [];
        $where_values = [];
        
        foreach ( $conditions as $column => $value ) {
            if ( is_array( $value ) ) {
                // Handle IN clause
                $placeholders = array_fill( 0, count( $value ), '%s' );
                $where_parts[] = "{$column} IN (" . implode( ', ', $placeholders ) . ")";
                $where_values = array_merge( $where_values, $value );
            } elseif ( is_null( $value ) ) {
                $where_parts[] = "{$column} IS NULL";
            } else {
                $where_parts[] = "{$column} = %s";
                $where_values[] = $value;
            }
        }
        
        $query = "SELECT * FROM {$this->get_table_name()}";
        
        if ( ! empty( $where_parts ) ) {
            $query .= " WHERE " . implode( ' AND ', $where_parts );
        }
        
        $query .= $this->build_order_clause( $args );
        $query .= $this->build_limit_clause( $args );
        
        if ( ! empty( $where_values ) ) {
            $query = $this->db->prepare( $query, $where_values );
        }
        
        $results = $this->db->get_results( $query );
        
        $this->log_query_performance( 'where', microtime( true ) - $start );
        $this->query_cache[ $cache_key ] = $results;
        
        return $results;
    }
    
    /**
     * Count records with conditions
     * 
     * @param array $conditions Where conditions
     * @return int
     */
    public function count( array $conditions = [] ): int {
        $cache_key = $this->get_cache_key( 'count', $conditions );
        
        if ( isset( $this->query_cache[ $cache_key ] ) ) {
            return $this->query_cache[ $cache_key ];
        }
        
        $start = microtime( true );
        
        $query = "SELECT COUNT(*) FROM {$this->get_table_name()}";
        
        if ( ! empty( $conditions ) ) {
            $where_parts = [];
            $where_values = [];
            
            foreach ( $conditions as $column => $value ) {
                $where_parts[] = "{$column} = %s";
                $where_values[] = $value;
            }
            
            $query .= " WHERE " . implode( ' AND ', $where_parts );
            
            if ( ! empty( $where_values ) ) {
                $query = $this->db->prepare( $query, $where_values );
            }
        }
        
        $count = (int) $this->db->get_var( $query );
        
        $this->log_query_performance( 'count', microtime( true ) - $start );
        $this->query_cache[ $cache_key ] = $count;
        
        return $count;
    }
    
    /**
     * Batch insert for better performance
     * 
     * @param array $records Array of records to insert
     * @return bool
     */
    public function insert_batch( array $records ): bool {
        if ( empty( $records ) ) {
            return false;
        }
        
        $start = microtime( true );
        
        // Get columns from first record
        $columns = array_keys( $records[0] );
        $table = $this->get_table_name();
        
        // Build query
        $column_names = implode( ', ', $columns );
        $placeholders = '(' . implode( ', ', array_fill( 0, count( $columns ), '%s' ) ) . ')';
        $value_sets = array_fill( 0, count( $records ), $placeholders );
        
        $query = "INSERT INTO {$table} ({$column_names}) VALUES " . implode( ', ', $value_sets );
        
        // Flatten values
        $values = [];
        foreach ( $records as $record ) {
            foreach ( $columns as $column ) {
                $values[] = $record[ $column ] ?? null;
            }
        }
        
        $result = $this->db->query( $this->db->prepare( $query, $values ) );
        
        $this->log_query_performance( 'insert_batch', microtime( true ) - $start );
        $this->clear_cache();
        
        return $result !== false;
    }
    
    /**
     * Optimized update with minimal queries
     * 
     * @param int   $id   Record ID
     * @param array $data Data to update
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        // Add updated_at if table has it
        if ( $this->has_timestamps() ) {
            $data['updated_at'] = current_time( 'mysql' );
        }
        
        $start = microtime( true );
        $result = parent::update( $id, $data );
        $this->log_query_performance( 'update', microtime( true ) - $start );
        
        // Clear cache for this record
        $this->clear_cache( 'find', $id );
        
        return $result;
    }
    
    /**
     * Build ORDER BY clause
     * 
     * @param array $args Arguments
     * @return string
     */
    protected function build_order_clause( array $args ): string {
        if ( empty( $args['orderby'] ) ) {
            return '';
        }
        
        $orderby = esc_sql( $args['orderby'] );
        $order = isset( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
        
        return " ORDER BY {$orderby} {$order}";
    }
    
    /**
     * Build LIMIT clause
     * 
     * @param array $args Arguments
     * @return string
     */
    protected function build_limit_clause( array $args ): string {
        if ( empty( $args['limit'] ) ) {
            return '';
        }
        
        $limit = absint( $args['limit'] );
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        
        return " LIMIT {$limit} OFFSET {$offset}";
    }
    
    /**
     * Get cache key
     * 
     * @param string $method Method name
     * @param mixed  $params Parameters
     * @return string
     */
    protected function get_cache_key( string $method, $params ): string {
        return $method . '_' . md5( serialize( $params ) );
    }
    
    /**
     * Clear query cache
     * 
     * @param string|null $method Method to clear
     * @param mixed       $params Parameters
     * @return void
     */
    protected function clear_cache( ?string $method = null, $params = null ): void {
        if ( $method && $params !== null ) {
            $key = $this->get_cache_key( $method, $params );
            unset( $this->query_cache[ $key ] );
        } else {
            $this->query_cache = [];
        }
    }
    
    /**
     * Log query performance
     * 
     * @param string $query_type Query type
     * @param float  $time       Execution time
     * @return void
     */
    protected function log_query_performance( string $query_type, float $time ): void {
        if ( ! $this->monitor_queries ) {
            return;
        }
        
        // Log slow queries
        if ( $time > 0.05 ) { // 50ms threshold
            error_log( sprintf(
                'Slow query detected: %s::%s took %.3f seconds',
                get_class( $this ),
                $query_type,
                $time
            ) );
        }
        
        // Store in performance table if available
        if ( $this->db->get_var( "SHOW TABLES LIKE '{$this->db->prefix}money_quiz_performance'" ) ) {
            $this->db->insert(
                $this->db->prefix . 'money_quiz_performance',
                [
                    'query_type' => get_class( $this ) . '::' . $query_type,
                    'query_hash' => md5( $query_type ),
                    'execution_time' => $time,
                    'memory_usage' => memory_get_usage(),
                ]
            );
        }
    }
    
    /**
     * Check if table has timestamps
     * 
     * @return bool
     */
    protected function has_timestamps(): bool {
        static $cache = [];
        
        $table = $this->get_table_name();
        
        if ( ! isset( $cache[ $table ] ) ) {
            $columns = $this->db->get_col( "SHOW COLUMNS FROM {$table}" );
            $cache[ $table ] = in_array( 'updated_at', $columns );
        }
        
        return $cache[ $table ];
    }
}