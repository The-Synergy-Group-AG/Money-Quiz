<?php
/**
 * Worker 9: Access Control - Capability Checks and Role-Based Security
 * CVSS: 7.2 (High)
 * Focus: Implement proper permission checks throughout the plugin
 */

// PATCH 1: Define custom capabilities for Money Quiz
class MoneyQuizCapabilities {
    
    // Custom capabilities
    const MANAGE_QUIZ = 'mq_manage_quiz';
    const EDIT_QUESTIONS = 'mq_edit_questions';
    const VIEW_REPORTS = 'mq_view_reports';
    const EXPORT_DATA = 'mq_export_data';
    const MANAGE_SETTINGS = 'mq_manage_settings';
    
    /**
     * Add capabilities to roles on activation
     */
    public static function add_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::MANAGE_QUIZ);
            $admin->add_cap(self::EDIT_QUESTIONS);
            $admin->add_cap(self::VIEW_REPORTS);
            $admin->add_cap(self::EXPORT_DATA);
            $admin->add_cap(self::MANAGE_SETTINGS);
        }
        
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap(self::EDIT_QUESTIONS);
            $editor->add_cap(self::VIEW_REPORTS);
        }
    }
    
    /**
     * Remove capabilities on deactivation
     */
    public static function remove_capabilities() {
        $roles = array('administrator', 'editor');
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->remove_cap(self::MANAGE_QUIZ);
                $role->remove_cap(self::EDIT_QUESTIONS);
                $role->remove_cap(self::VIEW_REPORTS);
                $role->remove_cap(self::EXPORT_DATA);
                $role->remove_cap(self::MANAGE_SETTINGS);
            }
        }
    }
    
    /**
     * Check if current user has capability
     */
    public static function current_user_can($capability) {
        // Map general capabilities to specific ones
        $capability_map = array(
            'manage_options' => self::MANAGE_QUIZ,
            'edit_posts' => self::EDIT_QUESTIONS,
            'read' => self::VIEW_REPORTS
        );
        
        // Check custom capability first
        if (current_user_can($capability)) {
            return true;
        }
        
        // Check mapped capability
        if (isset($capability_map[$capability])) {
            return current_user_can($capability_map[$capability]);
        }
        
        return false;
    }
}

// PATCH 2: Secure menu registration with capability checks
class MoneyQuizAdminMenu {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menus'));
    }
    
    public function register_menus() {
        // Main menu - requires manage capability
        add_menu_page(
            __('Money Quiz', 'money-quiz'),
            __('Money Quiz', 'money-quiz'),
            MoneyQuizCapabilities::MANAGE_QUIZ,
            'mq_questions',
            array($this, 'questions_page'),
            'dashicons-chart-pie',
            30
        );
        
        // Questions submenu
        add_submenu_page(
            'mq_questions',
            __('Questions', 'money-quiz'),
            __('Questions', 'money-quiz'),
            MoneyQuizCapabilities::EDIT_QUESTIONS,
            'mq_questions',
            array($this, 'questions_page')
        );
        
        // Reports submenu
        add_submenu_page(
            'mq_questions',
            __('Reports', 'money-quiz'),
            __('Reports', 'money-quiz'),
            MoneyQuizCapabilities::VIEW_REPORTS,
            'mq_reports',
            array($this, 'reports_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'mq_questions',
            __('Settings', 'money-quiz'),
            __('Settings', 'money-quiz'),
            MoneyQuizCapabilities::MANAGE_SETTINGS,
            'mq_settings',
            array($this, 'settings_page')
        );
    }
    
    public function questions_page() {
        // Verify capability
        if (!current_user_can(MoneyQuizCapabilities::EDIT_QUESTIONS)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'money-quiz'));
        }
        
        // Include questions admin page
        include MONEYQUIZ__PLUGIN_DIR . 'questions.admin.php';
    }
    
    public function reports_page() {
        // Verify capability
        if (!current_user_can(MoneyQuizCapabilities::VIEW_REPORTS)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'money-quiz'));
        }
        
        // Include reports admin page
        include MONEYQUIZ__PLUGIN_DIR . 'reports.admin.php';
    }
    
    public function settings_page() {
        // Verify capability
        if (!current_user_can(MoneyQuizCapabilities::MANAGE_SETTINGS)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'money-quiz'));
        }
        
        // Include settings admin page
        include MONEYQUIZ__PLUGIN_DIR . 'settings.admin.php';
    }
}

// PATCH 3: Add capability checks to all admin actions
class MoneyQuizAccessControl {
    
    /**
     * Check access for question operations
     */
    public static function check_question_access($action = 'edit') {
        $required_cap = MoneyQuizCapabilities::EDIT_QUESTIONS;
        
        if ($action === 'delete') {
            $required_cap = MoneyQuizCapabilities::MANAGE_QUIZ;
        }
        
        if (!current_user_can($required_cap)) {
            wp_die(
                __('You do not have permission to perform this action.', 'money-quiz'),
                __('Permission Denied', 'money-quiz'),
                array('response' => 403)
            );
        }
    }
    
    /**
     * Check access for report viewing
     */
    public static function check_report_access($report_type = 'general') {
        $required_cap = MoneyQuizCapabilities::VIEW_REPORTS;
        
        if ($report_type === 'export') {
            $required_cap = MoneyQuizCapabilities::EXPORT_DATA;
        }
        
        if (!current_user_can($required_cap)) {
            wp_die(
                __('You do not have permission to view this report.', 'money-quiz'),
                __('Permission Denied', 'money-quiz'),
                array('response' => 403)
            );
        }
    }
    
    /**
     * Check access for settings modification
     */
    public static function check_settings_access() {
        if (!current_user_can(MoneyQuizCapabilities::MANAGE_SETTINGS)) {
            wp_die(
                __('You do not have permission to modify settings.', 'money-quiz'),
                __('Permission Denied', 'money-quiz'),
                array('response' => 403)
            );
        }
    }
    
    /**
     * Filter data based on user permissions
     */
    public static function filter_data_by_permission($data, $context = 'view') {
        if (!is_array($data)) {
            return $data;
        }
        
        // Remove sensitive data for users without export permission
        if (!current_user_can(MoneyQuizCapabilities::EXPORT_DATA)) {
            $sensitive_fields = array('email', 'telephone', 'ip_address');
            
            foreach ($data as &$item) {
                if (is_array($item)) {
                    foreach ($sensitive_fields as $field) {
                        if (isset($item[$field])) {
                            $item[$field] = __('[Hidden]', 'money-quiz');
                        }
                    }
                } elseif (is_object($item)) {
                    foreach ($sensitive_fields as $field) {
                        if (property_exists($item, $field)) {
                            $item->$field = __('[Hidden]', 'money-quiz');
                        }
                    }
                }
            }
        }
        
        return $data;
    }
}

// PATCH 4: Update existing admin pages with access control
// Add to top of questions.admin.php
MoneyQuizAccessControl::check_question_access();

// Add to question save handler
if (isset($_POST['save_question'])) {
    MoneyQuizAccessControl::check_question_access('edit');
    // Process save...
}

// Add to question delete handler
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    MoneyQuizAccessControl::check_question_access('delete');
    // Process delete...
}

// PATCH 5: Implement row-level security for prospects
class MoneyQuizRowSecurity {
    
    /**
     * Check if user can access specific prospect
     */
    public static function can_access_prospect($prospect_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Admins can access all prospects
        if (current_user_can(MoneyQuizCapabilities::MANAGE_QUIZ)) {
            return true;
        }
        
        // Check if user created this prospect (if tracking is implemented)
        global $wpdb, $table_prefix;
        
        $created_by = $wpdb->get_var($wpdb->prepare(
            "SELECT created_by FROM {$table_prefix}" . TABLE_MQ_PROSPECTS . " WHERE Prospect_ID = %d",
            $prospect_id
        ));
        
        return $created_by == $user_id;
    }
    
    /**
     * Filter query results by user access
     */
    public static function add_user_filter_to_query($query, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Admins see everything
        if (current_user_can(MoneyQuizCapabilities::MANAGE_QUIZ)) {
            return $query;
        }
        
        // Add user filter
        $user_filter = " AND created_by = " . intval($user_id);
        
        // Insert before ORDER BY or at end
        if (stripos($query, 'ORDER BY') !== false) {
            $query = str_ireplace('ORDER BY', $user_filter . ' ORDER BY', $query);
        } else {
            $query .= $user_filter;
        }
        
        return $query;
    }
}

// PATCH 6: AJAX endpoint security
add_action('wp_ajax_mq_check_permission', function() {
    $capability = sanitize_key($_POST['capability'] ?? '');
    $context = sanitize_key($_POST['context'] ?? '');
    
    if (empty($capability)) {
        wp_send_json_error('No capability specified');
    }
    
    $has_permission = false;
    
    switch ($capability) {
        case 'edit_question':
            $has_permission = current_user_can(MoneyQuizCapabilities::EDIT_QUESTIONS);
            break;
        case 'view_report':
            $has_permission = current_user_can(MoneyQuizCapabilities::VIEW_REPORTS);
            break;
        case 'export_data':
            $has_permission = current_user_can(MoneyQuizCapabilities::EXPORT_DATA);
            break;
        case 'manage_settings':
            $has_permission = current_user_can(MoneyQuizCapabilities::MANAGE_SETTINGS);
            break;
        default:
            $has_permission = current_user_can($capability);
    }
    
    wp_send_json_success(array(
        'has_permission' => $has_permission,
        'capability' => $capability,
        'context' => $context
    ));
});

// PATCH 7: Frontend access control for quiz results
class MoneyQuizFrontendSecurity {
    
    /**
     * Generate secure access token for quiz results
     */
    public static function generate_access_token($taken_id, $email) {
        $data = array(
            'taken_id' => $taken_id,
            'email' => $email,
            'timestamp' => time()
        );
        
        $token = wp_hash(serialize($data), 'auth');
        
        // Store token with expiration (24 hours)
        set_transient('mq_access_' . $token, $data, DAY_IN_SECONDS);
        
        return $token;
    }
    
    /**
     * Verify access token
     */
    public static function verify_access_token($token, $taken_id) {
        $data = get_transient('mq_access_' . $token);
        
        if (!$data || !is_array($data)) {
            return false;
        }
        
        return $data['taken_id'] == $taken_id;
    }
    
    /**
     * Check if user can view quiz results
     */
    public static function can_view_results($taken_id) {
        // Logged in users with report permission
        if (is_user_logged_in() && current_user_can(MoneyQuizCapabilities::VIEW_REPORTS)) {
            return true;
        }
        
        // Check access token
        if (isset($_GET['token'])) {
            return self::verify_access_token($_GET['token'], $taken_id);
        }
        
        // Check if viewing own results (session-based)
        if (isset($_SESSION['mq_taken_' . $taken_id])) {
            return true;
        }
        
        return false;
    }
}

// PATCH 8: Role management UI
class MoneyQuizRoleManager {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_role_menu'));
    }
    
    public function add_role_menu() {
        add_submenu_page(
            'mq_questions',
            __('Role Management', 'money-quiz'),
            __('Roles', 'money-quiz'),
            'manage_options',
            'mq_roles',
            array($this, 'render_role_page')
        );
    }
    
    public function render_role_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'money-quiz'));
        }
        
        $roles = wp_roles();
        $capabilities = array(
            MoneyQuizCapabilities::MANAGE_QUIZ => __('Manage Quiz', 'money-quiz'),
            MoneyQuizCapabilities::EDIT_QUESTIONS => __('Edit Questions', 'money-quiz'),
            MoneyQuizCapabilities::VIEW_REPORTS => __('View Reports', 'money-quiz'),
            MoneyQuizCapabilities::EXPORT_DATA => __('Export Data', 'money-quiz'),
            MoneyQuizCapabilities::MANAGE_SETTINGS => __('Manage Settings', 'money-quiz')
        );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Money Quiz Role Management', 'money-quiz'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('mq_update_roles'); ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Role', 'money-quiz'); ?></th>
                            <?php foreach ($capabilities as $cap => $label): ?>
                                <th><?php echo esc_html($label); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles->roles as $role_name => $role_data): ?>
                            <tr>
                                <td><strong><?php echo esc_html($role_data['name']); ?></strong></td>
                                <?php foreach ($capabilities as $cap => $label): ?>
                                    <td>
                                        <input type="checkbox" 
                                               name="roles[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]" 
                                               value="1"
                                               <?php checked(isset($role_data['capabilities'][$cap])); ?> />
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button-primary">
                        <?php esc_html_e('Update Roles', 'money-quiz'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
}

// Initialize role manager
new MoneyQuizRoleManager();