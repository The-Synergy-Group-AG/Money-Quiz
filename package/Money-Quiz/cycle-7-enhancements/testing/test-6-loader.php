<?php
/**
 * Testing System Loader
 * 
 * @package MoneyQuiz\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Testing;

// Load testing components
require_once __DIR__ . '/test-1-jest-config.php';
require_once __DIR__ . '/test-2-phpunit-setup.php';
require_once __DIR__ . '/test-3-test-factories.php';
require_once __DIR__ . '/test-4-coverage-config.php';
require_once __DIR__ . '/test-5-ci-integration.php';

/**
 * Testing Manager
 */
class TestingManager {
    
    private static $instance = null;
    private $factories;
    private $coverage;
    
    private function __construct() {
        $this->factories = TestFactories::getInstance();
        $this->coverage = new CoverageConfig();
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
     * Initialize testing system
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Add CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('money-quiz test', [$instance, 'runTests']);
            \WP_CLI::add_command('money-quiz coverage', [$instance, 'checkCoverage']);
        }
        
        // Add admin page
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Register test data cleanup
        register_deactivation_hook(MONEY_QUIZ_FILE, [$instance, 'cleanupTestData']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        add_submenu_page(
            'money-quiz',
            'Testing',
            'Testing',
            'manage_options',
            'money-quiz-testing',
            [$this, 'renderTestingPage']
        );
    }
    
    /**
     * Render testing page
     */
    public function renderTestingPage() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        ?>
        <div class="wrap">
            <h1>Money Quiz Testing</h1>
            
            <div class="card">
                <h2>Test Configuration</h2>
                <pre><?php echo esc_html(json_encode($this->getTestConfig(), JSON_PRETTY_PRINT)); ?></pre>
            </div>
            
            <div class="card">
                <h2>Coverage Configuration</h2>
                <pre><?php echo esc_html(json_encode(CoverageConfig::generate(), JSON_PRETTY_PRINT)); ?></pre>
            </div>
            
            <div class="card">
                <h2>Test Factories</h2>
                <p>Available factories: quiz, question, result, user</p>
                <button class="button" onclick="runTestFactories()">Generate Test Data</button>
            </div>
            
            <div class="card">
                <h2>CI/CD Configuration</h2>
                <p>Available CI configurations:</p>
                <ul>
                    <li>GitHub Actions</li>
                    <li>GitLab CI</li>
                    <li>Jenkins</li>
                    <li>CircleCI</li>
                    <li>Travis CI</li>
                </ul>
            </div>
        </div>
        
        <script>
        function runTestFactories() {
            if (confirm('Generate test data?')) {
                // Would trigger AJAX to generate test data
                alert('Test data generation would be triggered here');
            }
        }
        </script>
        <?php
    }
    
    /**
     * Get test configuration
     */
    public function getTestConfig() {
        return [
            'php' => [
                'framework' => 'PHPUnit',
                'version' => phpversion(),
                'testDir' => 'tests/',
                'bootstrap' => 'tests/bootstrap.php'
            ],
            'js' => [
                'framework' => 'Jest',
                'testDir' => 'assets/js/__tests__',
                'setupFile' => 'tests/js/setup.js'
            ],
            'coverage' => CoverageConfig::generate(),
            'ci' => [
                'github' => file_exists('.github/workflows/tests.yml'),
                'gitlab' => file_exists('.gitlab-ci.yml'),
                'jenkins' => file_exists('Jenkinsfile'),
                'circle' => file_exists('.circleci/config.yml'),
                'travis' => file_exists('.travis.yml')
            ]
        ];
    }
    
    /**
     * Run tests (WP-CLI)
     */
    public function runTests($args, $assoc_args) {
        $type = $args[0] ?? 'all';
        
        switch ($type) {
            case 'php':
                \WP_CLI::launch_self('eval', ['vendor/bin/phpunit'], false, true);
                break;
                
            case 'js':
                passthru('npm test');
                break;
                
            case 'all':
                $this->runTests(['php'], $assoc_args);
                $this->runTests(['js'], $assoc_args);
                break;
                
            default:
                \WP_CLI::error("Unknown test type: $type");
        }
    }
    
    /**
     * Check coverage (WP-CLI)
     */
    public function checkCoverage($args, $assoc_args) {
        $report = $assoc_args['report'] ?? 'build/coverage/coverage.txt';
        
        if (!file_exists($report)) {
            \WP_CLI::error("Coverage report not found: $report");
        }
        
        $data = CoverageConfig::parseCoverageReport($report);
        $failures = CoverageConfig::checkThresholds($data);
        
        if (empty($failures)) {
            \WP_CLI::success('All coverage thresholds met!');
        } else {
            foreach ($failures as $failure) {
                \WP_CLI::warning($failure);
            }
            \WP_CLI::error('Coverage thresholds not met');
        }
    }
    
    /**
     * Setup test environment
     */
    public static function setupTestEnvironment() {
        // Write configuration files
        $plugin_dir = dirname(dirname(__DIR__));
        
        // PHPUnit setup
        PHPUnitSetup::writeTestFiles($plugin_dir);
        
        // Jest setup
        JestConfig::writeConfig($plugin_dir);
        
        // CI/CD configs
        self::writeCIConfigs($plugin_dir);
        
        \WP_CLI::success('Test environment setup complete!');
    }
    
    /**
     * Write CI configurations
     */
    private static function writeCIConfigs($plugin_dir) {
        // GitHub Actions
        $github_dir = $plugin_dir . '/.github/workflows';
        if (!file_exists($github_dir)) {
            wp_mkdir_p($github_dir);
        }
        file_put_contents(
            $github_dir . '/tests.yml',
            CIIntegration::generateGitHubActions()
        );
        
        // GitLab CI
        file_put_contents(
            $plugin_dir . '/.gitlab-ci.yml',
            CIIntegration::generateGitLabCI()
        );
        
        // Other CI configs...
    }
    
    /**
     * Cleanup test data
     */
    public function cleanupTestData() {
        global $wpdb;
        
        // Remove test users
        $users = get_users(['meta_key' => 'test_user']);
        foreach ($users as $user) {
            wp_delete_user($user->ID);
        }
        
        // Clean test data from tables
        $wpdb->query("DELETE FROM {$wpdb->prefix}money_quiz_results WHERE id < 0");
        $wpdb->query("DELETE FROM {$wpdb->prefix}money_quiz_questions WHERE id < 0");
        $wpdb->query("DELETE FROM {$wpdb->prefix}money_quiz_quizzes WHERE id < 0");
    }
}

// Initialize if in testing environment
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('plugins_loaded', [TestingManager::class, 'init']);
}

// CLI commands
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('money-quiz test:setup', [TestingManager::class, 'setupTestEnvironment']);
}