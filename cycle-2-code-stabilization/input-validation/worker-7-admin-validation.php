<?php
/**
 * Worker 7: Admin Input Validation
 * Focus: Settings, questions, imports, admin forms
 */

// Comprehensive admin validation framework
class MoneyQuizAdminValidator {
    
    private static $instance = null;
    private $validation_errors = array();
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate question data
     */
    public function validateQuestion($data) {
        $this->validation_errors = array();
        
        // Question text validation
        if (empty($data['question'])) {
            $this->addError('question', __('Question text is required', 'money-quiz'));
        } elseif (strlen($data['question']) < 10) {
            $this->addError('question', __('Question must be at least 10 characters', 'money-quiz'));
        } elseif (strlen($data['question']) > 500) {
            $this->addError('question', __('Question must not exceed 500 characters', 'money-quiz'));
        }
        
        // Money type validation
        if (!isset($data['money_type']) || !in_array($data['money_type'], range(1, 8))) {
            $this->addError('money_type', __('Please select a valid money type', 'money-quiz'));
        }
        
        // Archetype validation
        $valid_archetypes = array(1, 5, 9, 13, 17, 21, 25, 29);
        if (!isset($data['archetype']) || !in_array($data['archetype'], $valid_archetypes)) {
            $this->addError('archetype', __('Please select a valid archetype', 'money-quiz'));
        }
        
        // Question ID validation (for updates)
        if (isset($data['question_id']) && !empty($data['question_id'])) {
            if (!is_numeric($data['question_id']) || $data['question_id'] <= 0) {
                $this->addError('question_id', __('Invalid question ID', 'money-quiz'));
            }
        }
        
        // Custom validation hook
        do_action('mq_validate_question', $data, $this);
        
        return empty($this->validation_errors);
    }
    
    /**
     * Validate settings data
     */
    public function validateSettings($data) {
        $this->validation_errors = array();
        
        // Email settings
        if (isset($data['admin_email'])) {
            if (!is_email($data['admin_email'])) {
                $this->addError('admin_email', __('Please enter a valid admin email', 'money-quiz'));
            }
        }
        
        if (isset($data['from_email'])) {
            if (!is_email($data['from_email'])) {
                $this->addError('from_email', __('Please enter a valid from email', 'money-quiz'));
            }
        }
        
        // Email subject validation
        if (isset($data['email_subject'])) {
            if (empty($data['email_subject'])) {
                $this->addError('email_subject', __('Email subject is required', 'money-quiz'));
            } elseif (strlen($data['email_subject']) > 200) {
                $this->addError('email_subject', __('Email subject is too long', 'money-quiz'));
            }
        }
        
        // Results page validation
        if (isset($data['results_page_id'])) {
            $page_id = absint($data['results_page_id']);
            if ($page_id > 0 && get_post_status($page_id) !== 'publish') {
                $this->addError('results_page_id', __('Selected page is not published', 'money-quiz'));
            }
        }
        
        // Quiz settings
        if (isset($data['questions_per_page'])) {
            $qpp = absint($data['questions_per_page']);
            if ($qpp < 1 || $qpp > 50) {
                $this->addError('questions_per_page', __('Questions per page must be between 1 and 50', 'money-quiz'));
            }
        }
        
        if (isset($data['quiz_time_limit'])) {
            $time_limit = absint($data['quiz_time_limit']);
            if ($time_limit < 0 || $time_limit > 3600) {
                $this->addError('quiz_time_limit', __('Time limit must be between 0 and 3600 seconds', 'money-quiz'));
            }
        }
        
        // API settings
        if (isset($data['api_endpoint'])) {
            if (!empty($data['api_endpoint']) && !filter_var($data['api_endpoint'], FILTER_VALIDATE_URL)) {
                $this->addError('api_endpoint', __('Please enter a valid API endpoint URL', 'money-quiz'));
            }
        }
        
        return empty($this->validation_errors);
    }
    
    /**
     * Validate archetype data
     */
    public function validateArchetype($data) {
        $this->validation_errors = array();
        
        // Archetype name validation
        if (empty($data['name'])) {
            $this->addError('name', __('Archetype name is required', 'money-quiz'));
        } elseif (strlen($data['name']) > 100) {
            $this->addError('name', __('Archetype name is too long', 'money-quiz'));
        }
        
        // Description validation
        if (isset($data['description'])) {
            if (strlen($data['description']) > 5000) {
                $this->addError('description', __('Description is too long (max 5000 characters)', 'money-quiz'));
            }
        }
        
        // Image URL validation
        if (!empty($data['image_url'])) {
            if (!filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
                $this->addError('image_url', __('Please enter a valid image URL', 'money-quiz'));
            } elseif (!$this->isValidImageUrl($data['image_url'])) {
                $this->addError('image_url', __('URL must point to a valid image', 'money-quiz'));
            }
        }
        
        // Score range validation
        if (isset($data['min_score']) && isset($data['max_score'])) {
            $min = absint($data['min_score']);
            $max = absint($data['max_score']);
            
            if ($min >= $max) {
                $this->addError('score_range', __('Minimum score must be less than maximum score', 'money-quiz'));
            }
        }
        
        return empty($this->validation_errors);
    }
    
    /**
     * Validate import data
     */
    public function validateImportData($file_path, $type = 'csv') {
        $this->validation_errors = array();
        
        // File existence check
        if (!file_exists($file_path)) {
            $this->addError('file', __('Import file not found', 'money-quiz'));
            return false;
        }
        
        // File size check (max 10MB)
        $file_size = filesize($file_path);
        if ($file_size > 10 * 1024 * 1024) {
            $this->addError('file', __('File size exceeds 10MB limit', 'money-quiz'));
            return false;
        }
        
        // File type validation
        $allowed_types = array('csv', 'json', 'xml');
        if (!in_array($type, $allowed_types)) {
            $this->addError('file', __('Invalid file type', 'money-quiz'));
            return false;
        }
        
        // Validate file content based on type
        switch ($type) {
            case 'csv':
                return $this->validateCsvImport($file_path);
            case 'json':
                return $this->validateJsonImport($file_path);
            case 'xml':
                return $this->validateXmlImport($file_path);
        }
        
        return false;
    }
    
    /**
     * Validate CSV import
     */
    private function validateCsvImport($file_path) {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $this->addError('file', __('Unable to read CSV file', 'money-quiz'));
            return false;
        }
        
        // Check headers
        $headers = fgetcsv($handle);
        $required_headers = array('question', 'money_type', 'archetype');
        
        foreach ($required_headers as $required) {
            if (!in_array($required, $headers)) {
                $this->addError('headers', sprintf(__('Missing required column: %s', 'money-quiz'), $required));
            }
        }
        
        // Validate data rows
        $row_count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $row_count++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Validate row data
            $row_data = array_combine($headers, $data);
            if (!$this->validateQuestion($row_data)) {
                $this->addError('row_' . $row_count, sprintf(__('Invalid data in row %d', 'money-quiz'), $row_count));
            }
            
            // Limit check (max 1000 rows)
            if ($row_count > 1000) {
                $this->addError('file', __('File contains too many rows (max 1000)', 'money-quiz'));
                break;
            }
        }
        
        fclose($handle);
        
        if ($row_count === 0) {
            $this->addError('file', __('No data found in CSV file', 'money-quiz'));
        }
        
        return empty($this->validation_errors);
    }
    
    /**
     * Validate JSON import
     */
    private function validateJsonImport($file_path) {
        $json_content = file_get_contents($file_path);
        $data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('file', __('Invalid JSON format', 'money-quiz'));
            return false;
        }
        
        if (!is_array($data)) {
            $this->addError('file', __('JSON must contain an array of items', 'money-quiz'));
            return false;
        }
        
        foreach ($data as $index => $item) {
            if (!$this->validateQuestion($item)) {
                $this->addError('item_' . $index, sprintf(__('Invalid data in item %d', 'money-quiz'), $index + 1));
            }
        }
        
        return empty($this->validation_errors);
    }
    
    /**
     * Check if URL points to valid image
     */
    private function isValidImageUrl($url) {
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp');
        $parsed_url = parse_url($url);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return in_array($extension, $allowed_extensions);
    }
    
    /**
     * Add validation error
     */
    public function addError($field, $message) {
        $this->validation_errors[$field] = $message;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->validation_errors;
    }
}

// Admin form validation helpers

/**
 * Validate and sanitize admin form submission
 */
function mq_validate_admin_form($form_type, $data) {
    $validator = MoneyQuizAdminValidator::getInstance();
    
    switch ($form_type) {
        case 'question':
            return $validator->validateQuestion($data);
        case 'settings':
            return $validator->validateSettings($data);
        case 'archetype':
            return $validator->validateArchetype($data);
        default:
            return false;
    }
}

/**
 * Sanitize admin input data
 */
function mq_sanitize_admin_input($data, $context = 'general') {
    $sanitized = array();
    
    foreach ($data as $key => $value) {
        switch ($context) {
            case 'settings':
                $sanitized[$key] = mq_sanitize_setting($key, $value);
                break;
            case 'question':
                $sanitized[$key] = mq_sanitize_question_field($key, $value);
                break;
            default:
                $sanitized[$key] = is_array($value) 
                    ? array_map('sanitize_text_field', $value) 
                    : sanitize_text_field($value);
        }
    }
    
    return $sanitized;
}

/**
 * Sanitize individual setting value
 */
function mq_sanitize_setting($key, $value) {
    switch ($key) {
        case 'admin_email':
        case 'from_email':
            return sanitize_email($value);
        
        case 'email_subject':
        case 'email_template':
            return sanitize_text_field($value);
        
        case 'results_page_id':
        case 'questions_per_page':
        case 'quiz_time_limit':
            return absint($value);
        
        case 'api_endpoint':
            return esc_url_raw($value);
        
        case 'enable_feature':
        case 'show_results':
            return (bool) $value;
        
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Sanitize question field
 */
function mq_sanitize_question_field($key, $value) {
    switch ($key) {
        case 'question':
            return sanitize_textarea_field($value);
        
        case 'money_type':
        case 'archetype':
        case 'question_id':
            return absint($value);
        
        case 'active':
            return (bool) $value;
        
        default:
            return sanitize_text_field($value);
    }
}

// Admin AJAX validation
add_action('wp_ajax_mq_validate_field', function() {
    if (!current_user_can('manage_options')) {
        wp_die();
    }
    
    $field = sanitize_key($_POST['field']);
    $value = $_POST['value'];
    $context = sanitize_key($_POST['context']);
    
    $validator = MoneyQuizAdminValidator::getInstance();
    
    // Field-specific validation
    $is_valid = true;
    $message = '';
    
    switch ($field) {
        case 'email':
            $is_valid = is_email($value);
            $message = $is_valid ? '' : __('Invalid email address', 'money-quiz');
            break;
        
        case 'url':
            $is_valid = filter_var($value, FILTER_VALIDATE_URL);
            $message = $is_valid ? '' : __('Invalid URL', 'money-quiz');
            break;
        
        case 'number':
            $is_valid = is_numeric($value);
            $message = $is_valid ? '' : __('Must be a number', 'money-quiz');
            break;
    }
    
    wp_send_json(array(
        'valid' => $is_valid,
        'message' => $message
    ));
});

// Add validation to admin footer
add_action('admin_footer', function() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Real-time field validation
        $('.mq-validate-field').on('blur', function() {
            var field = $(this);
            var fieldType = field.data('validate');
            var value = field.val();
            
            $.post(ajaxurl, {
                action: 'mq_validate_field',
                field: fieldType,
                value: value,
                context: 'admin'
            }, function(response) {
                if (!response.valid) {
                    field.addClass('error');
                    field.after('<span class="error-message">' + response.message + '</span>');
                } else {
                    field.removeClass('error');
                    field.next('.error-message').remove();
                }
            });
        });
    });
    </script>
    <?php
});