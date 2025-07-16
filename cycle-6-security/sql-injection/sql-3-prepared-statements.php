<?php
/**
 * Prepared Statements Helper
 * 
 * @package MoneyQuiz\Security\SQL
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\SQL;

/**
 * Prepared Statement Manager
 */
class PreparedStatements {
    
    private $wpdb;
    private $statements = [];
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Prepare SELECT statement
     */
    public function prepareSelect($table, $fields = '*', $conditions = []) {
        $table = $this->wpdb->prefix . $table;
        
        if (is_array($fields)) {
            $fields = implode(', ', array_map([$this, 'escapeIdentifier'], $fields));
        }
        
        $query = "SELECT {$fields} FROM {$table}";
        
        if (!empty($conditions)) {
            $where = $this->buildWhereClause($conditions);
            $query .= " WHERE " . $where['clause'];
            return $this->wpdb->prepare($query, $where['values']);
        }
        
        return $query;
    }
    
    /**
     * Prepare INSERT statement
     */
    public function prepareInsert($table, array $data) {
        $table = $this->wpdb->prefix . $table;
        
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '%s');
        
        $fields = array_map([$this, 'escapeIdentifier'], $fields);
        
        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        return $this->wpdb->prepare($query, $values);
    }
    
    /**
     * Prepare UPDATE statement
     */
    public function prepareUpdate($table, array $data, array $conditions) {
        $table = $this->wpdb->prefix . $table;
        
        $set_clause = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $set_clause[] = $this->escapeIdentifier($field) . ' = %s';
            $values[] = $value;
        }
        
        $query = sprintf(
            "UPDATE %s SET %s",
            $table,
            implode(', ', $set_clause)
        );
        
        if (!empty($conditions)) {
            $where = $this->buildWhereClause($conditions);
            $query .= " WHERE " . $where['clause'];
            $values = array_merge($values, $where['values']);
        }
        
        return $this->wpdb->prepare($query, $values);
    }
    
    /**
     * Prepare DELETE statement
     */
    public function prepareDelete($table, array $conditions) {
        $table = $this->wpdb->prefix . $table;
        
        $query = "DELETE FROM {$table}";
        
        if (!empty($conditions)) {
            $where = $this->buildWhereClause($conditions);
            $query .= " WHERE " . $where['clause'];
            return $this->wpdb->prepare($query, $where['values']);
        }
        
        return $query;
    }
    
    /**
     * Execute prepared statement
     */
    public function execute($query) {
        return $this->wpdb->query($query);
    }
    
    /**
     * Get results from prepared statement
     */
    public function getResults($query, $output = OBJECT) {
        return $this->wpdb->get_results($query, $output);
    }
    
    /**
     * Get single row from prepared statement
     */
    public function getRow($query, $output = OBJECT) {
        return $this->wpdb->get_row($query, $output);
    }
    
    /**
     * Get single value from prepared statement
     */
    public function getVar($query) {
        return $this->wpdb->get_var($query);
    }
    
    /**
     * Build WHERE clause
     */
    private function buildWhereClause(array $conditions) {
        $clause_parts = [];
        $values = [];
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // IN clause
                $placeholders = array_fill(0, count($value), '%s');
                $clause_parts[] = $this->escapeIdentifier($field) . ' IN (' . implode(', ', $placeholders) . ')';
                $values = array_merge($values, $value);
            } elseif ($value === null) {
                $clause_parts[] = $this->escapeIdentifier($field) . ' IS NULL';
            } else {
                $clause_parts[] = $this->escapeIdentifier($field) . ' = %s';
                $values[] = $value;
            }
        }
        
        return [
            'clause' => implode(' AND ', $clause_parts),
            'values' => $values
        ];
    }
    
    /**
     * Escape identifier (table/field name)
     */
    private function escapeIdentifier($identifier) {
        // Remove any backticks first
        $identifier = str_replace('`', '', $identifier);
        
        // Only allow alphanumeric, underscore, and dot
        $identifier = preg_replace('/[^a-zA-Z0-9_.]/', '', $identifier);
        
        // Handle table.field notation
        if (strpos($identifier, '.') !== false) {
            $parts = explode('.', $identifier, 2);
            return '`' . $parts[0] . '`.`' . $parts[1] . '`';
        }
        
        return '`' . $identifier . '`';
    }
}