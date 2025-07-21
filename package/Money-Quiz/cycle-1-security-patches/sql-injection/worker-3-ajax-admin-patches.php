<?php
/**
 * Worker 3: SQL Injection Patches for Admin Panel and AJAX Handlers
 * CVSS: 9.8 (Critical)
 * Focus: Admin panel queries and form processing
 */

// PATCH 1: reports.details.admin.php - Line 10
// OLD: $sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." where Prospect_ID=".$_REQUEST['prospect']." ";
// NEW: Use prepared statement
$prospect_id = isset($_REQUEST['prospect']) ? absint($_REQUEST['prospect']) : 0;
if ($prospect_id > 0) {
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_prefix}" . TABLE_MQ_PROSPECTS . " WHERE Prospect_ID = %d",
        $prospect_id
    );
    $results = $wpdb->get_row($sql, OBJECT);
}

// PATCH 2: reports.details.admin.php - Line 16
// OLD: $sql1 = "SELECT * FROM ".$table_prefix.TABLE_MQ_TAKEN." where Prospect_ID=".$_REQUEST['prospect']." order by Taken_ID ASC";
// NEW: Use prepared statement
$sql1 = $wpdb->prepare(
    "SELECT * FROM {$table_prefix}" . TABLE_MQ_TAKEN . " WHERE Prospect_ID = %d ORDER BY Taken_ID ASC",
    $prospect_id
);
$sql_rows = $wpdb->get_results($sql1, OBJECT);

// PATCH 3: reports.details.admin.php - Line 76 (Complex query)
// OLD: Complex concatenated query with IN clause
// NEW: Secure implementation
if (isset($n_tid[2]) && !empty($n_tid[2])) {
    // If $n_tid[2] contains multiple IDs, handle it properly
    if (strpos($n_tid[2], ',') !== false) {
        $taken_ids = array_map('absint', explode(',', $n_tid[2]));
        $placeholders = array_fill(0, count($taken_ids), '%d');
        
        $sql_query = $wpdb->prepare(
            "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, 
                    mq_r.Results_ID, mq_r.Score, mq_r.Taken_ID 
             FROM {$table_prefix}" . TABLE_MQ_RESULTS . " as mq_r 
             LEFT JOIN {$table_prefix}" . TABLE_MQ_MASTER . " as mq_q 
                ON mq_q.Master_ID = mq_r.Master_ID 
             WHERE mq_r.Prospect_ID = %d 
                AND mq_r.Taken_ID IN (" . implode(',', $placeholders) . ") 
             ORDER BY mq_r.Taken_ID ASC",
            array_merge(array($prospect_id), $taken_ids)
        );
    } else {
        // Single ID
        $sql_query = $wpdb->prepare(
            "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, 
                    mq_r.Results_ID, mq_r.Score, mq_r.Taken_ID 
             FROM {$table_prefix}" . TABLE_MQ_RESULTS . " as mq_r 
             LEFT JOIN {$table_prefix}" . TABLE_MQ_MASTER . " as mq_q 
                ON mq_q.Master_ID = mq_r.Master_ID 
             WHERE mq_r.Prospect_ID = %d 
                AND mq_r.Taken_ID = %d 
             ORDER BY mq_r.Taken_ID ASC",
            $prospect_id,
            absint($n_tid[2])
        );
    }
    $sql_rows = $wpdb->get_results($sql_query, OBJECT);
}

// PATCH 4: Add AJAX handler security (currently missing)
// Since the plugin doesn't use proper AJAX, let's add secure AJAX framework
class MoneyQuizAjaxHandler {
    
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_mq_get_prospect', array($this, 'get_prospect'));
        add_action('wp_ajax_mq_update_prospect', array($this, 'update_prospect'));
        add_action('wp_ajax_mq_get_results', array($this, 'get_results'));
    }
    
    /**
     * Get prospect data securely
     */
    public function get_prospect() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mq_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb, $table_prefix;
        
        $prospect_id = isset($_POST['prospect_id']) ? absint($_POST['prospect_id']) : 0;
        
        if ($prospect_id > 0) {
            $prospect = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_PROSPECTS . " WHERE Prospect_ID = %d",
                    $prospect_id
                ),
                OBJECT
            );
            
            if ($prospect) {
                wp_send_json_success($prospect);
            } else {
                wp_send_json_error('Prospect not found');
            }
        } else {
            wp_send_json_error('Invalid prospect ID');
        }
    }
    
    /**
     * Update prospect data securely
     */
    public function update_prospect() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mq_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb, $table_prefix;
        
        $prospect_id = isset($_POST['prospect_id']) ? absint($_POST['prospect_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        
        // Sanitize all data
        $update_data = array();
        if (isset($data['Name'])) {
            $update_data['Name'] = sanitize_text_field($data['Name']);
        }
        if (isset($data['Surname'])) {
            $update_data['Surname'] = sanitize_text_field($data['Surname']);
        }
        if (isset($data['Email'])) {
            $update_data['Email'] = sanitize_email($data['Email']);
        }
        if (isset($data['Telephone'])) {
            $update_data['Telephone'] = sanitize_text_field($data['Telephone']);
        }
        
        if ($prospect_id > 0 && !empty($update_data)) {
            $result = $wpdb->update(
                $table_prefix . TABLE_MQ_PROSPECTS,
                $update_data,
                array('Prospect_ID' => $prospect_id),
                null,
                array('%d')
            );
            
            if ($result !== false) {
                wp_send_json_success('Prospect updated successfully');
            } else {
                wp_send_json_error('Failed to update prospect');
            }
        } else {
            wp_send_json_error('Invalid data');
        }
    }
    
    /**
     * Get quiz results securely
     */
    public function get_results() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mq_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb, $table_prefix;
        
        $prospect_id = isset($_POST['prospect_id']) ? absint($_POST['prospect_id']) : 0;
        $taken_id = isset($_POST['taken_id']) ? absint($_POST['taken_id']) : 0;
        
        if ($prospect_id > 0) {
            $query = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, 
                            mq_r.Results_ID, mq_r.Score, mq_r.Taken_ID 
                     FROM {$table_prefix}" . TABLE_MQ_RESULTS . " as mq_r 
                     LEFT JOIN {$table_prefix}" . TABLE_MQ_MASTER . " as mq_q 
                        ON mq_q.Master_ID = mq_r.Master_ID 
                     WHERE mq_r.Prospect_ID = %d";
            
            $params = array($prospect_id);
            
            if ($taken_id > 0) {
                $query .= " AND mq_r.Taken_ID = %d";
                $params[] = $taken_id;
            }
            
            $query .= " ORDER BY mq_r.Taken_ID ASC";
            
            $results = $wpdb->get_results(
                $wpdb->prepare($query, $params),
                OBJECT
            );
            
            if ($results) {
                wp_send_json_success($results);
            } else {
                wp_send_json_error('No results found');
            }
        } else {
            wp_send_json_error('Invalid prospect ID');
        }
    }
}

// Initialize AJAX handler
new MoneyQuizAjaxHandler();

// PATCH 5: Add nonce generation helper
function mq_get_ajax_nonce() {
    return wp_create_nonce('mq_ajax_nonce');
}

// PATCH 6: Add JavaScript template for secure AJAX calls
?>
<script type="text/javascript">
// Secure AJAX template
jQuery(document).ready(function($) {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var ajax_nonce = '<?php echo mq_get_ajax_nonce(); ?>';
    
    // Example: Get prospect data
    function getProspect(prospectId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mq_get_prospect',
                nonce: ajax_nonce,
                prospect_id: prospectId
            },
            success: function(response) {
                if (response.success) {
                    console.log('Prospect data:', response.data);
                } else {
                    console.error('Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }
});
</script>