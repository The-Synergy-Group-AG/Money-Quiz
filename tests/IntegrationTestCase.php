<?php
/**
 * Integration test case for Money Quiz tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests;

/**
 * Integration test case class
 */
abstract class IntegrationTestCase extends TestCase {
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create database tables
        $this->setup_database_tables();
        
        // Initialize plugin
        $this->plugin->run();
    }
    
    /**
     * Teardown after each test
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Clean up database
        $this->cleanup_database_tables();
    }
    
    /**
     * Setup database tables
     */
    protected function setup_database_tables(): void {
        global $wpdb;
        
        // Create modern tables
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quizzes table
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
            KEY is_active (is_active)
        ) $charset_collate;";
        
        $wpdb->query( $sql );
        
        // Questions table
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
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        $wpdb->query( $sql );
        
        // Archetypes table
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
            KEY is_active (is_active)
        ) $charset_collate;";
        
        $wpdb->query( $sql );
        
        // Results table
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
            KEY completed_at (completed_at)
        ) $charset_collate;";
        
        $wpdb->query( $sql );
        
        // Prospects table
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
            UNIQUE KEY email (email)
        ) $charset_collate;";
        
        $wpdb->query( $sql );
    }
    
    /**
     * Cleanup database tables
     */
    protected function cleanup_database_tables(): void {
        global $wpdb;
        
        // Drop tables in reverse order to avoid foreign key issues
        $tables = [
            'money_quiz_results',
            'money_quiz_prospects',
            'money_quiz_questions',
            'money_quiz_archetypes',
            'money_quiz_quizzes',
        ];
        
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
        }
    }
    
    /**
     * Create test quiz in database
     * 
     * @param array $data Quiz data
     * @return int Quiz ID
     */
    protected function create_test_quiz( array $data = [] ): int {
        global $wpdb;
        
        $defaults = [
            'title' => 'Test Quiz',
            'slug' => 'test-quiz',
            'description' => 'Test quiz description',
            'settings' => json_encode( [ 'show_progress' => true ] ),
            'is_active' => 1,
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_quizzes',
            $data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create test archetype in database
     * 
     * @param array $data Archetype data
     * @return int Archetype ID
     */
    protected function create_test_archetype( array $data = [] ): int {
        global $wpdb;
        
        $defaults = [
            'name' => 'Test Archetype',
            'slug' => 'test-archetype',
            'description' => 'Test archetype description',
            'is_active' => 1,
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_archetypes',
            $data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create test prospect in database
     * 
     * @param array $data Prospect data
     * @return int Prospect ID
     */
    protected function create_test_prospect( array $data = [] ): int {
        global $wpdb;
        
        $defaults = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_prospects',
            $data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Simulate form submission
     * 
     * @param array $data Form data
     */
    protected function simulate_form_submission( array $data ): void {
        $_POST = array_merge( $_POST, $data );
        $_REQUEST = array_merge( $_REQUEST, $data );
    }
    
    /**
     * Simulate AJAX request
     * 
     * @param string $action AJAX action
     * @param array  $data   Request data
     */
    protected function simulate_ajax_request( string $action, array $data = [] ): void {
        $_POST['action'] = $action;
        $_REQUEST['action'] = $action;
        
        $this->simulate_form_submission( $data );
        
        // Set up AJAX constants
        if ( ! defined( 'DOING_AJAX' ) ) {
            define( 'DOING_AJAX', true );
        }
    }
    
    /**
     * Assert database table exists
     * 
     * @param string $table Table name (without prefix)
     */
    protected function assertTableExists( string $table ): void {
        global $wpdb;
        
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" );
        
        $this->assertEquals( $full_table, $exists, "Table {$full_table} does not exist" );
    }
    
    /**
     * Assert database record exists
     * 
     * @param string $table      Table name (without prefix)
     * @param array  $conditions Where conditions
     */
    protected function assertRecordExists( string $table, array $conditions ): void {
        global $wpdb;
        
        $where_parts = [];
        $where_values = [];
        
        foreach ( $conditions as $column => $value ) {
            $where_parts[] = "{$column} = %s";
            $where_values[] = $value;
        }
        
        $where_clause = implode( ' AND ', $where_parts );
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$where_clause}",
            $where_values
        );
        
        $count = $wpdb->get_var( $query );
        
        $this->assertGreaterThan( 0, $count, "No record found in {$table} matching conditions" );
    }
}