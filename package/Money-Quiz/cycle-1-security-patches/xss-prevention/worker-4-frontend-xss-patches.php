<?php
/**
 * Worker 4: XSS Prevention Patches - Frontend Output Sanitization
 * CVSS: 8.8 (High)
 * Focus: Public-facing pages and quiz interface
 */

// PATCH SET 1: Email template output sanitization
// File: quiz.moneycoach.php - Email generation section

// Helper function for safe HTML output
function mq_safe_html_output($content, $allowed_tags = null) {
    if ($allowed_tags === null) {
        $allowed_tags = array(
            'a' => array('href' => array(), 'title' => array()),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array(),
            'span' => array('class' => array())
        );
    }
    return wp_kses($content, $allowed_tags);
}

// PATCH 1: Sanitize prospect data in emails
// OLD: <td>'.$Name.' '.$Surname.'</td>
// NEW: Safe output
$email_content = str_replace(
    array(
        '<td>'.$Name.' '.$Surname.'</td>',
        '<td>'.$Email.'</td>',
        '<td>'.$Telephone.'</td>'
    ),
    array(
        '<td>' . esc_html($Name) . ' ' . esc_html($Surname) . '</td>',
        '<td>' . esc_html($Email) . '</td>',
        '<td>' . esc_html($Telephone) . '</td>'
    ),
    $email_content
);

// PATCH 2: Sanitize archetype data output
// OLD: $archetypes_data[$row->ID] = stripslashes($row->Value);
// NEW: Properly escape for different contexts
foreach($rows as $row) {
    $temp_archive_id = $row->ID; 
    if(in_array($temp_archive_id, $archive_type_include)) {
        // For HTML content, use wp_kses
        $archetypes_data[$row->ID] = mq_safe_html_output(stripslashes($row->Value));
    } else {
        // For plain text, escape HTML
        $archetypes_data[$row->ID] = esc_html(stripslashes($row->Value));
    }
}

// PATCH 3: Quiz results table output
// OLD: $str = '<tr><td>'.$row->ID_Unique.'</td><td>'.$row->Question.'</td>...
// NEW: Properly escaped output
$str = '<tr>';
$str .= '<td>' . esc_html($row->ID_Unique) . '</td>';
$str .= '<td>' . esc_html($row->Question) . '</td>';
$str .= '<td>' . esc_html($archetypes_data[$row->Archetype]) . '</td>';
$str .= '<td>' . esc_html($row->Score) . '</td>';
$str .= '</tr>';

// PATCH 4: JavaScript variable sanitization
// OLD: var taken_id = <?php echo $_GET['tid']?>;
// NEW: Properly escape for JavaScript context
?>
<script type="text/javascript">
var moneyQuizData = {
    takenId: <?php echo json_encode(isset($_GET['tid']) ? absint($_GET['tid']) : 0); ?>,
    prospectId: <?php echo json_encode($prospect_id); ?>,
    ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
    nonce: <?php echo json_encode(wp_create_nonce('money_quiz_nonce')); ?>
};
</script>
<?php

// PATCH 5: Form field output sanitization
// Helper function for form fields
function mq_form_field($name, $value, $type = 'text', $attributes = array()) {
    $field_html = '<input type="' . esc_attr($type) . '" ';
    $field_html .= 'name="' . esc_attr($name) . '" ';
    $field_html .= 'value="' . esc_attr($value) . '" ';
    
    foreach ($attributes as $attr => $attr_value) {
        $field_html .= esc_attr($attr) . '="' . esc_attr($attr_value) . '" ';
    }
    
    $field_html .= '/>';
    return $field_html;
}

// Usage example:
// echo mq_form_field('prospect_data[Name]', $Name, 'text', array('class' => 'form-control', 'required' => 'required'));

// PATCH 6: URL parameter sanitization
// OLD: <a href="?step=results&tid=<?php echo $_GET['tid']?>">
// NEW: Properly escape URL parameters
function mq_build_url($base_url, $params = array()) {
    $url = $base_url;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params, '', '&amp;');
    }
    return esc_url($url);
}

// Usage:
// echo mq_build_url(get_permalink(), array('step' => 'results', 'tid' => $taken_id));

// PATCH 7: Dynamic content loading protection
class MoneyQuizContentSanitizer {
    
    /**
     * Sanitize content based on context
     */
    public static function sanitize($content, $context = 'html') {
        switch ($context) {
            case 'html':
                return wp_kses_post($content);
            
            case 'attribute':
                return esc_attr($content);
            
            case 'url':
                return esc_url($content);
            
            case 'js':
                return esc_js($content);
            
            case 'textarea':
                return esc_textarea($content);
            
            default:
                return esc_html($content);
        }
    }
    
    /**
     * Sanitize array of data
     */
    public static function sanitize_array($data, $rules) {
        $sanitized = array();
        
        foreach ($rules as $key => $context) {
            if (isset($data[$key])) {
                $sanitized[$key] = self::sanitize($data[$key], $context);
            }
        }
        
        return $sanitized;
    }
}

// PATCH 8: Quiz display template with proper escaping
function mq_display_quiz_question($question_data) {
    ?>
    <div class="mq-question">
        <h3><?php echo esc_html($question_data['question_text']); ?></h3>
        <div class="mq-options">
            <?php foreach ($question_data['options'] as $option): ?>
                <label>
                    <input type="radio" 
                           name="question_<?php echo esc_attr($question_data['id']); ?>" 
                           value="<?php echo esc_attr($option['value']); ?>">
                    <?php echo esc_html($option['text']); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// PATCH 9: Error message display
function mq_display_error($message, $type = 'error') {
    $allowed_types = array('error', 'warning', 'success', 'info');
    $type = in_array($type, $allowed_types) ? $type : 'error';
    ?>
    <div class="mq-notice mq-<?php echo esc_attr($type); ?>">
        <?php echo esc_html($message); ?>
    </div>
    <?php
}

// PATCH 10: Results display with proper escaping
function mq_display_results($results_data) {
    ?>
    <div class="mq-results">
        <h2><?php esc_html_e('Your Results', 'money-quiz'); ?></h2>
        <table class="mq-results-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Archetype', 'money-quiz'); ?></th>
                    <th><?php esc_html_e('Score', 'money-quiz'); ?></th>
                    <th><?php esc_html_e('Percentage', 'money-quiz'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results_data as $result): ?>
                <tr>
                    <td><?php echo esc_html($result['archetype']); ?></td>
                    <td><?php echo esc_html($result['score']); ?></td>
                    <td><?php echo esc_html($result['percentage']); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}