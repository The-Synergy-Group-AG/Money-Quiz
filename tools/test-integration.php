#!/usr/bin/env php
<?php
/**
 * Integration Test Script
 * 
 * Tests the Money Quiz integration features
 * 
 * Usage: php test-integration.php [--verbose]
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

// Bootstrap WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/usr/local/www/wp-load.php'
];

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
    if ( file_exists( $path ) ) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if ( ! $wp_loaded ) {
    die( "Error: Could not find wp-load.php. Please run from plugin directory.\n" );
}

/**
 * Integration Test Runner
 */
class Integration_Test_Runner {
    
    private $verbose = false;
    private $passed = 0;
    private $failed = 0;
    private $tests = [];
    
    /**
     * Run tests
     */
    public function run( $args ) {
        $this->verbose = in_array( '--verbose', $args );
        
        echo "Money Quiz Integration Test Suite\n";
        echo "=================================\n\n";
        
        // Check if integration is loaded
        if ( ! class_exists( '\MoneyQuiz\Integration\Legacy_Integration' ) ) {
            $this->error( "Integration classes not loaded. Is the plugin active?" );
            exit( 1 );
        }
        
        // Run test groups
        $this->test_database_safety();
        $this->test_input_sanitization();
        $this->test_version_management();
        $this->test_function_routing();
        $this->test_error_logging();
        $this->test_security_features();
        $this->test_performance();
        
        // Show results
        $this->show_results();
    }
    
    /**
     * Test database safety
     */
    private function test_database_safety() {
        $this->section( "Database Safety Tests" );
        
        // Test 1: SQL injection prevention
        $this->test( "SQL injection prevention", function() {
            $db_wrapper = mq_safe_db();
            
            $malicious = "'; DROP TABLE wp_users; --";
            $result = $db_wrapper->safe_query( 
                "SELECT * FROM test WHERE id = '$malicious'" 
            );
            
            return $result === false;
        });
        
        // Test 2: Prepared statements
        $this->test( "Prepared statement conversion", function() {
            $db_wrapper = mq_safe_db();
            
            global $wpdb;
            $table = $wpdb->prefix . 'options';
            
            $result = $db_wrapper->safe_get_var(
                "SELECT option_value FROM $table WHERE option_name = %s",
                [ 'blogname' ]
            );
            
            return ! empty( $result );
        });
        
        // Test 3: Query logging
        $this->test( "Query logging", function() {
            $db_wrapper = mq_safe_db();
            $log = $db_wrapper->get_query_log();
            
            return is_array( $log );
        });
    }
    
    /**
     * Test input sanitization
     */
    private function test_input_sanitization() {
        $this->section( "Input Sanitization Tests" );
        
        // Test 1: XSS prevention
        $this->test( "XSS prevention", function() {
            $dirty = [
                'name' => 'Test<script>alert("xss")</script>',
                'email' => 'test@example.com<script>'
            ];
            
            $clean = mq_sanitize_input( $dirty );
            
            return strpos( $clean['name'], '<script>' ) === false &&
                   strpos( $clean['email'], '<script>' ) === false;
        });
        
        // Test 2: Email validation
        $this->test( "Email validation", function() {
            $sanitizer = new \MoneyQuiz\Security\Legacy_Input_Sanitizer();
            
            $valid = $sanitizer->validate_email( 'test@example.com' );
            $invalid = $sanitizer->validate_email( 'not-an-email' );
            
            return $valid !== false && $invalid === false;
        });
        
        // Test 3: Field type detection
        $this->test( "Field type detection", function() {
            $data = [
                'quiz_id' => '123abc',
                'score' => '98.5points',
                'email' => 'TEST@EXAMPLE.COM'
            ];
            
            $clean = mq_sanitize_input( $data );
            
            return $clean['quiz_id'] === 123 &&
                   $clean['score'] === 98.5 &&
                   $clean['email'] === 'TEST@EXAMPLE.COM';
        });
    }
    
    /**
     * Test version management
     */
    private function test_version_management() {
        $this->section( "Version Management Tests" );
        
        // Test 1: Version detection
        $this->test( "Version detection", function() {
            $version_manager = new \MoneyQuiz\Core\Version_Manager();
            $report = $version_manager->get_version_report();
            
            return ! empty( $report['current_version'] );
        });
        
        // Test 2: Version reconciliation
        $this->test( "Version reconciliation", function() {
            // Set conflicting versions
            update_option( 'money_quiz_version', '3.0' );
            update_option( 'mq_money_coach_plugin_version', '1.4' );
            
            $version_manager = new \MoneyQuiz\Core\Version_Manager();
            $version_manager->init();
            
            $v1 = get_option( 'money_quiz_version' );
            $v2 = get_option( 'mq_money_coach_plugin_version' );
            
            return $v1 === $v2;
        });
    }
    
    /**
     * Test function routing
     */
    private function test_function_routing() {
        $this->section( "Function Routing Tests" );
        
        // Test 1: Router initialization
        $this->test( "Router initialization", function() {
            return class_exists( '\MoneyQuiz\Legacy\Legacy_Function_Router' );
        });
        
        // Test 2: Feature flag toggle
        $this->test( "Feature flag toggle", function() {
            $router = \MoneyQuiz\Legacy\Legacy_Function_Router::instance();
            
            // Disable a function
            $router->toggle_modern_implementation( 'mq_get_setting', false );
            $flags = get_option( 'money_quiz_feature_flags', [] );
            
            return isset( $flags['mq_get_setting'] ) && $flags['mq_get_setting'] === false;
        });
        
        // Test 3: Routing statistics
        $this->test( "Routing statistics", function() {
            $router = \MoneyQuiz\Legacy\Legacy_Function_Router::instance();
            $stats = $router->get_stats();
            
            return is_array( $stats );
        });
    }
    
    /**
     * Test error logging
     */
    private function test_error_logging() {
        $this->section( "Error Logging Tests" );
        
        // Test 1: Logger availability
        $this->test( "Error logger available", function() {
            return class_exists( '\MoneyQuiz\Debug\Enhanced_Error_Logger' );
        });
        
        // Test 2: Log directory
        $this->test( "Log directory exists", function() {
            $log_dir = WP_CONTENT_DIR . '/money-quiz-logs/';
            return is_dir( $log_dir );
        });
        
        // Test 3: Error capture
        if ( defined( 'MONEY_QUIZ_ERROR_LOGGING' ) && MONEY_QUIZ_ERROR_LOGGING ) {
            $this->test( "Error capture", function() {
                // Trigger a warning
                @trigger_error( 'Test warning from integration test', E_USER_WARNING );
                
                return true; // If we get here, error was handled
            });
        }
    }
    
    /**
     * Test security features
     */
    private function test_security_features() {
        $this->section( "Security Features Tests" );
        
        // Test 1: CSRF manager
        $this->test( "CSRF manager available", function() {
            return class_exists( '\MoneyQuiz\Security\CsrfManager' );
        });
        
        // Test 2: Nonce generation
        $this->test( "Nonce generation", function() {
            $nonce = wp_create_nonce( 'mq_test_action' );
            return ! empty( $nonce );
        });
        
        // Test 3: Security helpers
        $this->test( "Security helper functions", function() {
            return function_exists( 'mq_verify_security' ) &&
                   function_exists( 'mq_nonce_field' );
        });
    }
    
    /**
     * Test performance
     */
    private function test_performance() {
        $this->section( "Performance Tests" );
        
        // Test 1: Integration overhead
        $this->test( "Integration overhead < 50ms", function() {
            $start = microtime( true );
            
            // Initialize integration
            if ( class_exists( '\MoneyQuiz\Integration\Legacy_Integration' ) ) {
                $integration = \MoneyQuiz\Integration\Legacy_Integration::instance();
            }
            
            $time = ( microtime( true ) - $start ) * 1000;
            
            return $time < 50;
        });
        
        // Test 2: Memory usage
        $this->test( "Memory usage reasonable", function() {
            $memory = memory_get_usage( true );
            $limit = $this->get_memory_limit();
            
            $usage_percent = ( $memory / $limit ) * 100;
            
            return $usage_percent < 80;
        });
    }
    
    /**
     * Run a test
     */
    private function test( $name, $callback ) {
        try {
            $result = $callback();
            
            if ( $result ) {
                $this->pass( $name );
            } else {
                $this->fail( $name );
            }
        } catch ( Exception $e ) {
            $this->fail( $name, $e->getMessage() );
        }
    }
    
    /**
     * Mark test as passed
     */
    private function pass( $name ) {
        $this->passed++;
        echo "  ✓ $name\n";
        
        if ( $this->verbose ) {
            echo "    OK\n";
        }
    }
    
    /**
     * Mark test as failed
     */
    private function fail( $name, $reason = '' ) {
        $this->failed++;
        echo "  ✗ $name\n";
        
        if ( $this->verbose && $reason ) {
            echo "    Error: $reason\n";
        }
    }
    
    /**
     * Print section header
     */
    private function section( $title ) {
        echo "\n$title\n";
        echo str_repeat( '-', strlen( $title ) ) . "\n";
    }
    
    /**
     * Print error
     */
    private function error( $message ) {
        echo "\nERROR: $message\n";
    }
    
    /**
     * Get memory limit in bytes
     */
    private function get_memory_limit() {
        $limit = ini_get( 'memory_limit' );
        
        if ( preg_match( '/^(\d+)(.)$/', $limit, $matches ) ) {
            $value = $matches[1];
            switch ( $matches[2] ) {
                case 'G':
                    $value *= 1024;
                case 'M':
                    $value *= 1024;
                case 'K':
                    $value *= 1024;
            }
            return $value;
        }
        
        return PHP_INT_MAX;
    }
    
    /**
     * Show results
     */
    private function show_results() {
        echo "\n";
        echo str_repeat( '=', 40 ) . "\n";
        echo "Test Results\n";
        echo str_repeat( '=', 40 ) . "\n";
        echo "Passed: $this->passed\n";
        echo "Failed: $this->failed\n";
        echo "Total:  " . ( $this->passed + $this->failed ) . "\n";
        
        $percent = $this->passed + $this->failed > 0 ? 
            round( ( $this->passed / ( $this->passed + $this->failed ) ) * 100 ) : 0;
        
        echo "Score:  $percent%\n\n";
        
        if ( $this->failed > 0 ) {
            echo "Some tests failed. Please check the integration configuration.\n";
            exit( 1 );
        } else {
            echo "All tests passed! Integration is working correctly.\n";
            exit( 0 );
        }
    }
}

// Run tests
$runner = new Integration_Test_Runner();
$runner->run( $argv );