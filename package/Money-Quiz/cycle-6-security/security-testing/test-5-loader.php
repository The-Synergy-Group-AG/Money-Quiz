<?php
/**
 * Security Testing Loader
 * 
 * @package MoneyQuiz\Security\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Testing;

// Load all test components
require_once __DIR__ . '/test-1-framework-setup.php';
require_once __DIR__ . '/test-2-unit-tests.php';
require_once __DIR__ . '/test-3-integration-tests.php';
require_once __DIR__ . '/test-4-owasp-tests.php';

/**
 * Security Test Runner
 */
class SecurityTestRunner {
    
    private static $instance = null;
    private $test_suites = [];
    
    private function __construct() {
        $this->registerTestSuites();
    }
    
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
     * Initialize test runner
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Add CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('money-quiz security-test', [$instance, 'runCliTests']);
        }
        
        // Add admin interface
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Add REST endpoint
        add_action('rest_api_init', [$instance, 'registerRestEndpoint']);
    }
    
    /**
     * Register test suites
     */
    private function registerTestSuites() {
        $this->test_suites = [
            'unit' => [
                'name' => 'Unit Tests',
                'classes' => [
                    'InputValidationTest',
                    'CsrfProtectionTest',
                    'XssProtectionTest',
                    'SqlInjectionTest',
                    'RateLimitingTest'
                ]
            ],
            'integration' => [
                'name' => 'Integration Tests',
                'classes' => [
                    'SecurityIntegrationTest',
                    'SecurityHeadersIntegrationTest',
                    'AuditLoggingIntegrationTest'
                ]
            ],
            'owasp' => [
                'name' => 'OWASP Top 10 Tests',
                'classes' => ['OwaspTop10Test']
            ]
        ];
    }
    
    /**
     * Run all security tests
     */
    public function runAllTests() {
        $results = [
            'timestamp' => current_time('mysql'),
            'suites' => []
        ];
        
        foreach ($this->test_suites as $suite_id => $suite) {
            $results['suites'][$suite_id] = $this->runTestSuite($suite);
        }
        
        $results['summary'] = $this->generateSummary($results['suites']);
        
        // Store results
        update_option('money_quiz_security_test_results', $results);
        
        return $results;
    }
    
    /**
     * Run specific test suite
     */
    public function runTestSuite($suite) {
        $results = [
            'name' => $suite['name'],
            'tests' => [],
            'passed' => 0,
            'failed' => 0,
            'errors' => 0
        ];
        
        foreach ($suite['classes'] as $class) {
            $full_class = __NAMESPACE__ . '\\' . $class;
            
            if (class_exists($full_class)) {
                $test_result = $this->runTestClass($full_class);
                $results['tests'][$class] = $test_result;
                
                $results['passed'] += $test_result['passed'];
                $results['failed'] += $test_result['failed'];
                $results['errors'] += $test_result['errors'];
            }
        }
        
        return $results;
    }
    
    /**
     * Run test class
     */
    private function runTestClass($class) {
        $result = [
            'methods' => [],
            'passed' => 0,
            'failed' => 0,
            'errors' => 0
        ];
        
        try {
            $reflection = new \ReflectionClass($class);
            $instance = new $class();
            
            foreach ($reflection->getMethods() as $method) {
                if (strpos($method->name, 'test') === 0) {
                    $test_result = $this->runTestMethod($instance, $method->name);
                    $result['methods'][$method->name] = $test_result;
                    
                    if ($test_result['status'] === 'passed') {
                        $result['passed']++;
                    } elseif ($test_result['status'] === 'failed') {
                        $result['failed']++;
                    } else {
                        $result['errors']++;
                    }
                }
            }
        } catch (\Exception $e) {
            $result['errors']++;
            $result['error_message'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Run test method
     */
    private function runTestMethod($instance, $method) {
        $start_time = microtime(true);
        
        try {
            // Setup
            if (method_exists($instance, 'setUp')) {
                $instance->setUp();
            }
            
            // Run test
            $instance->$method();
            
            // Teardown
            if (method_exists($instance, 'tearDown')) {
                $instance->tearDown();
            }
            
            return [
                'status' => 'passed',
                'duration' => microtime(true) - $start_time
            ];
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'duration' => microtime(true) - $start_time
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'duration' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Generate summary
     */
    private function generateSummary($suites) {
        $total = ['passed' => 0, 'failed' => 0, 'errors' => 0];
        
        foreach ($suites as $suite) {
            $total['passed'] += $suite['passed'];
            $total['failed'] += $suite['failed'];
            $total['errors'] += $suite['errors'];
        }
        
        $total['total'] = $total['passed'] + $total['failed'] + $total['errors'];
        $total['success_rate'] = $total['total'] > 0 
            ? round(($total['passed'] / $total['total']) * 100, 2) 
            : 0;
        
        return $total;
    }
    
    /**
     * CLI command handler
     */
    public function runCliTests($args, $assoc_args) {
        \WP_CLI::line('Running Money Quiz Security Tests...');
        
        $suite = $assoc_args['suite'] ?? 'all';
        
        if ($suite === 'all') {
            $results = $this->runAllTests();
        } else {
            $results = $this->runTestSuite($this->test_suites[$suite]);
        }
        
        // Display results
        \WP_CLI::success(sprintf(
            'Tests completed: %d passed, %d failed, %d errors',
            $results['summary']['passed'],
            $results['summary']['failed'],
            $results['summary']['errors']
        ));
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'money-quiz',
            'Security Tests',
            'Security Tests',
            'manage_options',
            'money-quiz-security-tests',
            [$this, 'renderAdminPage']
        );
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Run tests if requested
        if (isset($_POST['run_tests']) && check_admin_referer('money_quiz_security_tests')) {
            $results = $this->runAllTests();
            echo '<div class="notice notice-info"><p>Tests completed!</p></div>';
        }
        
        // Get latest results
        $results = get_option('money_quiz_security_test_results');
        
        include __DIR__ . '/views/test-results.php';
    }
    
    /**
     * Register REST endpoint
     */
    public function registerRestEndpoint() {
        register_rest_route('money-quiz/v1', '/security/test', [
            'methods' => 'POST',
            'callback' => [$this, 'runTestsEndpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * REST endpoint callback
     */
    public function runTestsEndpoint($request) {
        $suite = $request->get_param('suite') ?? 'all';
        
        if ($suite === 'all') {
            $results = $this->runAllTests();
        } else {
            $results = $this->runTestSuite($this->test_suites[$suite]);
        }
        
        return rest_ensure_response($results);
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [SecurityTestRunner::class, 'init']);

// Helper function
if (!function_exists('money_quiz_run_security_tests')) {
    function money_quiz_run_security_tests($suite = 'all') {
        return SecurityTestRunner::getInstance()->runAllTests();
    }
}