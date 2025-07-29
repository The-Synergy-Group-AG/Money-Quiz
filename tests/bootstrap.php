<?php
/**
 * PHPUnit bootstrap file for Money Quiz plugin
 *
 * @package MoneyQuiz
 */

// Determine the tests directory
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit parameters to WP_TESTS_* constants
if ( ! defined( 'WP_TESTS_FORCE_KNOWN_BUGS' ) && getenv( 'WP_TESTS_FORCE_KNOWN_BUGS' ) ) {
    define( 'WP_TESTS_FORCE_KNOWN_BUGS', getenv( 'WP_TESTS_FORCE_KNOWN_BUGS' ) );
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Define plugin constants
    if ( ! defined( 'MONEY_QUIZ_VERSION' ) ) {
        define( 'MONEY_QUIZ_VERSION', '4.0.0' );
    }
    
    if ( ! defined( 'MONEY_QUIZ_PLUGIN_FILE' ) ) {
        define( 'MONEY_QUIZ_PLUGIN_FILE', dirname( __DIR__ ) . '/money-quiz.php' );
    }
    
    if ( ! defined( 'MONEY_QUIZ_PLUGIN_DIR' ) ) {
        define( 'MONEY_QUIZ_PLUGIN_DIR', dirname( __DIR__ ) );
    }
    
    if ( ! defined( 'MONEY_QUIZ_PLUGIN_URL' ) ) {
        define( 'MONEY_QUIZ_PLUGIN_URL', 'http://example.org/wp-content/plugins/money-quiz' );
    }
    
    // Load Composer autoloader
    require_once dirname( __DIR__ ) . '/vendor/autoload.php';
    
    // Load the main plugin file
    require dirname( __DIR__ ) . '/money-quiz.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test case classes
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/IntegrationTestCase.php';