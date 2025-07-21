<?php
/**
 * SQL Validation Layer
 * 
 * @package MoneyQuiz\Security\SQL
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\SQL;

/**
 * SQL Input Validator
 */
class SqlValidator {
    
    /**
     * Dangerous SQL patterns
     */
    private static $dangerous_patterns = [
        '/\bUNION\b.*\bSELECT\b/i',
        '/\bDROP\s+TABLE\b/i',
        '/\bDELETE\s+FROM\b.*\bWHERE\s+1\s*=\s*1/i',
        '/\bUPDATE\b.*\bSET\b.*\bWHERE\s+1\s*=\s*1/i',
        '/\bINSERT\s+INTO\b.*\bVALUES\s*\(.*\);.*\bDROP\b/i',
        '/\bEXEC\s*\(/i',
        '/\bEXECUTE\s+IMMEDIATE\b/i',
        '/\bDECLARE\s+@/i',
        '/\bCAST\s*\(.*AS\s+VARCHAR/i',
        '/\bWAITFOR\s+DELAY\b/i',
        '/\b(SLEEP|BENCHMARK)\s*\(/i',
        '/\bINFORMATION_SCHEMA\b/i',
        '/\bSYS\.\w+/i',
        '/\/\*.*\*\//s',
        '/--[^\n]*$/m',
        '/\bOR\s+\d+\s*=\s*\d+/i',
        '/\bAND\s+\d+\s*=\s*\d+/i',
        '/[\'\"]\s*OR\s*[\'\"]\w*[\'\"]\s*=\s*[\'\"]/i'
    ];
    
    /**
     * Validate input for SQL injection
     */
    public static function validate($input) {
        if (!is_string($input)) {
            return true;
        }
        
        foreach (self::$dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate and sanitize table name
     */
    public static function validateTableName($table) {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }
        
        // Check against WordPress tables
        global $wpdb;
        $allowed_tables = [
            'money_quiz_quizzes',
            'money_quiz_questions',
            'money_quiz_results',
            'money_quiz_settings',
            'money_quiz_logs'
        ];
        
        if (!in_array($table, $allowed_tables)) {
            throw new \InvalidArgumentException('Table not allowed');
        }
        
        return $table;
    }
    
    /**
     * Validate field name
     */
    public static function validateFieldName($field) {
        // Only allow alphanumeric, underscore, and dot (for table.field)
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $field)) {
            throw new \InvalidArgumentException('Invalid field name');
        }
        
        // Prevent access to sensitive fields
        $forbidden_fields = [
            'user_pass',
            'user_activation_key',
            'user_email',
            'meta_value'
        ];
        
        $field_lower = strtolower($field);
        foreach ($forbidden_fields as $forbidden) {
            if (strpos($field_lower, $forbidden) !== false) {
                throw new \InvalidArgumentException('Access to field not allowed');
            }
        }
        
        return $field;
    }
    
    /**
     * Validate ORDER BY clause
     */
    public static function validateOrderBy($orderby, $allowed_fields = []) {
        $parts = explode(' ', trim($orderby));
        
        if (count($parts) > 2) {
            throw new \InvalidArgumentException('Invalid ORDER BY clause');
        }
        
        $field = $parts[0];
        $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
        
        // Validate field
        if (!empty($allowed_fields) && !in_array($field, $allowed_fields)) {
            throw new \InvalidArgumentException('Field not allowed in ORDER BY');
        }
        
        self::validateFieldName($field);
        
        // Validate direction
        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException('Invalid sort direction');
        }
        
        return $field . ' ' . $direction;
    }
    
    /**
     * Validate LIMIT clause
     */
    public static function validateLimit($limit, $offset = 0) {
        $limit = intval($limit);
        $offset = intval($offset);
        
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Invalid LIMIT value');
        }
        
        if ($offset < 0) {
            throw new \InvalidArgumentException('Invalid OFFSET value');
        }
        
        return ['limit' => $limit, 'offset' => $offset];
    }
    
    /**
     * Create safe IN clause
     */
    public static function createInClause(array $values, $type = 'string') {
        if (empty($values)) {
            throw new \InvalidArgumentException('Empty values for IN clause');
        }
        
        global $wpdb;
        
        if ($type === 'int') {
            $values = array_map('intval', $values);
            $placeholders = array_fill(0, count($values), '%d');
        } else {
            $placeholders = array_fill(0, count($values), '%s');
        }
        
        return [
            'placeholders' => implode(', ', $placeholders),
            'values' => $values
        ];
    }
}