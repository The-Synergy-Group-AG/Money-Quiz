<?php
/**
 * Worker 1: SQL Injection Patches for quiz.moneycoach.php
 * CVSS: 9.8 (Critical)
 * Lines: 303, 335, 366
 */

// PATCH 1: Line 303 - Email lookup
// OLD: $results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );
// NEW: Use prepared statement
$results = $wpdb->get_row( 
    $wpdb->prepare(
        "SELECT * FROM {$table_prefix}" . TABLE_MQ_PROSPECTS . " WHERE Email = %s",
        $Email
    ),
    OBJECT
);

// PATCH 2: Line 335 - Update with GET parameter
// OLD: array( 'Taken_ID' => $_GET['tid'] )
// NEW: Sanitize and validate input
$taken_id = isset($_GET['tid']) ? absint($_GET['tid']) : 0;
if ($taken_id > 0) {
    $wpdb->update( 
        $table_prefix . TABLE_MQ_TAKEN, 
        array( 
            'Prospect_ID' => $prospect_id
        ), 
        array( 'Taken_ID' => $taken_id ),
        array('%d'),
        array('%d')
    );
}

// PATCH 3: Line 366 - Complex query with multiple injections
// OLD: $sql_qry = "SELECT ... WHERE mq_r.Prospect_ID=".$prospect." and mq_r.Taken_ID IN($tid) ORDER BY mq_r.Taken_ID ASC ";
// NEW: Use prepared statement with proper placeholders
$sql_qry = $wpdb->prepare(
    "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, 
            mq_r.Results_ID, mq_r.Score, mq_r.Taken_ID 
     FROM {$table_prefix}" . TABLE_MQ_RESULTS . " as mq_r 
     LEFT JOIN {$table_prefix}" . TABLE_MQ_MASTER . " as mq_q 
        ON mq_q.Master_ID = mq_r.Master_ID 
     WHERE mq_r.Prospect_ID = %d 
        AND mq_r.Taken_ID = %d 
     ORDER BY mq_r.Taken_ID ASC",
    $prospect,
    $tid
);
$sql_rows = $wpdb->get_results($sql_qry, OBJECT);

// Additional security measures
class MoneyQuizSecurity {
    /**
     * Validate and sanitize email input
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return false;
        }
        return $email;
    }
    
    /**
     * Validate numeric ID
     */
    public static function validate_id($id) {
        $id = absint($id);
        return $id > 0 ? $id : false;
    }
    
    /**
     * Escape table names (WordPress doesn't prepare these)
     */
    public static function escape_table_name($table_name) {
        return '`' . str_replace('`', '', $table_name) . '`';
    }
}