<?php
/**
 * Worker 6: Frontend Input Validation
 * Focus: Quiz forms, user data, frontend submissions
 */

// Comprehensive frontend validation framework
class MoneyQuizFrontendValidator {
    
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
     * Validate quiz submission data
     */
    public function validateQuizSubmission($data) {
        $this->validation_errors = array();
        
        // Validate prospect data
        if (isset($data['prospect_data'])) {
            $this->validateProspectData($data['prospect_data']);
        }
        
        // Validate quiz answers
        if (isset($data['question_data'])) {
            $this->validateQuestionData($data['question_data']);
        }
        
        // Validate quiz metadata
        if (isset($data['quiz_meta'])) {
            $this->validateQuizMeta($data['quiz_meta']);
        }
        
        return empty($this->validation_errors);
    }
    
    /**
     * Validate prospect information
     */
    private function validateProspectData($data) {
        // Name validation
        if (isset($data['Name'])) {
            $name = trim($data['Name']);
            if (empty($name)) {
                $this->addError('name', __('Name is required', 'money-quiz'));
            } elseif (strlen($name) < 2) {
                $this->addError('name', __('Name must be at least 2 characters', 'money-quiz'));
            } elseif (strlen($name) > 100) {
                $this->addError('name', __('Name must not exceed 100 characters', 'money-quiz'));
            } elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $name)) {
                $this->addError('name', __('Name contains invalid characters', 'money-quiz'));
            }
        }
        
        // Surname validation
        if (isset($data['Surname'])) {
            $surname = trim($data['Surname']);
            if (empty($surname)) {
                $this->addError('surname', __('Surname is required', 'money-quiz'));
            } elseif (strlen($surname) < 2) {
                $this->addError('surname', __('Surname must be at least 2 characters', 'money-quiz'));
            } elseif (strlen($surname) > 100) {
                $this->addError('surname', __('Surname must not exceed 100 characters', 'money-quiz'));
            } elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $surname)) {
                $this->addError('surname', __('Surname contains invalid characters', 'money-quiz'));
            }
        }
        
        // Email validation
        if (isset($data['Email'])) {
            $email = trim($data['Email']);
            if (empty($email)) {
                $this->addError('email', __('Email is required', 'money-quiz'));
            } elseif (!is_email($email)) {
                $this->addError('email', __('Please enter a valid email address', 'money-quiz'));
            } elseif (strlen($email) > 254) {
                $this->addError('email', __('Email address is too long', 'money-quiz'));
            }
            
            // Check for disposable email domains
            if ($this->isDisposableEmail($email)) {
                $this->addError('email', __('Please use a permanent email address', 'money-quiz'));
            }
        }
        
        // Telephone validation
        if (isset($data['Telephone'])) {
            $telephone = trim($data['Telephone']);
            if (!empty($telephone)) {
                // Remove common formatting characters
                $cleaned_phone = preg_replace('/[\s\-\(\)\.]+/', '', $telephone);
                
                if (!preg_match('/^\+?\d{7,15}$/', $cleaned_phone)) {
                    $this->addError('telephone', __('Please enter a valid phone number', 'money-quiz'));
                }
            }
        }
        
        // Newsletter consent validation
        if (isset($data['Newsletter'])) {
            $newsletter = $data['Newsletter'];
            if (!in_array($newsletter, array('0', '1', 0, 1, true, false), true)) {
                $this->addError('newsletter', __('Invalid newsletter preference', 'money-quiz'));
            }
        }
        
        // Consultation consent validation
        if (isset($data['Consultation'])) {
            $consultation = $data['Consultation'];
            if (!in_array($consultation, array('0', '1', 0, 1, true, false), true)) {
                $this->addError('consultation', __('Invalid consultation preference', 'money-quiz'));
            }
        }
    }
    
    /**
     * Validate quiz question data
     */
    private function validateQuestionData($data) {
        if (!is_array($data)) {
            $this->addError('questions', __('Invalid question data format', 'money-quiz'));
            return;
        }
        
        $question_count = count($data);
        
        // Check minimum questions answered
        if ($question_count < 5) {
            $this->addError('questions', __('Please answer at least 5 questions', 'money-quiz'));
        }
        
        // Validate each answer
        foreach ($data as $question_id => $answer) {
            // Validate question ID
            if (!is_numeric($question_id) || $question_id <= 0) {
                $this->addError('question_' . $question_id, __('Invalid question ID', 'money-quiz'));
                continue;
            }
            
            // Validate answer value (assuming 1-10 scale)
            if (!is_numeric($answer) || $answer < 1 || $answer > 10) {
                $this->addError(
                    'question_' . $question_id, 
                    sprintf(__('Answer for question %d must be between 1 and 10', 'money-quiz'), $question_id)
                );
            }
        }
    }
    
    /**
     * Validate quiz metadata
     */
    private function validateQuizMeta($data) {
        // Validate quiz ID if resuming
        if (isset($data['quiz_id'])) {
            $quiz_id = $data['quiz_id'];
            if (!is_numeric($quiz_id) || $quiz_id <= 0) {
                $this->addError('quiz_id', __('Invalid quiz ID', 'money-quiz'));
            }
        }
        
        // Validate step number
        if (isset($data['step'])) {
            $step = $data['step'];
            if (!is_numeric($step) || $step < 1 || $step > 10) {
                $this->addError('step', __('Invalid quiz step', 'money-quiz'));
            }
        }
        
        // Validate completion time
        if (isset($data['time_taken'])) {
            $time = $data['time_taken'];
            if (!is_numeric($time) || $time < 0 || $time > 86400) { // Max 24 hours
                $this->addError('time', __('Invalid completion time', 'money-quiz'));
            }
        }
    }
    
    /**
     * Check if email is from disposable domain
     */
    private function isDisposableEmail($email) {
        $disposable_domains = array(
            'tempmail.com', 'throwaway.email', 'guerrillamail.com',
            'mailinator.com', '10minutemail.com', 'trashmail.com'
            // Add more as needed
        );
        
        $domain = substr(strrchr($email, "@"), 1);
        return in_array($domain, $disposable_domains);
    }
    
    /**
     * Add validation error
     */
    private function addError($field, $message) {
        $this->validation_errors[$field] = $message;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->validation_errors;
    }
    
    /**
     * Get error messages as string
     */
    public function getErrorString() {
        return implode('<br>', $this->validation_errors);
    }
}

// Specific validation functions for quiz flow

/**
 * Validate quiz start
 */
function mq_validate_quiz_start($data) {
    $validator = MoneyQuizFrontendValidator::getInstance();
    
    // Check if user can start quiz
    if (isset($data['email'])) {
        // Check if email already completed quiz recently
        global $wpdb, $table_prefix;
        
        $email = sanitize_email($data['email']);
        $recent_quiz = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_prefix}" . TABLE_MQ_PROSPECTS . 
            " WHERE Email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $email
        ));
        
        if ($recent_quiz > 0) {
            $validator->addError('rate_limit', __('Please wait 24 hours before taking the quiz again', 'money-quiz'));
            return false;
        }
    }
    
    return true;
}

/**
 * Validate quiz step progression
 */
function mq_validate_quiz_step($current_step, $next_step, $answers = array()) {
    // Ensure sequential progression
    if ($next_step !== $current_step + 1) {
        return new WP_Error('invalid_progression', __('Invalid quiz progression', 'money-quiz'));
    }
    
    // Ensure previous steps were completed
    if (empty($answers) && $current_step > 1) {
        return new WP_Error('incomplete_steps', __('Previous steps must be completed', 'money-quiz'));
    }
    
    return true;
}

/**
 * Sanitize and validate quiz form data
 */
function mq_sanitize_quiz_data($raw_data) {
    $sanitized = array();
    
    // Sanitize prospect data
    if (isset($raw_data['prospect_data'])) {
        $sanitized['prospect_data'] = array(
            'Name' => sanitize_text_field($raw_data['prospect_data']['Name'] ?? ''),
            'Surname' => sanitize_text_field($raw_data['prospect_data']['Surname'] ?? ''),
            'Email' => sanitize_email($raw_data['prospect_data']['Email'] ?? ''),
            'Telephone' => sanitize_text_field($raw_data['prospect_data']['Telephone'] ?? ''),
            'Newsletter' => absint($raw_data['prospect_data']['Newsletter'] ?? 0),
            'Consultation' => absint($raw_data['prospect_data']['Consultation'] ?? 0)
        );
    }
    
    // Sanitize question answers
    if (isset($raw_data['question_data']) && is_array($raw_data['question_data'])) {
        $sanitized['question_data'] = array();
        foreach ($raw_data['question_data'] as $q_id => $answer) {
            $sanitized['question_data'][absint($q_id)] = absint($answer);
        }
    }
    
    // Sanitize metadata
    if (isset($raw_data['quiz_meta'])) {
        $sanitized['quiz_meta'] = array(
            'quiz_id' => absint($raw_data['quiz_meta']['quiz_id'] ?? 0),
            'step' => absint($raw_data['quiz_meta']['step'] ?? 1),
            'time_taken' => absint($raw_data['quiz_meta']['time_taken'] ?? 0)
        );
    }
    
    return $sanitized;
}

/**
 * JavaScript validation helper
 */
function mq_get_validation_rules_json() {
    $rules = array(
        'name' => array(
            'required' => true,
            'minLength' => 2,
            'maxLength' => 100,
            'pattern' => '^[\\p{L}\\s\'-]+$'
        ),
        'surname' => array(
            'required' => true,
            'minLength' => 2,
            'maxLength' => 100,
            'pattern' => '^[\\p{L}\\s\'-]+$'
        ),
        'email' => array(
            'required' => true,
            'type' => 'email',
            'maxLength' => 254
        ),
        'telephone' => array(
            'required' => false,
            'pattern' => '^\\+?\\d{7,15}$'
        ),
        'answer' => array(
            'required' => true,
            'type' => 'number',
            'min' => 1,
            'max' => 10
        )
    );
    
    return json_encode($rules);
}

// Add client-side validation
add_action('wp_footer', function() {
    if (!is_page_template('quiz-template.php')) {
        return;
    }
    ?>
    <script type="text/javascript">
    var mqValidationRules = <?php echo mq_get_validation_rules_json(); ?>;
    
    // Real-time validation
    jQuery(document).ready(function($) {
        // Email validation
        $('#mq-email').on('blur', function() {
            var email = $(this).val();
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                $(this).addClass('error');
                $(this).after('<span class="error-message"><?php esc_html_e('Please enter a valid email', 'money-quiz'); ?></span>');
            } else {
                $(this).removeClass('error');
                $(this).next('.error-message').remove();
            }
        });
        
        // Phone validation
        $('#mq-phone').on('blur', function() {
            var phone = $(this).val().replace(/[\s\-\(\)\.]+/g, '');
            var phoneRegex = /^\+?\d{7,15}$/;
            
            if (phone && !phoneRegex.test(phone)) {
                $(this).addClass('error');
                $(this).after('<span class="error-message"><?php esc_html_e('Please enter a valid phone number', 'money-quiz'); ?></span>');
            } else {
                $(this).removeClass('error');
                $(this).next('.error-message').remove();
            }
        });
        
        // Answer validation
        $('.mq-answer-input').on('change', function() {
            var value = parseInt($(this).val());
            
            if (isNaN(value) || value < 1 || value > 10) {
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
    });
    </script>
    <?php
});