<?php
/**
 * Security REST API
 * 
 * @package MoneyQuiz\Security
 * @version 1.0.0
 */

namespace MoneyQuiz\Security;

/**
 * Security API Controller
 */
class SecurityApi {
    
    private static $instance = null;
    private $namespace = 'money-quiz/v1';
    
    private function __construct() {}
    
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
     * Initialize API endpoints
     */
    public static function init() {
        $instance = self::getInstance();
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
    }
    
    /**
     * Register REST endpoints
     */
    public function registerEndpoints() {
        // Security status endpoint
        register_rest_route($this->namespace, '/security/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatus'],
            'permission_callback' => [$this, 'checkPermission']
        ]);
        
        // Security scan endpoint
        register_rest_route($this->namespace, '/security/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'runScan'],
            'permission_callback' => [$this, 'checkPermission']
        ]);
        
        // Security events endpoint
        register_rest_route($this->namespace, '/security/events', [
            'methods' => 'GET',
            'callback' => [$this, 'getEvents'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'limit' => [
                    'default' => 50,
                    'sanitize_callback' => 'absint'
                ],
                'severity' => [
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Settings endpoint
        register_rest_route($this->namespace, '/security/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkPermission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => $this->getSettingsSchema()
            ]
        ]);
        
        // Test endpoint
        register_rest_route($this->namespace, '/security/test', [
            'methods' => 'POST',
            'callback' => [$this, 'runTest'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'test' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }
    
    /**
     * Check API permission
     */
    public function checkPermission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get security status
     */
    public function getStatus($request) {
        $manager = SecurityManager::getInstance();
        $status = $manager->getSecurityStatus();
        
        // Add additional status info
        $status['php_version'] = PHP_VERSION;
        $status['ssl_enabled'] = is_ssl();
        $status['debug_mode'] = defined('WP_DEBUG') && WP_DEBUG;
        
        // Get module statuses
        $status['modules_status'] = [];
        foreach ($status['modules'] as $module) {
            $status['modules_status'][$module] = [
                'enabled' => SecurityConfig::get($module . '_enabled', true),
                'configured' => true
            ];
        }
        
        return rest_ensure_response($status);
    }
    
    /**
     * Run security scan
     */
    public function runScan($request) {
        if (!function_exists('money_quiz_security_scan')) {
            return new \WP_Error('scan_unavailable', 'Security scanner not available', ['status' => 503]);
        }
        
        // Run scan
        $results = money_quiz_security_scan();
        
        // Log scan
        if (class_exists('MoneyQuiz\Security\Audit\AuditLogger')) {
            \MoneyQuiz\Security\Audit\AuditLogger::getInstance()->logSecurityEvent(
                'api.security_scan',
                ['user_id' => get_current_user_id()]
            );
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Get security events
     */
    public function getEvents($request) {
        if (!class_exists('MoneyQuiz\Security\Audit\AuditLogger')) {
            return new \WP_Error('audit_unavailable', 'Audit logger not available', ['status' => 503]);
        }
        
        $logger = \MoneyQuiz\Security\Audit\AuditLogger::getInstance();
        $limit = $request->get_param('limit');
        $severity = $request->get_param('severity');
        
        // Get events
        $events = $logger->getRecentLogs($limit);
        
        // Filter by severity if requested
        if ($severity !== 'all') {
            $events = array_filter($events, function($event) use ($severity) {
                return $event['severity'] === $severity;
            });
        }
        
        return rest_ensure_response([
            'events' => array_values($events),
            'total' => count($events)
        ]);
    }
    
    /**
     * Get security settings
     */
    public function getSettings($request) {
        $settings = SecurityConfig::getAll();
        
        return rest_ensure_response([
            'settings' => $settings,
            'defaults' => SecurityConfig::$defaults ?? []
        ]);
    }
    
    /**
     * Update security settings
     */
    public function updateSettings($request) {
        $settings = $request->get_json_params();
        
        // Validate and save each setting
        foreach ($settings as $key => $value) {
            SecurityConfig::set($key, $value);
        }
        
        // Log settings update
        if (class_exists('MoneyQuiz\Security\Audit\AuditLogger')) {
            \MoneyQuiz\Security\Audit\AuditLogger::getInstance()->logSecurityEvent(
                'api.settings_updated',
                [
                    'user_id' => get_current_user_id(),
                    'settings' => array_keys($settings)
                ]
            );
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }
    
    /**
     * Run security test
     */
    public function runTest($request) {
        $test = $request->get_param('test');
        
        $results = [];
        
        switch ($test) {
            case 'csrf':
                $results = $this->testCsrf();
                break;
                
            case 'xss':
                $results = $this->testXss();
                break;
                
            case 'sql':
                $results = $this->testSql();
                break;
                
            case 'rate_limit':
                $results = $this->testRateLimit();
                break;
                
            default:
                return new \WP_Error('invalid_test', 'Invalid test specified', ['status' => 400]);
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Test CSRF protection
     */
    private function testCsrf() {
        if (!class_exists('MoneyQuiz\Security\CSRF\CsrfProtection')) {
            return ['error' => 'CSRF protection not available'];
        }
        
        $csrf = \MoneyQuiz\Security\CSRF\CsrfProtection::getInstance();
        $token = $csrf->generateToken('api_test');
        
        return [
            'test' => 'csrf',
            'token_generated' => !empty($token),
            'token_length' => strlen($token),
            'validation_available' => method_exists($csrf, 'verifyToken')
        ];
    }
    
    /**
     * Test XSS protection
     */
    private function testXss() {
        if (!class_exists('MoneyQuiz\Security\XSS\XssProtection')) {
            return ['error' => 'XSS protection not available'];
        }
        
        $xss = \MoneyQuiz\Security\XSS\XssProtection::getInstance();
        $test_input = '<script>alert("XSS")</script>';
        $filtered = $xss->filterInput($test_input);
        
        return [
            'test' => 'xss',
            'input' => $test_input,
            'filtered' => $filtered,
            'protection_active' => $test_input !== $filtered
        ];
    }
    
    /**
     * Test SQL protection
     */
    private function testSql() {
        if (!class_exists('MoneyQuiz\Security\SQL\SqlValidator')) {
            return ['error' => 'SQL protection not available'];
        }
        
        $test_inputs = [
            "' OR '1'='1" => false,
            "normal input" => true
        ];
        
        $results = [];
        foreach ($test_inputs as $input => $expected) {
            $valid = \MoneyQuiz\Security\SQL\SqlValidator::validate($input);
            $results[] = [
                'input' => $input,
                'valid' => $valid,
                'expected' => $expected,
                'passed' => $valid === $expected
            ];
        }
        
        return [
            'test' => 'sql',
            'results' => $results,
            'protection_active' => true
        ];
    }
    
    /**
     * Test rate limiting
     */
    private function testRateLimit() {
        if (!class_exists('MoneyQuiz\Security\RateLimit\RateLimiter')) {
            return ['error' => 'Rate limiting not available'];
        }
        
        $limiter = \MoneyQuiz\Security\RateLimit\RateLimiter::getInstance();
        $result = $limiter->checkLimit('api_test');
        
        return [
            'test' => 'rate_limit',
            'allowed' => $result['allowed'],
            'remaining' => $result['remaining'] ?? null,
            'reset_time' => $result['reset_time'] ?? null
        ];
    }
    
    /**
     * Get settings schema
     */
    private function getSettingsSchema() {
        return [
            'force_ssl' => [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean'
            ],
            'csrf_enabled' => [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean'
            ],
            'xss_filtering' => [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean'
            ],
            'rate_limiting_enabled' => [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean'
            ],
            'audit_logging' => [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean'
            ]
        ];
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [SecurityApi::class, 'init']);