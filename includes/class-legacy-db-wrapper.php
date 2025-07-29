<?php
/**
 * Legacy Database Wrapper
 * 
 * Provides safe database operations for legacy code
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Legacy;

class Legacy_DB_Wrapper {
    
    /**
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * @var array Query log for debugging
     */
    private $query_log = [];
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Safe query execution with automatic escaping
     * 
     * STRATEGIC FIX: Always require prepared statements - no exceptions
     * 
     * @param string $query Query with placeholders
     * @param array  $args  Arguments to escape
     * @return mixed Query results
     * @throws \Exception When query has no placeholders but needs them
     */
    public function safe_query( $query, $args = [] ) {
        // Log the query for debugging
        $this->log_query( $query, $args );
        
        // STRATEGIC: Analyze query for variables that need escaping
        if ( empty( $args ) && $this->query_needs_preparation( $query ) ) {
            throw new \Exception( 
                'Query contains dynamic data but no parameters provided. Use placeholders (%s, %d, %f) and provide arguments.'
            );
        }
        
        // If truly static query (no variables), allow it
        if ( empty( $args ) && ! $this->query_needs_preparation( $query ) ) {
            return $this->wpdb->query( $query );
        }
        
        // Always use prepare when args are provided
        $prepared = $this->wpdb->prepare( $query, $args );
        return $this->wpdb->query( $prepared );
    }
    
    /**
     * Safe get_results with automatic escaping
     */
    public function safe_get_results( $query, $args = [], $output = OBJECT ) {
        $this->log_query( $query, $args );
        
        // STRATEGIC: Enforce prepared statements
        if ( empty( $args ) && $this->query_needs_preparation( $query ) ) {
            throw new \Exception( 
                'Query contains dynamic data but no parameters provided. Use placeholders (%s, %d, %f) and provide arguments.'
            );
        }
        
        if ( empty( $args ) && ! $this->query_needs_preparation( $query ) ) {
            return $this->wpdb->get_results( $query, $output );
        }
        
        $prepared = $this->wpdb->prepare( $query, $args );
        return $this->wpdb->get_results( $prepared, $output );
    }
    
    /**
     * Safe get_row with automatic escaping
     */
    public function safe_get_row( $query, $args = [], $output = OBJECT, $y = 0 ) {
        $this->log_query( $query, $args );
        
        // STRATEGIC: Enforce prepared statements
        if ( empty( $args ) && $this->query_needs_preparation( $query ) ) {
            throw new \Exception( 
                'Query contains dynamic data but no parameters provided. Use placeholders (%s, %d, %f) and provide arguments.'
            );
        }
        
        if ( empty( $args ) && ! $this->query_needs_preparation( $query ) ) {
            return $this->wpdb->get_row( $query, $output, $y );
        }
        
        $prepared = $this->wpdb->prepare( $query, $args );
        return $this->wpdb->get_row( $prepared, $output, $y );
    }
    
    /**
     * Safe get_var with automatic escaping
     */
    public function safe_get_var( $query, $args = [], $x = 0, $y = 0 ) {
        $this->log_query( $query, $args );
        
        // STRATEGIC: Enforce prepared statements
        if ( empty( $args ) && $this->query_needs_preparation( $query ) ) {
            throw new \Exception( 
                'Query contains dynamic data but no parameters provided. Use placeholders (%s, %d, %f) and provide arguments.'
            );
        }
        
        if ( empty( $args ) && ! $this->query_needs_preparation( $query ) ) {
            return $this->wpdb->get_var( $query, $x, $y );
        }
        
        $prepared = $this->wpdb->prepare( $query, $args );
        return $this->wpdb->get_var( $prepared, $x, $y );
    }
    
    /**
     * Check if query needs preparation (contains dynamic data)
     * 
     * STRATEGIC: Detect queries that should use prepared statements
     * 
     * @param string $query SQL query to analyze
     * @return bool True if query appears to contain dynamic data
     */
    private function query_needs_preparation( $query ) {
        // Check for common signs of dynamic data
        $indicators = [
            // PHP variable interpolation
            '/\$\w+/',
            // Concatenation patterns
            '/"\s*\.\s*\$/',
            '/\$\w+\s*\.\s*"/',
            // Common dynamic patterns
            '/WHERE\s+\w+\s*=\s*["\']?[^"\' ]+["\']?\s*(?:AND|OR|;|$)/i',
            // User input patterns
            '/(?:email|name|id|user)\s*=\s*["\']?[^"\' ]+["\']?/i',
        ];
        
        foreach ( $indicators as $pattern ) {
            if ( preg_match( $pattern, $query ) ) {
                return true;
            }
        }
        
        // Check for WHERE clauses with literals that might be user input
        if ( preg_match( '/WHERE.*=\s*["\']?\w+["\']?/i', $query ) ) {
            // If it's not a simple boolean or constant, it might need preparation
            if ( ! preg_match( '/WHERE.*=\s*(?:true|false|null|[0-9]+)\s*(?:AND|OR|;|$)/i', $query ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log queries for debugging
     */
    private function log_query( $query, $args ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $this->query_log[] = [
                'query' => $query,
                'args' => $args,
                'time' => current_time( 'mysql' ),
                'backtrace' => wp_debug_backtrace_summary()
            ];
        }
    }
    
    /**
     * Log security events
     */
    private function log_security_event( $message, $data ) {
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $logger = \MoneyQuiz\Core\Plugin::instance()->get_container()->get( 'error_handler' );
            if ( $logger ) {
                $logger->log_security_event( $message, [ 'query' => $data ] );
            }
        }
        
        // Also log to error log
        error_log( sprintf( '[MoneyQuiz Security] %s: %s', $message, $data ) );
    }
    
    /**
     * Get query log
     */
    public function get_query_log() {
        return $this->query_log;
    }
    
    /**
     * Migrate a legacy query to use placeholders
     * Helper method for gradual migration
     */
    public function migrate_query( $old_query ) {
        // This helps identify queries that need migration
        $migration_hints = [];
        
        // Check for concatenated variables
        if ( preg_match_all( '/"\s*\.\s*\$(\w+)\s*\.\s*"/', $old_query, $matches ) ) {
            $migration_hints[] = 'Contains concatenated variables: ' . implode( ', ', $matches[1] );
        }
        
        // Check for direct variable interpolation
        if ( preg_match_all( '/\$(\w+)/', $old_query, $matches ) ) {
            $migration_hints[] = 'Contains direct variables: ' . implode( ', ', array_unique( $matches[1] ) );
        }
        
        return [
            'original' => $old_query,
            'hints' => $migration_hints,
            'needs_migration' => ! empty( $migration_hints )
        ];
    }
}

// Global function for easy legacy code updates
if ( ! function_exists( 'mq_safe_db' ) ) {
    function mq_safe_db() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new Legacy_DB_Wrapper();
        }
        return $instance;
    }
}