<?php
/**
 * SQL Injection Prevention Loader
 * 
 * @package MoneyQuiz\Security\SQL
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\SQL;

// Include the trait first
require_once __DIR__ . '/sql-2-query-builder-methods.php';

// Extend QueryBuilder with trait
class QueryBuilder {
    use QueryBuilderMethods;
    
    private $wpdb;
    private $table;
    private $type = 'SELECT';
    private $fields = ['*'];
    private $conditions = [];
    private $joins = [];
    private $order = [];
    private $limit = null;
    private $offset = null;
    private $values = [];
    private $bindings = [];
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    // Methods from sql-1-query-builder.php are inherited via trait
    // Additional methods specific to QueryBuilder go here
}

// Load other components
require_once __DIR__ . '/sql-3-prepared-statements.php';
require_once __DIR__ . '/sql-4-validation-layer.php';

/**
 * SQL Security Manager
 */
class SqlSecurity {
    
    private static $instance = null;
    private $validator;
    private $statements;
    
    private function __construct() {
        $this->validator = new SqlValidator();
        $this->statements = new PreparedStatements();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create query builder
     */
    public function query() {
        return new QueryBuilder();
    }
    
    /**
     * Get prepared statements helper
     */
    public function prepared() {
        return $this->statements;
    }
    
    /**
     * Validate input
     */
    public function validate($input) {
        return SqlValidator::validate($input);
    }
    
    /**
     * Initialize SQL security
     */
    public static function init() {
        // Add validation filters
        add_filter('money_quiz_sql_input', [SqlValidator::class, 'validate']);
        add_filter('money_quiz_table_name', [SqlValidator::class, 'validateTableName']);
        add_filter('money_quiz_field_name', [SqlValidator::class, 'validateFieldName']);
        
        // Register helper functions
        self::registerHelpers();
    }
    
    /**
     * Register helper functions
     */
    private static function registerHelpers() {
        if (!function_exists('money_quiz_db')) {
            function money_quiz_db() {
                return SqlSecurity::getInstance();
            }
        }
        
        if (!function_exists('money_quiz_query')) {
            function money_quiz_query() {
                return SqlSecurity::getInstance()->query();
            }
        }
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [SqlSecurity::class, 'init']);