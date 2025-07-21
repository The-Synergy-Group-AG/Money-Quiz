<?php
/**
 * Money Quiz Plugin - Query Optimizer
 * Worker 1: Database Query Optimization
 * 
 * Optimizes database queries for maximum performance through intelligent
 * query analysis, caching, and batch operations.
 * 
 * @package MoneyQuiz
 * @subpackage Performance
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance;

use MoneyQuiz\Services\DatabaseService;
use MoneyQuiz\Utilities\CacheUtil;
use MoneyQuiz\Utilities\DebugUtil;

/**
 * Query Optimizer Class
 * 
 * Handles all database query optimization
 */
class QueryOptimizer {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Query cache
     * 
     * @var array
     */
    protected $query_cache = array();
    
    /**
     * Query statistics
     * 
     * @var array
     */
    protected $query_stats = array();
    
    /**
     * Optimization rules
     * 
     * @var array
     */
    protected $optimization_rules = array();
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     */
    public function __construct( DatabaseService $database ) {
        $this->database = $database;
        $this->init_optimization_rules();
        
        // Hook into query execution
        add_filter( 'query', array( $this, 'optimize_query' ), 10 );
        add_filter( 'posts_request', array( $this, 'optimize_posts_query' ), 10, 2 );
        add_filter( 'pre_get_posts', array( $this, 'optimize_wp_query' ), 10 );
        
        // Query monitoring
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            add_filter( 'query', array( $this, 'log_query' ), 999 );
        }
    }
    
    /**
     * Optimize a query before execution
     * 
     * @param string $query SQL query
     * @return string Optimized query
     */
    public function optimize_query( $query ) {
        // Skip if not Money Quiz query
        if ( ! $this->is_plugin_query( $query ) ) {
            return $query;
        }
        
        // Apply optimization rules
        foreach ( $this->optimization_rules as $rule ) {
            if ( $rule['condition']( $query ) ) {
                $query = $rule['optimizer']( $query );
            }
        }
        
        return $query;
    }
    
    /**
     * Get prospects with optimized query
     * 
     * @param array $args Query arguments
     * @return array Results
     */
    public function get_prospects_optimized( array $args = array() ) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'where' => array(),
            'select' => '*',
            'cache' => true,
            'cache_time' => 300 // 5 minutes
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        // Generate cache key
        $cache_key = 'prospects_' . md5( serialize( $args ) );
        
        // Check cache
        if ( $args['cache'] && $cached = $this->get_cached_query( $cache_key ) ) {
            return $cached;
        }
        
        // Build optimized query
        $query = $this->build_optimized_query( 'prospects', $args );
        
        // Execute query
        $results = $this->database->get_results_raw( $query );
        
        // Cache results
        if ( $args['cache'] ) {
            $this->cache_query_results( $cache_key, $results, $args['cache_time'] );
        }
        
        return $results;
    }
    
    /**
     * Get quiz results with JOIN optimization
     * 
     * @param array $args Query arguments
     * @return array Results
     */
    public function get_results_with_relations( array $args = array() ) {
        $cache_key = 'results_relations_' . md5( serialize( $args ) );
        
        if ( $cached = $this->get_cached_query( $cache_key ) ) {
            return $cached;
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        // Optimized query with selective JOINs
        $query = "
            SELECT 
                r.id,
                r.score,
                r.archetype_id,
                r.created_at,
                p.id as prospect_id,
                p.Email as email,
                p.Name as name,
                a.name as archetype_name,
                a.description as archetype_desc
            FROM {$prefix}mq_results r
            INNER JOIN {$prefix}mq_prospects p ON r.prospect_id = p.id
            INNER JOIN {$prefix}mq_archetypes a ON r.archetype_id = a.id
            WHERE 1=1
        ";
        
        // Add conditions
        if ( ! empty( $args['date_from'] ) ) {
            $query .= $wpdb->prepare( " AND r.created_at >= %s", $args['date_from'] );
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $query .= $wpdb->prepare( " AND r.created_at <= %s", $args['date_to'] );
        }
        
        if ( ! empty( $args['archetype_id'] ) ) {
            $query .= $wpdb->prepare( " AND r.archetype_id = %d", $args['archetype_id'] );
        }
        
        // Add ordering
        $orderby = $args['orderby'] ?? 'r.created_at';
        $order = $args['order'] ?? 'DESC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Add limit
        if ( ! empty( $args['limit'] ) ) {
            $query .= $wpdb->prepare( " LIMIT %d", $args['limit'] );
            
            if ( ! empty( $args['offset'] ) ) {
                $query .= $wpdb->prepare( " OFFSET %d", $args['offset'] );
            }
        }
        
        // Execute with query optimization
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        // Cache results
        $this->cache_query_results( $cache_key, $results, 300 );
        
        return $results;
    }
    
    /**
     * Batch insert optimization
     * 
     * @param string $table Table name
     * @param array  $data Array of records to insert
     * @param int    $batch_size Records per batch
     * @return bool Success
     */
    public function batch_insert( $table, array $data, $batch_size = 100 ) {
        if ( empty( $data ) ) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mq_' . $table;
        
        // Get columns from first record
        $columns = array_keys( reset( $data ) );
        $column_list = '`' . implode( '`, `', $columns ) . '`';
        
        // Process in batches
        $batches = array_chunk( $data, $batch_size );
        $total_inserted = 0;
        
        foreach ( $batches as $batch ) {
            $values = array();
            $placeholders = array();
            
            foreach ( $batch as $row ) {
                $row_placeholders = array();
                
                foreach ( $columns as $column ) {
                    $values[] = $row[ $column ] ?? null;
                    $row_placeholders[] = $this->get_placeholder( $row[ $column ] ?? null );
                }
                
                $placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';
            }
            
            // Build and execute batch insert
            $query = "INSERT INTO {$table_name} ({$column_list}) VALUES " . implode( ', ', $placeholders );
            
            $result = $wpdb->query( $wpdb->prepare( $query, $values ) );
            
            if ( false === $result ) {
                DebugUtil::log( 'Batch insert failed: ' . $wpdb->last_error, 'error' );
                return false;
            }
            
            $total_inserted += $result;
        }
        
        return $total_inserted;
    }
    
    /**
     * Update with optimized conditions
     * 
     * @param string $table Table name
     * @param array  $data Data to update
     * @param array  $where Where conditions
     * @return int|false Number of rows updated
     */
    public function optimized_update( $table, array $data, array $where ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mq_' . $table;
        
        // Build SET clause
        $set_parts = array();
        $set_values = array();
        
        foreach ( $data as $column => $value ) {
            $set_parts[] = "`{$column}` = " . $this->get_placeholder( $value );
            $set_values[] = $value;
        }
        
        // Build WHERE clause
        $where_parts = array();
        $where_values = array();
        
        foreach ( $where as $column => $value ) {
            if ( is_array( $value ) ) {
                // IN clause optimization
                $placeholders = array_fill( 0, count( $value ), $this->get_placeholder( reset( $value ) ) );
                $where_parts[] = "`{$column}` IN (" . implode( ', ', $placeholders ) . ")";
                $where_values = array_merge( $where_values, $value );
            } else {
                $where_parts[] = "`{$column}` = " . $this->get_placeholder( $value );
                $where_values[] = $value;
            }
        }
        
        // Build query
        $query = "UPDATE {$table_name} SET " . implode( ', ', $set_parts );
        
        if ( ! empty( $where_parts ) ) {
            $query .= " WHERE " . implode( ' AND ', $where_parts );
        }
        
        // Execute
        $values = array_merge( $set_values, $where_values );
        return $wpdb->query( $wpdb->prepare( $query, $values ) );
    }
    
    /**
     * Delete with optimization
     * 
     * @param string $table Table name
     * @param array  $where Where conditions
     * @param int    $limit Limit number of deletions
     * @return int|false Number of rows deleted
     */
    public function optimized_delete( $table, array $where, $limit = null ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mq_' . $table;
        
        // For large deletions, use batches
        if ( $limit && $limit > 1000 ) {
            return $this->batch_delete( $table, $where, 1000 );
        }
        
        // Build WHERE clause
        $where_parts = array();
        $values = array();
        
        foreach ( $where as $column => $value ) {
            $where_parts[] = "`{$column}` = " . $this->get_placeholder( $value );
            $values[] = $value;
        }
        
        $query = "DELETE FROM {$table_name}";
        
        if ( ! empty( $where_parts ) ) {
            $query .= " WHERE " . implode( ' AND ', $where_parts );
        }
        
        if ( $limit ) {
            $query .= " LIMIT " . intval( $limit );
        }
        
        return $wpdb->query( $wpdb->prepare( $query, $values ) );
    }
    
    /**
     * Get aggregated statistics with optimization
     * 
     * @param string $table Table name
     * @param array  $args Arguments
     * @return array Statistics
     */
    public function get_statistics( $table, array $args = array() ) {
        $cache_key = "stats_{$table}_" . md5( serialize( $args ) );
        
        if ( $cached = $this->get_cached_query( $cache_key ) ) {
            return $cached;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mq_' . $table;
        
        $query = "
            SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT DATE(created_at)) as days_active,
                MIN(created_at) as first_record,
                MAX(created_at) as last_record
        ";
        
        // Add custom aggregations
        if ( ! empty( $args['aggregations'] ) ) {
            foreach ( $args['aggregations'] as $agg ) {
                $query .= ", {$agg['function']}({$agg['column']}) as {$agg['alias']}";
            }
        }
        
        $query .= " FROM {$table_name}";
        
        // Add conditions
        if ( ! empty( $args['where'] ) ) {
            $where_parts = array();
            foreach ( $args['where'] as $column => $value ) {
                $where_parts[] = $wpdb->prepare( "{$column} = %s", $value );
            }
            $query .= " WHERE " . implode( ' AND ', $where_parts );
        }
        
        // Add grouping
        if ( ! empty( $args['group_by'] ) ) {
            $query .= " GROUP BY " . $args['group_by'];
        }
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        // Cache for 10 minutes
        $this->cache_query_results( $cache_key, $results, 600 );
        
        return $results;
    }
    
    /**
     * Optimize WordPress queries
     * 
     * @param \WP_Query $query
     */
    public function optimize_wp_query( $query ) {
        // Skip admin queries
        if ( is_admin() ) {
            return;
        }
        
        // Optimize main query
        if ( $query->is_main_query() ) {
            // Disable unnecessary queries
            $query->set( 'no_found_rows', true );
            
            // Limit fields if full post not needed
            if ( ! $query->is_singular() ) {
                $query->set( 'fields', 'ids' );
            }
        }
        
        // Optimize Money Quiz related queries
        if ( $query->get( 'meta_key' ) && strpos( $query->get( 'meta_key' ), 'money_quiz' ) === 0 ) {
            // Add meta query optimization
            $query->set( 'update_post_meta_cache', false );
            $query->set( 'update_post_term_cache', false );
        }
    }
    
    /**
     * Initialize optimization rules
     */
    protected function init_optimization_rules() {
        $this->optimization_rules = array(
            // Convert SELECT * to specific columns
            array(
                'condition' => function( $query ) {
                    return strpos( $query, 'SELECT *' ) !== false;
                },
                'optimizer' => function( $query ) {
                    return $this->optimize_select_star( $query );
                }
            ),
            
            // Add LIMIT to UPDATE/DELETE without LIMIT
            array(
                'condition' => function( $query ) {
                    return ( strpos( $query, 'UPDATE' ) === 0 || strpos( $query, 'DELETE' ) === 0 ) 
                           && strpos( $query, 'LIMIT' ) === false;
                },
                'optimizer' => function( $query ) {
                    return $query . ' LIMIT 1000';
                }
            ),
            
            // Optimize IN clauses
            array(
                'condition' => function( $query ) {
                    return preg_match( '/IN\s*\([^)]+\)/', $query );
                },
                'optimizer' => function( $query ) {
                    return $this->optimize_in_clause( $query );
                }
            ),
            
            // Add index hints
            array(
                'condition' => function( $query ) {
                    return strpos( $query, 'mq_prospects' ) !== false;
                },
                'optimizer' => function( $query ) {
                    return $this->add_index_hints( $query );
                }
            )
        );
    }
    
    /**
     * Optimize SELECT * queries
     * 
     * @param string $query
     * @return string
     */
    protected function optimize_select_star( $query ) {
        // Map tables to commonly used columns
        $column_maps = array(
            'mq_prospects' => 'id, Email, Name, Phone, Age, created_at',
            'mq_results' => 'id, prospect_id, archetype_id, score, created_at',
            'mq_archetypes' => 'id, name, description'
        );
        
        foreach ( $column_maps as $table => $columns ) {
            if ( strpos( $query, $table ) !== false ) {
                $query = str_replace( 'SELECT *', 'SELECT ' . $columns, $query );
                break;
            }
        }
        
        return $query;
    }
    
    /**
     * Optimize IN clauses
     * 
     * @param string $query
     * @return string
     */
    protected function optimize_in_clause( $query ) {
        // Convert large IN clauses to temporary table joins
        if ( preg_match( '/IN\s*\(([^)]+)\)/', $query, $matches ) ) {
            $values = explode( ',', $matches[1] );
            
            if ( count( $values ) > 100 ) {
                // Use temporary table for large IN clauses
                return $this->convert_to_temp_table_join( $query, $values );
            }
        }
        
        return $query;
    }
    
    /**
     * Add index hints to queries
     * 
     * @param string $query
     * @return string
     */
    protected function add_index_hints( $query ) {
        // Add USE INDEX hints for known slow queries
        $index_hints = array(
            'mq_prospects' => array(
                'Email' => 'idx_email',
                'created_at' => 'idx_created_at'
            ),
            'mq_results' => array(
                'prospect_id' => 'idx_prospect_id',
                'archetype_id' => 'idx_archetype_id'
            )
        );
        
        foreach ( $index_hints as $table => $indexes ) {
            if ( strpos( $query, $table ) !== false ) {
                foreach ( $indexes as $column => $index ) {
                    if ( strpos( $query, $column ) !== false ) {
                        $query = str_replace( 
                            "FROM {$table}", 
                            "FROM {$table} USE INDEX ({$index})", 
                            $query 
                        );
                        break;
                    }
                }
            }
        }
        
        return $query;
    }
    
    /**
     * Build optimized query
     * 
     * @param string $table Table name
     * @param array  $args Query arguments
     * @return string SQL query
     */
    protected function build_optimized_query( $table, array $args ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mq_' . $table;
        
        // SELECT clause
        $select = is_array( $args['select'] ) ? implode( ', ', $args['select'] ) : $args['select'];
        $query = "SELECT {$select} FROM {$table_name}";
        
        // WHERE clause
        if ( ! empty( $args['where'] ) ) {
            $where_parts = array();
            foreach ( $args['where'] as $column => $value ) {
                if ( is_array( $value ) ) {
                    $placeholders = array_fill( 0, count( $value ), '%s' );
                    $where_parts[] = $wpdb->prepare( 
                        "{$column} IN (" . implode( ', ', $placeholders ) . ")", 
                        $value 
                    );
                } else {
                    $where_parts[] = $wpdb->prepare( "{$column} = %s", $value );
                }
            }
            $query .= " WHERE " . implode( ' AND ', $where_parts );
        }
        
        // ORDER BY clause
        if ( ! empty( $args['orderby'] ) ) {
            $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
            $query .= " ORDER BY {$args['orderby']} {$order}";
        }
        
        // LIMIT clause
        if ( ! empty( $args['limit'] ) ) {
            $query .= $wpdb->prepare( " LIMIT %d", $args['limit'] );
            
            if ( ! empty( $args['offset'] ) ) {
                $query .= $wpdb->prepare( " OFFSET %d", $args['offset'] );
            }
        }
        
        return $query;
    }
    
    /**
     * Get placeholder for value type
     * 
     * @param mixed $value
     * @return string
     */
    protected function get_placeholder( $value ) {
        if ( is_int( $value ) ) {
            return '%d';
        } elseif ( is_float( $value ) ) {
            return '%f';
        } else {
            return '%s';
        }
    }
    
    /**
     * Cache query results
     * 
     * @param string $key Cache key
     * @param mixed  $data Data to cache
     * @param int    $expiration Expiration time
     */
    protected function cache_query_results( $key, $data, $expiration = 300 ) {
        CacheUtil::set( 'query_' . $key, $data, $expiration );
        
        // Update local cache
        $this->query_cache[ $key ] = array(
            'data' => $data,
            'expires' => time() + $expiration
        );
    }
    
    /**
     * Get cached query results
     * 
     * @param string $key Cache key
     * @return mixed|false Cached data or false
     */
    protected function get_cached_query( $key ) {
        // Check local cache first
        if ( isset( $this->query_cache[ $key ] ) && 
             $this->query_cache[ $key ]['expires'] > time() ) {
            return $this->query_cache[ $key ]['data'];
        }
        
        // Check persistent cache
        return CacheUtil::get( 'query_' . $key );
    }
    
    /**
     * Check if query is from this plugin
     * 
     * @param string $query
     * @return bool
     */
    protected function is_plugin_query( $query ) {
        $plugin_tables = array( 'mq_', 'money_quiz' );
        
        foreach ( $plugin_tables as $prefix ) {
            if ( strpos( $query, $prefix ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log query for analysis
     * 
     * @param string $query
     * @return string
     */
    public function log_query( $query ) {
        if ( ! $this->is_plugin_query( $query ) ) {
            return $query;
        }
        
        // Track query execution
        $start_time = microtime( true );
        
        add_filter( 'query_results', function( $results ) use ( $query, $start_time ) {
            $execution_time = microtime( true ) - $start_time;
            
            $this->query_stats[] = array(
                'query' => $query,
                'time' => $execution_time,
                'rows' => is_array( $results ) ? count( $results ) : 0,
                'timestamp' => time()
            );
            
            // Log slow queries
            if ( $execution_time > 0.1 ) { // 100ms
                DebugUtil::log( sprintf( 
                    'Slow query detected (%.3fs): %s', 
                    $execution_time, 
                    $query 
                ), 'warning' );
            }
            
            return $results;
        }, 10 );
        
        return $query;
    }
    
    /**
     * Get query statistics
     * 
     * @return array Statistics
     */
    public function get_query_statistics() {
        $stats = array(
            'total_queries' => count( $this->query_stats ),
            'total_time' => array_sum( array_column( $this->query_stats, 'time' ) ),
            'average_time' => 0,
            'slow_queries' => 0,
            'queries_by_table' => array()
        );
        
        if ( $stats['total_queries'] > 0 ) {
            $stats['average_time'] = $stats['total_time'] / $stats['total_queries'];
            
            foreach ( $this->query_stats as $query_stat ) {
                if ( $query_stat['time'] > 0.1 ) {
                    $stats['slow_queries']++;
                }
                
                // Group by table
                if ( preg_match( '/FROM\s+(\S+)/i', $query_stat['query'], $matches ) ) {
                    $table = $matches[1];
                    if ( ! isset( $stats['queries_by_table'][ $table ] ) ) {
                        $stats['queries_by_table'][ $table ] = 0;
                    }
                    $stats['queries_by_table'][ $table ]++;
                }
            }
        }
        
        return $stats;
    }
}

/**
 * Database Index Manager
 * 
 * Manages database indexes for optimal performance
 */
class DatabaseIndexManager {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Required indexes
     * 
     * @var array
     */
    protected $required_indexes = array(
        'mq_prospects' => array(
            'idx_email' => array( 'Email' ),
            'idx_created_at' => array( 'created_at' ),
            'idx_email_created' => array( 'Email', 'created_at' )
        ),
        'mq_results' => array(
            'idx_prospect_id' => array( 'prospect_id' ),
            'idx_archetype_id' => array( 'archetype_id' ),
            'idx_created_at' => array( 'created_at' ),
            'idx_prospect_archetype' => array( 'prospect_id', 'archetype_id' )
        ),
        'mq_responses' => array(
            'idx_result_id' => array( 'result_id' ),
            'idx_question_id' => array( 'question_id' ),
            'idx_result_question' => array( 'result_id', 'question_id' )
        ),
        'mq_analytics_events' => array(
            'idx_event_type' => array( 'event_type' ),
            'idx_user_id' => array( 'user_id' ),
            'idx_created_at' => array( 'created_at' ),
            'idx_type_date' => array( 'event_type', 'created_at' )
        )
    );
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     */
    public function __construct( DatabaseService $database ) {
        $this->database = $database;
    }
    
    /**
     * Check and create missing indexes
     */
    public function optimize_indexes() {
        global $wpdb;
        
        foreach ( $this->required_indexes as $table => $indexes ) {
            $table_name = $wpdb->prefix . $table;
            
            // Get existing indexes
            $existing = $this->get_table_indexes( $table_name );
            
            foreach ( $indexes as $index_name => $columns ) {
                if ( ! isset( $existing[ $index_name ] ) ) {
                    $this->create_index( $table_name, $index_name, $columns );
                }
            }
        }
    }
    
    /**
     * Get table indexes
     * 
     * @param string $table Table name
     * @return array Indexes
     */
    protected function get_table_indexes( $table ) {
        global $wpdb;
        
        $indexes = array();
        $results = $wpdb->get_results( "SHOW INDEX FROM {$table}" );
        
        foreach ( $results as $index ) {
            $indexes[ $index->Key_name ][] = $index->Column_name;
        }
        
        return $indexes;
    }
    
    /**
     * Create index
     * 
     * @param string $table Table name
     * @param string $index_name Index name
     * @param array  $columns Columns
     */
    protected function create_index( $table, $index_name, array $columns ) {
        global $wpdb;
        
        $column_list = implode( ', ', array_map( function( $col ) {
            return "`{$col}`";
        }, $columns ) );
        
        $query = "ALTER TABLE {$table} ADD INDEX {$index_name} ({$column_list})";
        
        $result = $wpdb->query( $query );
        
        if ( false === $result ) {
            DebugUtil::log( "Failed to create index {$index_name} on {$table}: " . $wpdb->last_error, 'error' );
        } else {
            DebugUtil::log( "Created index {$index_name} on {$table}", 'info' );
        }
    }
    
    /**
     * Analyze table statistics
     * 
     * @param string $table Table name
     * @return array Statistics
     */
    public function analyze_table( $table ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $table;
        
        // Update table statistics
        $wpdb->query( "ANALYZE TABLE {$table_name}" );
        
        // Get table status
        $status = $wpdb->get_row( "SHOW TABLE STATUS LIKE '{$table_name}'" );
        
        // Get index usage
        $index_usage = $wpdb->get_results( "
            SELECT 
                index_name,
                stat_value AS cardinality
            FROM mysql.innodb_index_stats
            WHERE database_name = DATABASE()
            AND table_name = '{$table_name}'
            AND stat_name = 'n_diff_pfx01'
        " );
        
        return array(
            'rows' => $status->Rows,
            'data_length' => $status->Data_length,
            'index_length' => $status->Index_length,
            'indexes' => $index_usage
        );
    }
}