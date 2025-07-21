<?php
/**
 * SQL Query Builder
 * 
 * @package MoneyQuiz\Security\SQL
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\SQL;

/**
 * Secure Query Builder
 */
class QueryBuilder {
    
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
    
    /**
     * Set table
     */
    public function table($table) {
        $this->table = $this->wpdb->prefix . $table;
        return $this;
    }
    
    /**
     * Select fields
     */
    public function select($fields = ['*']) {
        $this->type = 'SELECT';
        $this->fields = is_array($fields) ? $fields : [$fields];
        return $this;
    }
    
    /**
     * Insert data
     */
    public function insert(array $data) {
        $this->type = 'INSERT';
        $this->values = $data;
        return $this;
    }
    
    /**
     * Update data
     */
    public function update(array $data) {
        $this->type = 'UPDATE';
        $this->values = $data;
        return $this;
    }
    
    /**
     * Delete records
     */
    public function delete() {
        $this->type = 'DELETE';
        return $this;
    }
    
    /**
     * Add where condition
     */
    public function where($field, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->conditions[] = [
            'type' => 'AND',
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
        
        return $this;
    }
    
    /**
     * Add OR where condition
     */
    public function orWhere($field, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->conditions[] = [
            'type' => 'OR',
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
        
        return $this;
    }
    
    /**
     * Add order by
     */
    public function orderBy($field, $direction = 'ASC') {
        $this->order[] = [
            'field' => $field,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }
    
    /**
     * Set limit
     */
    public function limit($limit, $offset = null) {
        $this->limit = (int) $limit;
        if ($offset !== null) {
            $this->offset = (int) $offset;
        }
        return $this;
    }
    
    /**
     * Build and prepare query
     */
    public function build() {
        $this->bindings = [];
        
        switch ($this->type) {
            case 'SELECT':
                return $this->buildSelect();
            case 'INSERT':
                return $this->buildInsert();
            case 'UPDATE':
                return $this->buildUpdate();
            case 'DELETE':
                return $this->buildDelete();
        }
    }
    
    /**
     * Execute query
     */
    public function execute() {
        $query = $this->build();
        
        if (empty($this->bindings)) {
            return $this->wpdb->query($query);
        }
        
        $prepared = $this->wpdb->prepare($query, $this->bindings);
        return $this->wpdb->query($prepared);
    }
    
    /**
     * Get results
     */
    public function get() {
        $query = $this->build();
        
        if (empty($this->bindings)) {
            return $this->wpdb->get_results($query);
        }
        
        $prepared = $this->wpdb->prepare($query, $this->bindings);
        return $this->wpdb->get_results($prepared);
    }
    
    /**
     * Get single row
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }
}