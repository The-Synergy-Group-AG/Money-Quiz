<?php
/**
 * SQL Query Builder Methods
 * 
 * @package MoneyQuiz\Security\SQL
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\SQL;

/**
 * Query Builder Methods (continuation)
 */
trait QueryBuilderMethods {
    
    /**
     * Build SELECT query
     */
    protected function buildSelect() {
        $fields = $this->sanitizeFields($this->fields);
        $query = "SELECT " . implode(', ', $fields) . " FROM {$this->table}";
        
        $query .= $this->buildWhere();
        $query .= $this->buildOrderBy();
        $query .= $this->buildLimit();
        
        return $query;
    }
    
    /**
     * Build INSERT query
     */
    protected function buildInsert() {
        $fields = array_keys($this->values);
        $placeholders = array_fill(0, count($fields), '%s');
        
        foreach ($this->values as $value) {
            $this->bindings[] = $value;
        }
        
        $query = "INSERT INTO {$this->table} ";
        $query .= "(" . implode(', ', array_map([$this, 'sanitizeField'], $fields)) . ") ";
        $query .= "VALUES (" . implode(', ', $placeholders) . ")";
        
        return $query;
    }
    
    /**
     * Build UPDATE query
     */
    protected function buildUpdate() {
        $sets = [];
        
        foreach ($this->values as $field => $value) {
            $sets[] = $this->sanitizeField($field) . " = %s";
            $this->bindings[] = $value;
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $query .= $this->buildWhere();
        
        return $query;
    }
    
    /**
     * Build DELETE query
     */
    protected function buildDelete() {
        $query = "DELETE FROM {$this->table}";
        $query .= $this->buildWhere();
        return $query;
    }
    
    /**
     * Build WHERE clause
     */
    protected function buildWhere() {
        if (empty($this->conditions)) {
            return '';
        }
        
        $where = ' WHERE ';
        $parts = [];
        
        foreach ($this->conditions as $index => $condition) {
            $field = $this->sanitizeField($condition['field']);
            $operator = $this->sanitizeOperator($condition['operator']);
            
            if ($index > 0) {
                $parts[] = $condition['type'];
            }
            
            if ($condition['value'] === null && $operator === '=') {
                $parts[] = "{$field} IS NULL";
            } elseif ($condition['value'] === null && $operator === '!=') {
                $parts[] = "{$field} IS NOT NULL";
            } else {
                $parts[] = "{$field} {$operator} %s";
                $this->bindings[] = $condition['value'];
            }
        }
        
        return $where . implode(' ', $parts);
    }
    
    /**
     * Build ORDER BY clause
     */
    protected function buildOrderBy() {
        if (empty($this->order)) {
            return '';
        }
        
        $parts = [];
        foreach ($this->order as $order) {
            $field = $this->sanitizeField($order['field']);
            $direction = in_array($order['direction'], ['ASC', 'DESC']) ? $order['direction'] : 'ASC';
            $parts[] = "{$field} {$direction}";
        }
        
        return ' ORDER BY ' . implode(', ', $parts);
    }
    
    /**
     * Build LIMIT clause
     */
    protected function buildLimit() {
        if ($this->limit === null) {
            return '';
        }
        
        $limit = ' LIMIT ' . $this->limit;
        
        if ($this->offset !== null) {
            $limit .= ' OFFSET ' . $this->offset;
        }
        
        return $limit;
    }
    
    /**
     * Sanitize field name
     */
    protected function sanitizeField($field) {
        // Remove any non-alphanumeric characters except underscore and dot
        $field = preg_replace('/[^a-zA-Z0-9_.]/', '', $field);
        
        // Handle table.field notation
        if (strpos($field, '.') !== false) {
            list($table, $field) = explode('.', $field, 2);
            return $this->sanitizeField($table) . '.' . $this->sanitizeField($field);
        }
        
        return '`' . $field . '`';
    }
    
    /**
     * Sanitize fields array
     */
    protected function sanitizeFields($fields) {
        if ($fields === ['*']) {
            return $fields;
        }
        
        return array_map([$this, 'sanitizeField'], $fields);
    }
    
    /**
     * Sanitize operator
     */
    protected function sanitizeOperator($operator) {
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        
        $operator = strtoupper($operator);
        
        return in_array($operator, $allowed) ? $operator : '=';
    }
}