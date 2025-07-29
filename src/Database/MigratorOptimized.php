<?php
/**
 * Optimized Database Migrator
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database;

/**
 * Handles database migrations with performance optimizations
 */
class MigratorOptimized extends Migrator {
    
    /**
     * Create all tables with optimized indexes
     * 
     * @return void
     */
    protected function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quizzes table with optimized indexes
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_quizzes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            settings longtext,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY created_at (created_at),
            KEY active_recent (is_active, created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Questions table with composite indexes
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_questions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) UNSIGNED NOT NULL,
            question text NOT NULL,
            question_type varchar(50) DEFAULT 'multiple_choice',
            options longtext,
            archetype_weights longtext,
            sort_order int(11) DEFAULT 0,
            is_required tinyint(1) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY sort_order (sort_order),
            KEY quiz_active_sort (quiz_id, is_active, sort_order),
            KEY active_required (is_active, is_required)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Archetypes table with optimized indexes
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_archetypes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            traits longtext,
            recommendations longtext,
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY active_sort (is_active, sort_order)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Results table with optimized indexes for reporting
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_results (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) UNSIGNED NOT NULL,
            prospect_id bigint(20) UNSIGNED,
            archetype_id bigint(20) UNSIGNED,
            answers longtext,
            score decimal(5,2),
            completed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY prospect_id (prospect_id),
            KEY archetype_id (archetype_id),
            KEY completed_at (completed_at),
            KEY quiz_date (quiz_id, completed_at),
            KEY archetype_date (archetype_id, completed_at),
            KEY date_score (completed_at, score)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Prospects table with optimized indexes
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_prospects (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            name varchar(255),
            phone varchar(50),
            company varchar(255),
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY created_at (created_at),
            KEY name_email (name, email),
            FULLTEXT KEY search_index (name, email, company)
        ) $charset_collate;";
        
        dbDelta( $sql );
    }
    
    /**
     * Add performance monitoring table
     * 
     * @return void
     */
    public function create_performance_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_performance (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            query_type varchar(50) NOT NULL,
            query_hash varchar(32) NOT NULL,
            execution_time float NOT NULL,
            memory_usage bigint(20) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY query_type (query_type),
            KEY timestamp (timestamp),
            KEY type_time (query_type, execution_time)
        ) $charset_collate;";
        
        dbDelta( $sql );
    }
    
    /**
     * Optimize existing tables
     * 
     * @return void
     */
    public function optimize_tables(): void {
        global $wpdb;
        
        $tables = [
            'money_quiz_quizzes',
            'money_quiz_questions', 
            'money_quiz_archetypes',
            'money_quiz_results',
            'money_quiz_prospects',
        ];
        
        foreach ( $tables as $table ) {
            $full_table = $wpdb->prefix . $table;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" ) ) {
                $wpdb->query( "OPTIMIZE TABLE {$full_table}" );
            }
        }
    }
    
    /**
     * Add missing indexes to existing tables
     * 
     * @return void
     */
    public function add_missing_indexes(): void {
        global $wpdb;
        
        // Check and add composite indexes
        $indexes = [
            [
                'table' => 'money_quiz_questions',
                'name' => 'quiz_active_sort',
                'columns' => 'quiz_id, is_active, sort_order',
            ],
            [
                'table' => 'money_quiz_results',
                'name' => 'quiz_date',
                'columns' => 'quiz_id, completed_at',
            ],
            [
                'table' => 'money_quiz_results',
                'name' => 'date_score',
                'columns' => 'completed_at, score',
            ],
            [
                'table' => 'money_quiz_prospects',
                'name' => 'name_email',
                'columns' => 'name, email',
            ],
        ];
        
        foreach ( $indexes as $index ) {
            $table = $wpdb->prefix . $index['table'];
            
            // Check if index exists
            $index_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = %s 
                 AND table_name = %s 
                 AND index_name = %s",
                DB_NAME,
                $table,
                $index['name']
            ) );
            
            if ( ! $index_exists ) {
                $wpdb->query( "ALTER TABLE {$table} ADD INDEX {$index['name']} ({$index['columns']})" );
            }
        }
    }
}