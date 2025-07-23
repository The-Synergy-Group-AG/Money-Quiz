<?php
/**
 * PHPUnit bootstrap file
 *
 * @package MoneyQuiz
 */

// Define test constants.
define( 'MONEY_QUIZ_TESTS', true );
define( 'MONEY_QUIZ_PLUGIN_FILE', dirname( __DIR__ ) . '/money-quiz.php' );

// Load Composer autoloader.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $autoloader ) ) {
	die( 'Please run "composer install" first.' . PHP_EOL );
}

require_once $autoloader;

// Load WordPress test environment.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wp_tests_dir ) {
	$wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at: $wp_tests_dir\n";
	echo "Please set the WP_TESTS_DIR environment variable.\n";
	exit( 1 );
}

// Load WordPress test functions.
require_once $wp_tests_dir . '/includes/functions.php';

// Load plugin for testing.
tests_add_filter( 'muplugins_loaded', function() {
	require dirname( __DIR__ ) . '/money-quiz.php';
} );

// Load WordPress test suite.
require $wp_tests_dir . '/includes/bootstrap.php';

// Load test base classes.
require __DIR__ . '/TestCase.php';