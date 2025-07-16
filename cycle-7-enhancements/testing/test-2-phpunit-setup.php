<?php
/**
 * PHPUnit Test Setup
 * 
 * @package MoneyQuiz\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Testing;

/**
 * PHPUnit Setup
 */
class PHPUnitSetup {
    
    /**
     * Generate PHPUnit configuration
     */
    public static function generateConfig() {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
    bootstrap="tests/bootstrap.php"
    colors="true"
    beStrictAboutCoversAnnotation="true"
    beStrictAboutOutputDuringTests="true"
    beStrictAboutTestsThatDoNotTestAnything="true"
    beStrictAboutTodoAnnotatedTests="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    verbose="true">

    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">tests/unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory suffix="Test.php">tests/integration</directory>
        </testsuite>
        <testsuite name="security">
            <directory suffix="Test.php">cycle-6-security/security-testing</directory>
        </testsuite>
    </testsuites>

    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">includes</directory>
            <directory suffix=".php">cycle-6-security</directory>
            <directory suffix=".php">cycle-7-enhancements</directory>
        </include>
        <exclude>
            <directory>vendor</directory>
            <directory>tests</directory>
            <file>money-quiz.php</file>
        </exclude>
        <report>
            <clover outputFile="build/logs/clover.xml"/>
            <html outputDirectory="build/coverage"/>
            <text outputFile="build/coverage.txt"/>
        </report>
    </coverage>

    <logging>
        <junit outputFile="build/logs/junit.xml"/>
    </logging>

    <php>
        <const name="WP_TESTS_DOMAIN" value="example.org"/>
        <const name="WP_TESTS_EMAIL" value="admin@example.org"/>
        <const name="WP_TESTS_TITLE" value="Test Blog"/>
        <const name="WP_PHP_BINARY" value="php"/>
    </php>
</phpunit>
XML;
    }
    
    /**
     * Generate bootstrap file
     */
    public static function generateBootstrap() {
        return <<<'PHP'
<?php
/**
 * PHPUnit bootstrap file
 */

// Get WordPress tests directory
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Get WordPress core directory
$_core_dir = getenv('WP_CORE_DIR');

if (!$_core_dir) {
    $_core_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(dirname(__FILE__)) . '/money-quiz.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Include test case classes
require_once dirname(__FILE__) . '/test-case.php';
PHP;
    }
    
    /**
     * Generate base test case
     */
    public static function generateTestCase() {
        return <<<'PHP'
<?php
/**
 * Base test case
 */

namespace MoneyQuiz\Tests;

use WP_UnitTestCase;

/**
 * Base test case class
 */
abstract class TestCase extends WP_UnitTestCase {
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Reset data
        $this->resetTables();
        
        // Create test user
        $this->test_user = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        
        wp_set_current_user($this->test_user);
    }
    
    /**
     * Teardown after each test
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Clean up test data
        $this->cleanupTestData();
    }
    
    /**
     * Reset plugin tables
     */
    protected function resetTables() {
        global $wpdb;
        
        $tables = [
            'money_quiz_quizzes',
            'money_quiz_questions',
            'money_quiz_results'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table}");
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Remove test users
        $users = get_users(['meta_key' => 'test_user']);
        foreach ($users as $user) {
            wp_delete_user($user->ID);
        }
    }
    
    /**
     * Create test quiz
     */
    protected function createTestQuiz($args = []) {
        global $wpdb;
        
        $defaults = [
            'title' => 'Test Quiz',
            'description' => 'Test quiz description',
            'status' => 'published',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($args, $defaults);
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_quizzes',
            $data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create test question
     */
    protected function createTestQuestion($quiz_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'quiz_id' => $quiz_id,
            'text' => 'Test question?',
            'type' => 'multiple',
            'options' => json_encode(['A', 'B', 'C', 'D']),
            'correct_answer' => 'A',
            'points' => 10,
            'order_num' => 1
        ];
        
        $data = wp_parse_args($args, $defaults);
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_questions',
            $data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Assert WP_Error
     */
    protected function assertWPError($actual, $code = null) {
        $this->assertInstanceOf('WP_Error', $actual);
        
        if ($code !== null) {
            $this->assertEquals($code, $actual->get_error_code());
        }
    }
    
    /**
     * Assert successful AJAX response
     */
    protected function assertAjaxSuccess($response) {
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
    }
    
    /**
     * Assert failed AJAX response
     */
    protected function assertAjaxError($response, $code = null) {
        $this->assertArrayHasKey('success', $response);
        $this->assertFalse($response['success']);
        
        if ($code !== null) {
            $this->assertEquals($code, $response['data']['code'] ?? '');
        }
    }
}
PHP;
    }
    
    /**
     * Generate sample unit test
     */
    public static function generateSampleTest() {
        return <<<'PHP'
<?php
/**
 * Sample unit test
 */

namespace MoneyQuiz\Tests\Unit;

use MoneyQuiz\Tests\TestCase;

class QuizManagerTest extends TestCase {
    
    private $quiz_manager;
    
    public function setUp(): void {
        parent::setUp();
        $this->quiz_manager = new \MoneyQuiz\QuizManager();
    }
    
    /**
     * Test quiz creation
     */
    public function test_create_quiz() {
        $quiz_data = [
            'title' => 'Test Quiz',
            'description' => 'A test quiz'
        ];
        
        $quiz_id = $this->quiz_manager->createQuiz($quiz_data);
        
        $this->assertIsNumeric($quiz_id);
        $this->assertGreaterThan(0, $quiz_id);
        
        // Verify quiz was created
        $quiz = $this->quiz_manager->getQuiz($quiz_id);
        $this->assertEquals('Test Quiz', $quiz->title);
    }
    
    /**
     * Test quiz validation
     */
    public function test_quiz_validation() {
        // Missing title
        $result = $this->quiz_manager->createQuiz([
            'description' => 'No title'
        ]);
        
        $this->assertWPError($result, 'missing_title');
        
        // Empty title
        $result = $this->quiz_manager->createQuiz([
            'title' => '',
            'description' => 'Empty title'
        ]);
        
        $this->assertWPError($result, 'empty_title');
    }
    
    /**
     * Test quiz permissions
     */
    public function test_quiz_permissions() {
        // Create quiz as admin
        $quiz_id = $this->createTestQuiz();
        
        // Switch to subscriber
        $subscriber = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);
        
        // Try to edit quiz
        $result = $this->quiz_manager->updateQuiz($quiz_id, [
            'title' => 'Updated'
        ]);
        
        $this->assertWPError($result, 'insufficient_permissions');
    }
    
    /**
     * Test quiz deletion
     */
    public function test_delete_quiz() {
        $quiz_id = $this->createTestQuiz();
        
        // Add questions
        $this->createTestQuestion($quiz_id);
        $this->createTestQuestion($quiz_id);
        
        // Delete quiz
        $result = $this->quiz_manager->deleteQuiz($quiz_id);
        
        $this->assertTrue($result);
        
        // Verify quiz and questions are deleted
        $this->assertNull($this->quiz_manager->getQuiz($quiz_id));
        
        global $wpdb;
        $questions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_questions WHERE quiz_id = %d",
            $quiz_id
        ));
        
        $this->assertEquals(0, $questions);
    }
}
PHP;
    }
    
    /**
     * Generate composer scripts
     */
    public static function getComposerScripts() {
        return [
            'test' => 'phpunit',
            'test:unit' => 'phpunit --testsuite unit',
            'test:integration' => 'phpunit --testsuite integration',
            'test:coverage' => 'phpunit --coverage-html build/coverage',
            'test:watch' => 'phpunit-watcher watch'
        ];
    }
    
    /**
     * Write test files
     */
    public static function writeTestFiles($plugin_dir) {
        // PHPUnit config
        file_put_contents(
            $plugin_dir . '/phpunit.xml.dist',
            self::generateConfig()
        );
        
        // Bootstrap
        $tests_dir = $plugin_dir . '/tests';
        if (!file_exists($tests_dir)) {
            wp_mkdir_p($tests_dir);
        }
        file_put_contents($tests_dir . '/bootstrap.php', self::generateBootstrap());
        
        // Base test case
        file_put_contents($tests_dir . '/test-case.php', self::generateTestCase());
        
        // Sample test
        $unit_dir = $tests_dir . '/unit';
        if (!file_exists($unit_dir)) {
            wp_mkdir_p($unit_dir);
        }
        file_put_contents($unit_dir . '/QuizManagerTest.php', self::generateSampleTest());
    }
}