<?php
/**
 * Worker 2: SQL Injection Patches for class.moneyquiz.php and admin files
 * CVSS: 9.8 (Critical)
 * Focus: Admin panel queries and database operations
 */

// PATCH 1: questions.admin.php - Line 29-33
// OLD: $where = " where Master_ID = ".$_REQUEST['questionid'];
// NEW: Properly sanitize and prepare
if(isset($_REQUEST['questionid']) && $_REQUEST['questionid'] > 0 ){
    $questionid = absint($_REQUEST['questionid']);
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_prefix}" . TABLE_MQ_MASTER . " WHERE Master_ID = %d",
        $questionid
    );
} else {
    $sql = "SELECT * FROM {$table_prefix}" . TABLE_MQ_MASTER;
}

// PATCH 2: questions.admin.php - Search functionality
// OLD: $where_arr[] = " Question like '%".sanitize_text_field($_REQUEST['Question'])."%'";
// NEW: Use proper LIKE escaping
$where_conditions = array();
$where_values = array();

if(isset($_REQUEST['Question']) && $_REQUEST['Question'] != ""){
    $where_conditions[] = "Question LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like($_REQUEST['Question']) . '%';
}

if(isset($_REQUEST['Money_Type']) && $_REQUEST['Money_Type'] != ""){
    $where_conditions[] = "Money_Type = %d";
    $where_values[] = absint($_REQUEST['Money_Type']);
}

if(isset($_REQUEST['Archetype']) && $_REQUEST['Archetype'] != ""){
    $where_conditions[] = "Archetype = %d";
    $where_values[] = absint($_REQUEST['Archetype']);
}

if(!empty($where_conditions)){
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_prefix}" . TABLE_MQ_MASTER . $where_clause,
        $where_values
    );
} else {
    $sql = "SELECT * FROM {$table_prefix}" . TABLE_MQ_MASTER;
}

// PATCH 3: stats.admin.php - IN clause injection
// OLD: $sql = "SELECT * from ".$table_prefix.TABLE_MQ_TAKEN." where Quiz_Length IN ('".implode("','", $show_quiz_length_arr)."')";
// NEW: Properly prepare IN clause
if(!empty($show_quiz_length_arr)){
    $placeholders = array_fill(0, count($show_quiz_length_arr), '%s');
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_prefix}" . TABLE_MQ_TAKEN . " WHERE Quiz_Length IN (" . implode(',', $placeholders) . ")",
        $show_quiz_length_arr
    );
    $results = $wpdb->get_results($sql, OBJECT);
}

// PATCH 4: Generic prepared statement helper
class MoneyQuizDatabase {
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb, $table_prefix;
        $this->wpdb = $wpdb;
        $this->table_prefix = $table_prefix;
    }
    
    /**
     * Safe query builder for SELECT statements
     */
    public function select($table, $conditions = array(), $fields = '*') {
        $table_name = $this->table_prefix . $table;
        $query = "SELECT {$fields} FROM {$table_name}";
        
        if (!empty($conditions)) {
            $where_parts = array();
            $values = array();
            
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    // Handle IN clause
                    $placeholders = array_fill(0, count($value), '%s');
                    $where_parts[] = "{$field} IN (" . implode(',', $placeholders) . ")";
                    $values = array_merge($values, $value);
                } elseif (strpos($value, '%') !== false) {
                    // Handle LIKE clause
                    $where_parts[] = "{$field} LIKE %s";
                    $values[] = $value;
                } else {
                    // Handle regular equality
                    $where_parts[] = "{$field} = %s";
                    $values[] = $value;
                }
            }
            
            $query .= " WHERE " . implode(" AND ", $where_parts);
            return $this->wpdb->get_results($this->wpdb->prepare($query, $values));
        }
        
        return $this->wpdb->get_results($query);
    }
    
    /**
     * Safe INSERT with prepared statements
     */
    public function insert($table, $data) {
        $table_name = $this->table_prefix . $table;
        return $this->wpdb->insert($table_name, $data);
    }
    
    /**
     * Safe UPDATE with prepared statements
     */
    public function update($table, $data, $where) {
        $table_name = $this->table_prefix . $table;
        return $this->wpdb->update($table_name, $data, $where);
    }
    
    /**
     * Safe DELETE with prepared statements
     */
    public function delete($table, $where) {
        $table_name = $this->table_prefix . $table;
        return $this->wpdb->delete($table_name, $where);
    }
}

// Usage example:
$mq_db = new MoneyQuizDatabase();

// Safe select with conditions
$results = $mq_db->select(TABLE_MQ_MASTER, array(
    'Money_Type' => 1,
    'Archetype' => array(1, 5, 9), // IN clause
    'Question' => '%money%' // LIKE clause
));

// Safe insert
$mq_db->insert(TABLE_MQ_PROSPECTS, array(
    'Name' => $name,
    'Email' => $email,
    'Newsletter' => 1
));

// Safe update
$mq_db->update(
    TABLE_MQ_TAKEN,
    array('Prospect_ID' => $prospect_id),
    array('Taken_ID' => $taken_id)
);