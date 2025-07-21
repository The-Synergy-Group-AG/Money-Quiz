<?php
/**
 * Worker 6: CSRF Protection - Form Submissions and State Changes
 * CVSS: 8.8 (High)
 * Focus: Implementing WordPress nonce system for all forms
 */

// CSRF Protection Framework for Money Quiz
class MoneyQuizCSRF {
    
    const NONCE_ACTION_PREFIX = 'mq_';
    const NONCE_LIFETIME = 12 * HOUR_IN_SECONDS; // 12 hours
    
    /**
     * Generate nonce for specific action
     */
    public static function create_nonce($action) {
        return wp_create_nonce(self::NONCE_ACTION_PREFIX . $action);
    }
    
    /**
     * Verify nonce for specific action
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, self::NONCE_ACTION_PREFIX . $action);
    }
    
    /**
     * Add nonce field to form
     */
    public static function nonce_field($action, $name = '_wpnonce', $referer = true) {
        return wp_nonce_field(self::NONCE_ACTION_PREFIX . $action, $name, $referer, false);
    }
    
    /**
     * Check nonce and die if invalid
     */
    public static function check_nonce($action, $nonce_key = '_wpnonce') {
        $nonce = isset($_REQUEST[$nonce_key]) ? $_REQUEST[$nonce_key] : '';
        
        if (!self::verify_nonce($nonce, $action)) {
            wp_die(
                __('Security check failed. Please try again.', 'money-quiz'),
                __('Security Error', 'money-quiz'),
                array('response' => 403)
            );
        }
        
        return true;
    }
}

// PATCH 1: Quiz submission form (quiz.moneycoach.php)
// Add to form generation
function mq_render_quiz_form($quiz_data) {
    ?>
    <form method="post" action="" id="mq-quiz-form">
        <?php echo MoneyQuizCSRF::nonce_field('submit_quiz'); ?>
        <input type="hidden" name="prospect_action" value="submit_new" />
        
        <!-- Quiz fields here -->
        
        <button type="submit"><?php esc_html_e('Submit Quiz', 'money-quiz'); ?></button>
    </form>
    <?php
}

// PATCH 2: Quiz submission handler
// Add to submission processing (line ~283)
if (isset($_POST['prospect_action']) && $_POST['prospect_action'] == "submit_new") {
    // Verify CSRF token
    MoneyQuizCSRF::check_nonce('submit_quiz');
    
    // Process form data...
}

// PATCH 3: Admin form protection - Questions
function mq_admin_question_form($question_data = array()) {
    ?>
    <form method="post" action="">
        <?php echo MoneyQuizCSRF::nonce_field('admin_save_question'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="question"><?php esc_html_e('Question', 'money-quiz'); ?></label></th>
                <td>
                    <input type="text" id="question" name="question" 
                           value="<?php echo esc_attr($question_data['question'] ?? ''); ?>" />
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button-primary">
                <?php esc_html_e('Save Question', 'money-quiz'); ?>
            </button>
        </p>
    </form>
    <?php
}

// Handler for question save
if (isset($_POST['save_question'])) {
    MoneyQuizCSRF::check_nonce('admin_save_question');
    // Process question save...
}

// PATCH 4: Admin settings form protection
function mq_admin_settings_form($settings) {
    ?>
    <form method="post" action="">
        <?php echo MoneyQuizCSRF::nonce_field('admin_save_settings'); ?>
        
        <table class="form-table">
            <?php foreach ($settings as $key => $setting): ?>
            <tr>
                <th><label for="<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($setting['label']); ?>
                </label></th>
                <td>
                    <input type="text" id="<?php echo esc_attr($key); ?>" 
                           name="settings[<?php echo esc_attr($key); ?>]" 
                           value="<?php echo esc_attr($setting['value']); ?>" />
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <p class="submit">
            <button type="submit" class="button-primary">
                <?php esc_html_e('Save Settings', 'money-quiz'); ?>
            </button>
        </p>
    </form>
    <?php
}

// PATCH 5: Archetype form protection
function mq_admin_archetype_form($archetype_data = array()) {
    ?>
    <form method="post" action="" enctype="multipart/form-data">
        <?php echo MoneyQuizCSRF::nonce_field('admin_save_archetype'); ?>
        
        <input type="hidden" name="action" value="save_archetype" />
        
        <!-- Archetype fields with CSRF protection -->
        
        <p class="submit">
            <button type="submit" class="button-primary">
                <?php esc_html_e('Save Archetype', 'money-quiz'); ?>
            </button>
        </p>
    </form>
    <?php
}

// PATCH 6: Delete operations protection
function mq_delete_link($item_id, $item_type) {
    $delete_url = wp_nonce_url(
        add_query_arg(array(
            'action' => 'delete',
            'type' => $item_type,
            'id' => $item_id
        ), admin_url('admin.php')),
        'delete_' . $item_type . '_' . $item_id
    );
    
    return sprintf(
        '<a href="%s" class="delete" onclick="return confirm(\'%s\');">%s</a>',
        esc_url($delete_url),
        esc_js(__('Are you sure you want to delete this item?', 'money-quiz')),
        esc_html__('Delete', 'money-quiz')
    );
}

// Delete handler
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $type = sanitize_key($_GET['type']);
    $id = absint($_GET['id']);
    
    check_admin_referer('delete_' . $type . '_' . $id);
    
    // Perform deletion...
}

// PATCH 7: Bulk actions protection
function mq_bulk_actions_form() {
    ?>
    <form method="post" action="">
        <?php echo MoneyQuizCSRF::nonce_field('bulk_actions'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action">
                    <option value=""><?php esc_html_e('Bulk Actions', 'money-quiz'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'money-quiz'); ?></option>
                    <option value="export"><?php esc_html_e('Export', 'money-quiz'); ?></option>
                </select>
                <button type="submit" class="button action">
                    <?php esc_html_e('Apply', 'money-quiz'); ?>
                </button>
            </div>
        </div>
        
        <!-- Table with checkboxes -->
    </form>
    <?php
}

// Bulk action handler
if (isset($_POST['bulk_action']) && !empty($_POST['bulk_action'])) {
    MoneyQuizCSRF::check_nonce('bulk_actions');
    
    $action = sanitize_key($_POST['bulk_action']);
    $items = isset($_POST['items']) ? array_map('absint', $_POST['items']) : array();
    
    // Process bulk action...
}

// PATCH 8: AJAX CSRF protection
function mq_localize_ajax_security() {
    wp_localize_script('mq-admin-js', 'mq_ajax', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => MoneyQuizCSRF::create_nonce('ajax_request'),
        'saving' => __('Saving...', 'money-quiz'),
        'saved' => __('Saved!', 'money-quiz'),
        'error' => __('An error occurred', 'money-quiz')
    ));
}
add_action('admin_enqueue_scripts', 'mq_localize_ajax_security');

// AJAX handler with CSRF check
function mq_ajax_handler() {
    // Check AJAX referer
    MoneyQuizCSRF::check_nonce('ajax_request', 'nonce');
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 403);
    }
    
    // Process AJAX request...
    $action = sanitize_key($_POST['mq_action']);
    
    switch ($action) {
        case 'save_data':
            // Handle save...
            break;
        case 'get_data':
            // Handle get...
            break;
        default:
            wp_die('Invalid action');
    }
}
add_action('wp_ajax_mq_ajax_handler', 'mq_ajax_handler');

// PATCH 9: Frontend quiz result viewing protection
function mq_view_results_link($taken_id) {
    $view_url = add_query_arg(array(
        'step' => 'results',
        'tid' => $taken_id,
        'token' => wp_create_nonce('view_results_' . $taken_id)
    ), get_permalink());
    
    return esc_url($view_url);
}

// Result viewing handler
if (isset($_GET['step']) && $_GET['step'] === 'results') {
    $taken_id = isset($_GET['tid']) ? absint($_GET['tid']) : 0;
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    
    if (!wp_verify_nonce($token, 'view_results_' . $taken_id)) {
        wp_die(__('Invalid or expired link', 'money-quiz'));
    }
    
    // Display results...
}

// PATCH 10: Session-based CSRF for multi-step quiz
class MoneyQuizSession {
    
    public static function init() {
        if (!session_id()) {
            session_start();
        }
    }
    
    public static function set_quiz_token($quiz_id) {
        self::init();
        $token = wp_generate_password(32, false);
        $_SESSION['mq_quiz_' . $quiz_id] = $token;
        return $token;
    }
    
    public static function verify_quiz_token($quiz_id, $token) {
        self::init();
        return isset($_SESSION['mq_quiz_' . $quiz_id]) && 
               $_SESSION['mq_quiz_' . $quiz_id] === $token;
    }
    
    public static function clear_quiz_token($quiz_id) {
        self::init();
        unset($_SESSION['mq_quiz_' . $quiz_id]);
    }
}

// Multi-step quiz form
function mq_render_quiz_step($step, $quiz_id) {
    $token = MoneyQuizSession::set_quiz_token($quiz_id);
    ?>
    <form method="post" action="">
        <input type="hidden" name="quiz_id" value="<?php echo esc_attr($quiz_id); ?>" />
        <input type="hidden" name="step" value="<?php echo esc_attr($step); ?>" />
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>" />
        <?php echo MoneyQuizCSRF::nonce_field('quiz_step_' . $step); ?>
        
        <!-- Step content -->
        
        <button type="submit"><?php esc_html_e('Next', 'money-quiz'); ?></button>
    </form>
    <?php
}