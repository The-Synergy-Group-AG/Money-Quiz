<?php
/**
 * Worker 5: XSS Prevention Patches - Admin Panel Output Encoding
 * CVSS: 8.8 (High)
 * Focus: WordPress admin area XSS vulnerabilities
 */

// PATCH SET 1: questions.admin.php
// Line 72 - Hidden input field
// OLD: <input type="hidden" name="questionid" value="<?php echo $_REQUEST['questionid'];?>" >
// NEW:
?>
<input type="hidden" name="questionid" value="<?php echo esc_attr(isset($_REQUEST['questionid']) ? $_REQUEST['questionid'] : ''); ?>" />
<?php

// Lines 116-119 - Table output
// OLD: Direct echo of database values
// NEW: Properly escaped table rows
function mq_admin_display_question_row($row) {
    ?>
    <tr>
        <td><?php echo esc_html($row->ID_Unique); ?></td>
        <td><?php echo esc_html($row->Question); ?></td>
        <td><?php echo esc_html($row->Money_Type_Name); ?></td>
        <td><?php echo esc_html($row->Archetype_Name); ?></td>
        <td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mq_questions&questionid=' . intval($row->Master_ID))); ?>">
                <?php esc_html_e('Edit', 'money-quiz'); ?>
            </a>
        </td>
    </tr>
    <?php
}

// PATCH SET 2: reports.details.admin.php
// Line 35 - Link with parameter
// OLD: <a href="<?php echo $admin_page_url?>mq_reports&prospect=<?php echo $_REQUEST['prospect']?>" ...>
// NEW:
$report_url = add_query_arg(array(
    'page' => 'mq_reports',
    'prospect' => intval($_REQUEST['prospect'])
), admin_url('admin.php'));
?>
<a href="<?php echo esc_url($report_url); ?>" class="button">
    <?php esc_html_e('Back to Reports', 'money-quiz'); ?>
</a>
<?php

// PATCH SET 3: reports.page.admin.php
// Line 30 - Hidden input
// OLD: <input type="hidden" name="prospect" value="<?php echo $_REQUEST['prospect']?>">
// NEW:
?>
<input type="hidden" name="prospect" value="<?php echo esc_attr(isset($_REQUEST['prospect']) ? intval($_REQUEST['prospect']) : 0); ?>" />
<?php

// PATCH SET 4: archetypes.admin.php - Multiple instances
// Create helper function for archetype form fields
function mq_archetype_form_field($field_name, $value, $type = 'text') {
    $field_id = 'mq_' . sanitize_key($field_name);
    ?>
    <input type="<?php echo esc_attr($type); ?>" 
           id="<?php echo esc_attr($field_id); ?>" 
           name="<?php echo esc_attr($field_name); ?>" 
           value="<?php echo esc_attr($value); ?>" 
           class="regular-text" />
    <?php
}

// For image fields
function mq_archetype_image_field($field_name, $image_url) {
    ?>
    <div class="mq-image-preview">
        <?php if (!empty($image_url)): ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 200px;" />
        <?php endif; ?>
    </div>
    <input type="text" 
           name="<?php echo esc_attr($field_name); ?>" 
           value="<?php echo esc_url($image_url); ?>" 
           class="regular-text mq-image-url" />
    <button type="button" class="button mq-upload-image">
        <?php esc_html_e('Upload Image', 'money-quiz'); ?>
    </button>
    <?php
}

// PATCH SET 5: quiz.admin.php - Multiple instances
// Create comprehensive form builder for quiz settings
class MoneyQuizAdminForms {
    
    /**
     * Render text input field
     */
    public static function text_field($name, $value, $label = '', $help = '') {
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($name); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <input type="text" 
                       id="<?php echo esc_attr($name); ?>" 
                       name="<?php echo esc_attr($name); ?>" 
                       value="<?php echo esc_attr($value); ?>" 
                       class="regular-text" />
                <?php if ($help): ?>
                    <p class="description"><?php echo esc_html($help); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render textarea field
     */
    public static function textarea_field($name, $value, $label = '', $help = '') {
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($name); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <textarea id="<?php echo esc_attr($name); ?>" 
                          name="<?php echo esc_attr($name); ?>" 
                          rows="5" 
                          cols="50"><?php echo esc_textarea($value); ?></textarea>
                <?php if ($help): ?>
                    <p class="description"><?php echo esc_html($help); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render select field
     */
    public static function select_field($name, $value, $options, $label = '', $help = '') {
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($name); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <select id="<?php echo esc_attr($name); ?>" 
                        name="<?php echo esc_attr($name); ?>">
                    <?php foreach ($options as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" 
                                <?php selected($value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($help): ?>
                    <p class="description"><?php echo esc_html($help); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
}

// PATCH SET 6: reports.admin.php - Table output
// Lines 73-75 - Database values in table
function mq_admin_display_report_row($row) {
    $details_url = add_query_arg(array(
        'page' => 'mq_reports_details',
        'prospect' => intval($row->Prospect_ID)
    ), admin_url('admin.php'));
    
    ?>
    <tr>
        <td><?php echo esc_html($row->Name); ?></td>
        <td><?php echo esc_html($row->Surname); ?></td>
        <td><?php echo esc_html($row->Email); ?></td>
        <td><?php echo esc_html($row->Telephone); ?></td>
        <td><?php echo esc_html($row->created_at); ?></td>
        <td>
            <a href="<?php echo esc_url($details_url); ?>" class="button button-small">
                <?php esc_html_e('View Details', 'money-quiz'); ?>
            </a>
        </td>
    </tr>
    <?php
}

// PATCH SET 7: JavaScript generation protection
function mq_admin_localize_script() {
    $admin_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mq_admin_nonce'),
        'confirm_delete' => __('Are you sure you want to delete this item?', 'money-quiz'),
        'error_message' => __('An error occurred. Please try again.', 'money-quiz'),
        'success_message' => __('Operation completed successfully.', 'money-quiz')
    );
    
    wp_localize_script('mq-admin-script', 'mqAdminData', $admin_data);
}
add_action('admin_enqueue_scripts', 'mq_admin_localize_script');

// PATCH SET 8: Bulk data display protection
class MoneyQuizAdminDisplay {
    
    /**
     * Display admin notice
     */
    public static function admin_notice($message, $type = 'info') {
        $allowed_types = array('error', 'warning', 'success', 'info');
        $type = in_array($type, $allowed_types) ? $type : 'info';
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
    
    /**
     * Display data table with proper escaping
     */
    public static function data_table($columns, $data) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php foreach ($columns as $key => $label): ?>
                        <th scope="col"><?php echo esc_html($label); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($columns as $key => $label): ?>
                            <td>
                                <?php 
                                if (isset($row[$key])) {
                                    if (filter_var($row[$key], FILTER_VALIDATE_URL)) {
                                        echo '<a href="' . esc_url($row[$key]) . '">' . esc_html($row[$key]) . '</a>';
                                    } elseif (filter_var($row[$key], FILTER_VALIDATE_EMAIL)) {
                                        echo '<a href="mailto:' . esc_attr($row[$key]) . '">' . esc_html($row[$key]) . '</a>';
                                    } else {
                                        echo esc_html($row[$key]);
                                    }
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

// PATCH SET 9: AJAX response sanitization
function mq_send_json_response($data, $success = true) {
    if ($success) {
        wp_send_json_success(array_map('esc_html', $data));
    } else {
        wp_send_json_error(esc_html($data));
    }
}

// PATCH SET 10: Global XSS prevention filter
function mq_sanitize_output($content) {
    // For admin area, we can be more permissive with allowed tags
    $allowed_html = array(
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array(),
            'class' => array()
        ),
        'br' => array(),
        'em' => array(),
        'strong' => array(),
        'p' => array('class' => array()),
        'span' => array('class' => array()),
        'div' => array('class' => array(), 'id' => array()),
        'h1' => array('class' => array()),
        'h2' => array('class' => array()),
        'h3' => array('class' => array()),
        'table' => array('class' => array()),
        'tr' => array(),
        'td' => array('colspan' => array()),
        'th' => array('scope' => array())
    );
    
    return wp_kses($content, $allowed_html);
}