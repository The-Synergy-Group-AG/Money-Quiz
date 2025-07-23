<?php
/**
 * Input Validator
 *
 * Comprehensive input validation for all user data.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Input validator class.
 *
 * @since 7.0.0
 */
class InputValidator {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Validation rules.
     *
     * @var array
     */
    private array $rules = [];
    
    /**
     * Validation errors.
     *
     * @var array
     */
    private array $errors = [];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Validate input data.
     *
     * @param array $data  Input data.
     * @param array $rules Validation rules.
     * @return bool True if valid.
     */
    public function validate(array $data, array $rules): bool {
        $this->errors = [];
        $this->rules = $rules;
        
        foreach ($rules as $field => $rule_set) {
            $value = $data[$field] ?? null;
            $this->validate_field($field, $value, $rule_set);
        }
        
        if (!empty($this->errors)) {
            $this->logger->warning('Input validation failed', [
                'errors' => $this->errors,
                'fields' => array_keys($this->errors)
            ]);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate single field.
     *
     * @param string $field Field name.
     * @param mixed  $value Field value.
     * @param string $rules Rule string.
     */
    private function validate_field(string $field, $value, string $rules): void {
        $rules_array = explode('|', $rules);
        
        foreach ($rules_array as $rule) {
            $this->apply_rule($field, $value, $rule);
        }
    }
    
    /**
     * Apply validation rule.
     *
     * @param string $field Field name.
     * @param mixed  $value Field value.
     * @param string $rule  Rule to apply.
     */
    private function apply_rule(string $field, $value, string $rule): void {
        $params = [];
        if (strpos($rule, ':') !== false) {
            [$rule, $param_str] = explode(':', $rule, 2);
            $params = explode(',', $param_str);
        }
        
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->add_error($field, 'Field is required');
                }
                break;
                
            case 'email':
                if ($value && !is_email($value)) {
                    $this->add_error($field, 'Invalid email format');
                }
                break;
                
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->add_error($field, 'Invalid URL format');
                }
                break;
                
            case 'integer':
                if ($value && !is_numeric($value)) {
                    $this->add_error($field, 'Must be a number');
                }
                break;
                
            case 'min':
                $min = (int) ($params[0] ?? 0);
                if ($value && (is_numeric($value) ? $value < $min : strlen($value) < $min)) {
                    $this->add_error($field, "Minimum value/length is $min");
                }
                break;
                
            case 'max':
                $max = (int) ($params[0] ?? PHP_INT_MAX);
                if ($value && (is_numeric($value) ? $value > $max : strlen($value) > $max)) {
                    $this->add_error($field, "Maximum value/length is $max");
                }
                break;
                
            case 'in':
                if ($value && !in_array($value, $params, true)) {
                    $this->add_error($field, 'Invalid selection');
                }
                break;
                
            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value && !preg_match($pattern, $value)) {
                    $this->add_error($field, 'Invalid format');
                }
                break;
                
            case 'alpha':
                if ($value && !ctype_alpha($value)) {
                    $this->add_error($field, 'Must contain only letters');
                }
                break;
                
            case 'alphanumeric':
                if ($value && !ctype_alnum($value)) {
                    $this->add_error($field, 'Must contain only letters and numbers');
                }
                break;
                
            case 'slug':
                if ($value && !preg_match('/^[a-z0-9-]+$/', $value)) {
                    $this->add_error($field, 'Must be a valid slug (lowercase letters, numbers, hyphens)');
                }
                break;
                
            case 'boolean':
                if ($value !== null && !in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                    $this->add_error($field, 'Must be true or false');
                }
                break;
                
            case 'date':
                if ($value && !strtotime($value)) {
                    $this->add_error($field, 'Invalid date format');
                }
                break;
                
            case 'json':
                if ($value && json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->add_error($field, 'Invalid JSON format');
                }
                break;
        }
    }
    
    /**
     * Add validation error.
     *
     * @param string $field   Field name.
     * @param string $message Error message.
     */
    private function add_error(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get validation errors.
     *
     * @return array Validation errors.
     */
    public function get_errors(): array {
        return $this->errors;
    }
    
    /**
     * Sanitize input based on type.
     *
     * @param mixed  $value Input value.
     * @param string $type  Data type.
     * @return mixed Sanitized value.
     */
    public function sanitize($value, string $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'text':
                return sanitize_text_field($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'integer':
                return (int) $value;
                
            case 'float':
                return (float) $value;
                
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'key':
                return sanitize_key($value);
                
            case 'title':
                return sanitize_title($value);
                
            case 'html':
                return wp_kses_post($value);
                
            case 'json':
                $decoded = json_decode($value, true);
                return json_encode($decoded);
                
            default:
                return sanitize_text_field($value);
        }
    }
}