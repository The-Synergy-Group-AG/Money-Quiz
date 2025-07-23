<?php
/**
 * Input Validation Middleware
 *
 * Validates all input data before processing.
 *
 * @package MoneyQuiz\Security\Middleware
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Middleware;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\InputValidator;
use WP_REST_Request;
use WP_REST_Response;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Input validation middleware class.
 *
 * @since 7.0.0
 */
class InputValidationMiddleware extends SecurityMiddleware {
    
    /**
     * Input validator.
     *
     * @var InputValidator
     */
    private InputValidator $validator;
    
    /**
     * Middleware priority.
     *
     * @var int
     */
    protected int $priority = 5;
    
    /**
     * Validation rules by route.
     *
     * @var array
     */
    private array $route_rules = [
        'POST:/money-quiz/v1/quiz' => [
            'title' => 'required|min:3|max:200',
            'description' => 'required|min:10|max:1000',
            'questions' => 'required|array|min:1',
            'settings' => 'array'
        ],
        'POST:/money-quiz/v1/quiz/submit' => [
            'quiz_id' => 'required|integer',
            'answers' => 'required|array',
            'email' => 'required|email',
            'name' => 'required|min:2|max:100'
        ],
        'PUT:/money-quiz/v1/settings/*' => [
            'value' => 'required',
            'type' => 'in:string,boolean,integer,array'
        ]
    ];
    
    /**
     * Constructor.
     *
     * @param Logger         $logger    Logger instance.
     * @param InputValidator $validator Input validator.
     */
    public function __construct(Logger $logger, InputValidator $validator) {
        parent::__construct($logger);
        $this->validator = $validator;
    }
    
    /**
     * Process input validation.
     *
     * @param WP_REST_Request $request The request.
     * @param callable        $next    Next middleware.
     * @return WP_REST_Response Response.
     */
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response {
        // Skip GET requests by default
        if ($request->get_method() === 'GET' && !$this->has_validation_rules($request)) {
            return $next($request);
        }
        
        // Get validation rules
        $rules = $this->get_validation_rules($request);
        if (empty($rules)) {
            return $next($request);
        }
        
        // Get input data
        $data = $this->get_input_data($request);
        
        // Validate input
        if (!$this->validator->validate($data, $rules)) {
            $errors = $this->validator->get_errors();
            
            $this->log_security_event('input_validation_failed', [
                'errors' => $errors,
                'route' => $request->get_route(),
                'method' => $request->get_method()
            ], 'warning');
            
            return $this->error_response(
                __('Validation failed.', 'money-quiz'),
                400,
                ['errors' => $errors]
            );
        }
        
        // Sanitize and store validated data
        $sanitized = $this->sanitize_data($data, $rules);
        $request->set_param('_validated_data', $sanitized);
        
        return $next($request);
    }
    
    /**
     * Get validation rules for request.
     *
     * @param WP_REST_Request $request The request.
     * @return array Validation rules.
     */
    private function get_validation_rules(WP_REST_Request $request): array {
        $method = $request->get_method();
        $route = $request->get_route();
        $key = "{$method}:{$route}";
        
        // Check exact match
        if (isset($this->route_rules[$key])) {
            return $this->route_rules[$key];
        }
        
        // Check pattern match
        foreach ($this->route_rules as $pattern => $rules) {
            if ($this->match_route_pattern($method, $route, $pattern)) {
                return $rules;
            }
        }
        
        // Check route options
        $route_options = $request->get_route_options();
        return $route_options['money_quiz_validation_rules'] ?? [];
    }
    
    /**
     * Check if request has validation rules.
     *
     * @param WP_REST_Request $request The request.
     * @return bool True if has rules.
     */
    private function has_validation_rules(WP_REST_Request $request): bool {
        return !empty($this->get_validation_rules($request));
    }
    
    /**
     * Get input data from request.
     *
     * Collects data from all possible sources in a REST request:
     * 1. Body parameters (form data)
     * 2. JSON payload
     * 3. Query parameters (for GET requests)
     * 4. URL path parameters
     * 
     * The order matters as later sources override earlier ones.
     *
     * @param WP_REST_Request $request The request.
     * @return array Combined input data from all sources.
     */
    private function get_input_data(WP_REST_Request $request): array {
        $combined_input_data = [];
        
        // Get POST/PUT body parameters (form-encoded data)
        $body_parameters = $request->get_body_params();
        $combined_input_data = array_merge($combined_input_data, $body_parameters);
        
        // Get JSON payload (Content-Type: application/json)
        // JSON params override body params if both exist
        $json_parameters = $request->get_json_params() ?? [];
        $combined_input_data = array_merge($combined_input_data, $json_parameters);
        
        // Include query string parameters for GET requests
        // Example: /api/endpoint?filter=active&limit=10
        if ($request->get_method() === 'GET') {
            $query_parameters = $request->get_query_params();
            $combined_input_data = array_merge($combined_input_data, $query_parameters);
        }
        
        // URL path parameters have highest priority
        // Example: /api/quiz/{id} where {id} is a URL parameter
        $url_parameters = $request->get_url_params();
        $combined_input_data = array_merge($combined_input_data, $url_parameters);
        
        return $combined_input_data;
    }
    
    /**
     * Sanitize validated data.
     *
     * Applies appropriate sanitization based on validation rules.
     * This ensures data is safe for storage and display even after
     * passing validation.
     *
     * @param array $data  Validated data.
     * @param array $rules Validation rules keyed by field name.
     * @return array Sanitized data safe for storage.
     */
    private function sanitize_data(array $data, array $rules): array {
        $sanitized_data = [];
        
        foreach ($data as $field_name => $field_value) {
            // Get validation rules for this field (empty string if no rules)
            $field_validation_rules = $rules[$field_name] ?? '';
            
            // Determine appropriate sanitization based on field name and rules
            $sanitization_type = $this->determine_sanitization_type($field_name, $field_validation_rules);
            
            // Apply sanitization and store result
            $sanitized_data[$field_name] = $this->validator->sanitize($field_value, $sanitization_type);
        }
        
        return $sanitized_data;
    }
    
    /**
     * Determine sanitization type from rules.
     *
     * Maps validation rules to appropriate sanitization methods.
     * Priority order matters - more specific types are checked first.
     *
     * @param string $field_name       The field name (used for context).
     * @param string $validation_rules Pipe-separated validation rules.
     * @return string Sanitization type identifier.
     */
    private function determine_sanitization_type(string $field_name, string $validation_rules): string {
        // Check for email fields (highest priority for security)
        if (strpos($validation_rules, 'email') !== false) {
            return 'email';
        }
        
        // URL fields need special encoding
        if (strpos($validation_rules, 'url') !== false) {
            return 'url';
        }
        
        // Numeric types
        if (strpos($validation_rules, 'integer') !== false) {
            return 'integer';
        }
        if (strpos($validation_rules, 'boolean') !== false) {
            return 'boolean';
        }
        
        // Long text fields (allow some HTML)
        $is_long_text_field = strpos($field_name, 'description') !== false;
        $has_large_max_length = strpos($validation_rules, 'max:1000') !== false;
        if ($is_long_text_field || $has_large_max_length) {
            return 'textarea';
        }
        
        // Default to strict text sanitization
        return 'text';
    }
    
    /**
     * Match route pattern.
     *
     * @param string $request_method HTTP method from request.
     * @param string $request_route  Route path from request.
     * @param string $rule_pattern   Pattern to match (FORMAT: "METHOD:path/with/*").
     * @return bool True if the request matches the pattern.
     */
    private function match_route_pattern(string $request_method, string $request_route, string $rule_pattern): bool {
        // Pattern must contain method:path separator
        if (strpos($rule_pattern, ':') === false) {
            return false;
        }
        
        // Split pattern into method and path components
        [$pattern_method, $pattern_path] = explode(':', $rule_pattern, 2);
        
        // Method must match exactly (case-sensitive)
        if ($pattern_method !== $request_method) {
            return false;
        }
        
        // Convert wildcard pattern to regex
        // * matches any single path segment (not including /)
        $regex_pattern = str_replace(
            ['*', '/'], 
            ['[^/]+', '\\/'],  // * becomes [^/]+, / is escaped
            $pattern_path
        );
        
        // Match entire route from start to end
        return preg_match('/^' . $regex_pattern . '$/', $request_route) === 1;
    }
    
    /**
     * Register validation rules for route.
     *
     * @param string $pattern Route pattern.
     * @param array  $rules   Validation rules.
     */
    public function register_route_rules(string $pattern, array $rules): void {
        $this->route_rules[$pattern] = $rules;
    }
}