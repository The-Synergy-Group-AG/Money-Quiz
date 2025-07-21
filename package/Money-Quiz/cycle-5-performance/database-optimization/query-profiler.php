<?php
/**
 * Money Quiz Plugin - Query Profiler
 * 
 * Profiles database queries to identify performance bottlenecks
 * and provides optimization recommendations.
 * 
 * @package MoneyQuiz
 * @subpackage Performance
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance;

use MoneyQuiz\Utilities\DebugUtil;

/**
 * Query Profiler Class
 * 
 * Analyzes query performance and provides insights
 */
class QueryProfiler {
    
    /**
     * Profiling data
     * 
     * @var array
     */
    protected $profiles = array();
    
    /**
     * Start times for queries
     * 
     * @var array
     */
    protected $start_times = array();
    
    /**
     * Query patterns
     * 
     * @var array
     */
    protected $patterns = array();
    
    /**
     * Singleton instance
     * 
     * @var QueryProfiler
     */
    protected static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return QueryProfiler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    protected function __construct() {
        if ( defined( 'MONEY_QUIZ_PROFILE_QUERIES' ) && MONEY_QUIZ_PROFILE_QUERIES ) {
            $this->init_profiling();
        }
    }
    
    /**
     * Initialize query profiling
     */
    protected function init_profiling() {
        // Hook into query execution
        add_filter( 'query', array( $this, 'before_query' ), 1 );
        add_filter( 'query', array( $this, 'after_query' ), 9999 );
        
        // Profile results
        add_filter( 'query_results', array( $this, 'profile_results' ), 10, 2 );
        
        // Shutdown hook to save profiles
        add_action( 'shutdown', array( $this, 'save_profiles' ) );
    }
    
    /**
     * Before query execution
     * 
     * @param string $query
     * @return string
     */
    public function before_query( $query ) {
        $query_hash = md5( $query );
        $this->start_times[ $query_hash ] = microtime( true );
        
        return $query;
    }
    
    /**
     * After query execution
     * 
     * @param string $query
     * @return string
     */
    public function after_query( $query ) {
        $query_hash = md5( $query );
        
        if ( isset( $this->start_times[ $query_hash ] ) ) {
            $execution_time = microtime( true ) - $this->start_times[ $query_hash ];
            
            // Create profile entry
            $profile = array(
                'query' => $query,
                'time' => $execution_time,
                'timestamp' => time(),
                'backtrace' => $this->get_query_backtrace(),
                'type' => $this->get_query_type( $query ),
                'tables' => $this->extract_tables( $query ),
                'complexity' => $this->calculate_complexity( $query )
            );
            
            // Add EXPLAIN data for SELECT queries
            if ( $profile['type'] === 'SELECT' ) {
                $profile['explain'] = $this->get_query_explain( $query );
            }
            
            $this->profiles[] = $profile;
            
            // Identify patterns
            $this->analyze_pattern( $query );
            
            unset( $this->start_times[ $query_hash ] );
        }
        
        return $query;
    }
    
    /**
     * Profile query results
     * 
     * @param mixed  $results
     * @param string $query
     * @return mixed
     */
    public function profile_results( $results, $query = null ) {
        if ( ! empty( $this->profiles ) ) {
            $last_profile = &$this->profiles[ count( $this->profiles ) - 1 ];
            
            if ( is_array( $results ) ) {
                $last_profile['rows'] = count( $results );
            } elseif ( is_numeric( $results ) ) {
                $last_profile['affected_rows'] = $results;
            }
            
            // Calculate efficiency
            $last_profile['efficiency'] = $this->calculate_efficiency( $last_profile );
        }
        
        return $results;
    }
    
    /**
     * Get query type
     * 
     * @param string $query
     * @return string
     */
    protected function get_query_type( $query ) {
        $query = trim( $query );
        $first_word = strtoupper( substr( $query, 0, strpos( $query, ' ' ) ) );
        
        return in_array( $first_word, array( 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP' ) ) 
            ? $first_word 
            : 'OTHER';
    }
    
    /**
     * Extract tables from query
     * 
     * @param string $query
     * @return array
     */
    protected function extract_tables( $query ) {
        $tables = array();
        
        // Extract from FROM clause
        if ( preg_match_all( '/FROM\s+`?(\w+)`?/i', $query, $matches ) ) {
            $tables = array_merge( $tables, $matches[1] );
        }
        
        // Extract from JOIN clauses
        if ( preg_match_all( '/JOIN\s+`?(\w+)`?/i', $query, $matches ) ) {
            $tables = array_merge( $tables, $matches[1] );
        }
        
        // Extract from UPDATE/INSERT/DELETE
        if ( preg_match( '/(UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+`?(\w+)`?/i', $query, $matches ) ) {
            $tables[] = $matches[2];
        }
        
        return array_unique( $tables );
    }
    
    /**
     * Calculate query complexity
     * 
     * @param string $query
     * @return int
     */
    protected function calculate_complexity( $query ) {
        $complexity = 1;
        
        // Count JOINs
        $complexity += substr_count( strtoupper( $query ), 'JOIN' ) * 2;
        
        // Count subqueries
        $complexity += substr_count( $query, 'SELECT' ) - 1;
        
        // Count functions
        if ( preg_match_all( '/\b(COUNT|SUM|AVG|MAX|MIN|GROUP_CONCAT)\s*\(/i', $query, $matches ) ) {
            $complexity += count( $matches[0] );
        }
        
        // Check for GROUP BY
        if ( stripos( $query, 'GROUP BY' ) !== false ) {
            $complexity += 2;
        }
        
        // Check for ORDER BY
        if ( stripos( $query, 'ORDER BY' ) !== false ) {
            $complexity += 1;
        }
        
        // Check for HAVING
        if ( stripos( $query, 'HAVING' ) !== false ) {
            $complexity += 2;
        }
        
        return $complexity;
    }
    
    /**
     * Get EXPLAIN data for query
     * 
     * @param string $query
     * @return array|null
     */
    protected function get_query_explain( $query ) {
        global $wpdb;
        
        // Only EXPLAIN SELECT queries
        if ( stripos( trim( $query ), 'SELECT' ) !== 0 ) {
            return null;
        }
        
        try {
            $explain = $wpdb->get_results( "EXPLAIN {$query}", ARRAY_A );
            return $explain;
        } catch ( \Exception $e ) {
            return null;
        }
    }
    
    /**
     * Calculate query efficiency
     * 
     * @param array $profile
     * @return float
     */
    protected function calculate_efficiency( $profile ) {
        $efficiency = 100;
        
        // Time penalty (queries over 50ms start losing efficiency)
        if ( $profile['time'] > 0.05 ) {
            $efficiency -= min( 50, ( $profile['time'] - 0.05 ) * 100 );
        }
        
        // Complexity penalty
        $efficiency -= min( 30, $profile['complexity'] * 3 );
        
        // Row efficiency (too many rows = inefficient)
        if ( isset( $profile['rows'] ) && $profile['rows'] > 1000 ) {
            $efficiency -= min( 20, ( $profile['rows'] - 1000 ) / 100 );
        }
        
        // EXPLAIN analysis
        if ( isset( $profile['explain'] ) ) {
            foreach ( $profile['explain'] as $explain_row ) {
                // Penalty for table scans
                if ( $explain_row['type'] === 'ALL' ) {
                    $efficiency -= 20;
                }
                
                // Penalty for filesort
                if ( strpos( $explain_row['Extra'] ?? '', 'filesort' ) !== false ) {
                    $efficiency -= 10;
                }
                
                // Penalty for temporary tables
                if ( strpos( $explain_row['Extra'] ?? '', 'temporary' ) !== false ) {
                    $efficiency -= 10;
                }
            }
        }
        
        return max( 0, $efficiency );
    }
    
    /**
     * Get query backtrace
     * 
     * @return array
     */
    protected function get_query_backtrace() {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $clean_trace = array();
        
        foreach ( $backtrace as $trace ) {
            // Skip profiler and WordPress core traces
            if ( isset( $trace['class'] ) && 
                 ( $trace['class'] === __CLASS__ || strpos( $trace['class'], 'wpdb' ) !== false ) ) {
                continue;
            }
            
            $clean_trace[] = array(
                'file' => $trace['file'] ?? 'unknown',
                'line' => $trace['line'] ?? 0,
                'function' => $trace['function'] ?? 'unknown'
            );
            
            // Stop at plugin boundary
            if ( isset( $trace['file'] ) && strpos( $trace['file'], 'money-quiz' ) !== false ) {
                break;
            }
        }
        
        return $clean_trace;
    }
    
    /**
     * Analyze query pattern
     * 
     * @param string $query
     */
    protected function analyze_pattern( $query ) {
        // Normalize query for pattern matching
        $normalized = preg_replace( '/\s+/', ' ', trim( $query ) );
        $normalized = preg_replace( '/\d+/', 'N', $normalized );
        $normalized = preg_replace( "/'[^']*'/", "'S'", $normalized );
        
        $pattern_hash = md5( $normalized );
        
        if ( ! isset( $this->patterns[ $pattern_hash ] ) ) {
            $this->patterns[ $pattern_hash ] = array(
                'pattern' => $normalized,
                'count' => 0,
                'total_time' => 0,
                'examples' => array()
            );
        }
        
        $this->patterns[ $pattern_hash ]['count']++;
        $this->patterns[ $pattern_hash ]['total_time'] += end( $this->profiles )['time'];
        
        if ( count( $this->patterns[ $pattern_hash ]['examples'] ) < 3 ) {
            $this->patterns[ $pattern_hash ]['examples'][] = $query;
        }
    }
    
    /**
     * Get profiling report
     * 
     * @return array
     */
    public function get_report() {
        $report = array(
            'summary' => $this->get_summary_stats(),
            'slow_queries' => $this->get_slow_queries(),
            'frequent_patterns' => $this->get_frequent_patterns(),
            'inefficient_queries' => $this->get_inefficient_queries(),
            'recommendations' => $this->get_recommendations()
        );
        
        return $report;
    }
    
    /**
     * Get summary statistics
     * 
     * @return array
     */
    protected function get_summary_stats() {
        $total_queries = count( $this->profiles );
        $total_time = array_sum( array_column( $this->profiles, 'time' ) );
        
        $stats = array(
            'total_queries' => $total_queries,
            'total_time' => $total_time,
            'average_time' => $total_queries > 0 ? $total_time / $total_queries : 0,
            'queries_by_type' => array(),
            'queries_by_table' => array()
        );
        
        foreach ( $this->profiles as $profile ) {
            // By type
            $type = $profile['type'];
            if ( ! isset( $stats['queries_by_type'][ $type ] ) ) {
                $stats['queries_by_type'][ $type ] = 0;
            }
            $stats['queries_by_type'][ $type ]++;
            
            // By table
            foreach ( $profile['tables'] as $table ) {
                if ( ! isset( $stats['queries_by_table'][ $table ] ) ) {
                    $stats['queries_by_table'][ $table ] = 0;
                }
                $stats['queries_by_table'][ $table ]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get slow queries
     * 
     * @param int $limit
     * @return array
     */
    protected function get_slow_queries( $limit = 10 ) {
        $queries = $this->profiles;
        
        // Sort by execution time
        usort( $queries, function( $a, $b ) {
            return $b['time'] <=> $a['time'];
        });
        
        return array_slice( $queries, 0, $limit );
    }
    
    /**
     * Get frequent query patterns
     * 
     * @param int $limit
     * @return array
     */
    protected function get_frequent_patterns( $limit = 10 ) {
        $patterns = $this->patterns;
        
        // Sort by frequency
        uasort( $patterns, function( $a, $b ) {
            return $b['count'] <=> $a['count'];
        });
        
        return array_slice( $patterns, 0, $limit, true );
    }
    
    /**
     * Get inefficient queries
     * 
     * @param int $limit
     * @return array
     */
    protected function get_inefficient_queries( $limit = 10 ) {
        $queries = array_filter( $this->profiles, function( $profile ) {
            return isset( $profile['efficiency'] ) && $profile['efficiency'] < 50;
        });
        
        // Sort by efficiency (ascending)
        usort( $queries, function( $a, $b ) {
            return $a['efficiency'] <=> $b['efficiency'];
        });
        
        return array_slice( $queries, 0, $limit );
    }
    
    /**
     * Get optimization recommendations
     * 
     * @return array
     */
    protected function get_recommendations() {
        $recommendations = array();
        
        // Check for missing indexes
        foreach ( $this->profiles as $profile ) {
            if ( isset( $profile['explain'] ) ) {
                foreach ( $profile['explain'] as $explain ) {
                    if ( $explain['type'] === 'ALL' && isset( $explain['rows'] ) && $explain['rows'] > 1000 ) {
                        $recommendations[] = array(
                            'type' => 'missing_index',
                            'table' => $explain['table'],
                            'message' => "Table '{$explain['table']}' is doing full table scans. Consider adding an index."
                        );
                    }
                }
            }
        }
        
        // Check for N+1 query problems
        foreach ( $this->patterns as $pattern ) {
            if ( $pattern['count'] > 10 && strpos( $pattern['pattern'], 'WHERE' ) !== false ) {
                $recommendations[] = array(
                    'type' => 'n_plus_one',
                    'pattern' => $pattern['pattern'],
                    'count' => $pattern['count'],
                    'message' => "This query pattern is executed {$pattern['count']} times. Consider batching or using JOINs."
                );
            }
        }
        
        // Check for SELECT *
        foreach ( $this->profiles as $profile ) {
            if ( strpos( $profile['query'], 'SELECT *' ) !== false ) {
                $recommendations[] = array(
                    'type' => 'select_star',
                    'query' => substr( $profile['query'], 0, 100 ) . '...',
                    'message' => "Avoid using SELECT *. Specify only the columns you need."
                );
            }
        }
        
        return array_unique( $recommendations, SORT_REGULAR );
    }
    
    /**
     * Save profiles to file
     */
    public function save_profiles() {
        if ( empty( $this->profiles ) ) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $profile_dir = $upload_dir['basedir'] . '/money-quiz-profiles';
        
        if ( ! file_exists( $profile_dir ) ) {
            wp_mkdir_p( $profile_dir );
        }
        
        $filename = $profile_dir . '/query-profile-' . date( 'Y-m-d-H-i-s' ) . '.json';
        
        $data = array(
            'timestamp' => time(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'profiles' => $this->profiles,
            'patterns' => $this->patterns,
            'report' => $this->get_report()
        );
        
        file_put_contents( $filename, json_encode( $data, JSON_PRETTY_PRINT ) );
    }
    
    /**
     * Clear profiling data
     */
    public function clear() {
        $this->profiles = array();
        $this->patterns = array();
        $this->start_times = array();
    }
}