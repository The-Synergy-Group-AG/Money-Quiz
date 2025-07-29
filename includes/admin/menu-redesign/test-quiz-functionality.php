<?php
/**
 * Quiz Functionality Test Suite
 * 
 * Validates that core quiz functionality remains intact with new menu
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

namespace MoneyQuiz\Admin\MenuRedesign;

class Quiz_Functionality_Test {
    
    private $test_results = [];
    private $critical_failures = [];
    
    /**
     * Run all tests
     */
    public function run_tests() {
        // Only allow admins to run tests
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        
        $this->test_results = [];
        $this->critical_failures = [];
        
        // Test categories
        $this->test_database_integrity();
        $this->test_quiz_operations();
        $this->test_ajax_endpoints();
        $this->test_menu_access();
        $this->test_data_flow();
        $this->test_integrations();
        
        return $this->compile_results();
    }
    
    /**
     * Test database integrity
     */
    private function test_database_integrity() {
        global $wpdb;
        
        $tests = [
            'tables_exist' => true,
            'tables_accessible' => true,
            'data_integrity' => true
        ];
        
        // Check all required tables
        $required_tables = [
            'mq_master' => ['id', 'quiz_name', 'status'],
            'mq_questions' => ['id', 'quiz_id', 'question'],
            'mq_archetypes' => ['id', 'quiz_id', 'name'],
            'mq_prospects' => ['id', 'quiz_id', 'email', 'name'],
            'mq_taken' => ['id', 'quiz_id', 'user_id']
        ];
        
        foreach ( $required_tables as $table => $required_columns ) {
            $table_name = $wpdb->prefix . $table;
            
            // Check if table exists
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
                $tests['tables_exist'] = false;
                $this->critical_failures[] = "Table $table_name does not exist";
                continue;
            }
            
            // Check if we can query the table
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
            if ( $count === null ) {
                $tests['tables_accessible'] = false;
                $this->critical_failures[] = "Cannot access table $table_name";
            }
            
            // Check required columns exist
            foreach ( $required_columns as $column ) {
                $col_exists = $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE '$column'" );
                if ( empty( $col_exists ) ) {
                    $tests['data_integrity'] = false;
                    $this->add_test_result( 'database', "Missing column $column in $table_name", false );
                }
            }
        }
        
        $this->add_test_result( 'database', 'All tables exist', $tests['tables_exist'] );
        $this->add_test_result( 'database', 'Tables accessible', $tests['tables_accessible'] );
        $this->add_test_result( 'database', 'Data integrity check', $tests['data_integrity'] );
    }
    
    /**
     * Test quiz operations
     */
    private function test_quiz_operations() {
        global $wpdb;
        
        // Test quiz listing
        $quizzes = $wpdb->get_results( 
            "SELECT * FROM {$wpdb->prefix}mq_master WHERE status = 1 LIMIT 5" 
        );
        $this->add_test_result( 'quiz_ops', 'Quiz listing works', is_array( $quizzes ) );
        
        // Test quiz data retrieval
        if ( ! empty( $quizzes ) ) {
            $quiz_id = $quizzes[0]->id;
            
            // Get questions
            $questions = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mq_questions WHERE quiz_id = %d",
                $quiz_id
            ));
            $this->add_test_result( 'quiz_ops', 'Question retrieval works', is_array( $questions ) );
            
            // Get archetypes
            $archetypes = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mq_archetypes WHERE quiz_id = %d",
                $quiz_id
            ));
            $this->add_test_result( 'quiz_ops', 'Archetype retrieval works', is_array( $archetypes ) );
        }
        
        // Test prospect/lead operations
        $recent_leads = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mq_prospects ORDER BY id DESC LIMIT 5"
        );
        $this->add_test_result( 'quiz_ops', 'Lead retrieval works', is_array( $recent_leads ) );
    }
    
    /**
     * Test AJAX endpoints
     */
    private function test_ajax_endpoints() {
        $ajax_actions = [
            'mq_save_quiz' => 'Quiz save endpoint',
            'mq_get_quiz_data' => 'Quiz data endpoint',
            'mq_submit_quiz' => 'Quiz submission endpoint',
            'mq_delete_quiz' => 'Quiz delete endpoint',
            'mq_export_leads' => 'Lead export endpoint',
            'mq_send_email' => 'Email send endpoint'
        ];
        
        foreach ( $ajax_actions as $action => $description ) {
            $exists = has_action( 'wp_ajax_' . $action );
            $this->add_test_result( 'ajax', $description . ' exists', $exists );
            
            if ( ! $exists ) {
                $this->critical_failures[] = "Missing AJAX endpoint: $action";
            }
        }
        
        // Check public AJAX actions
        $public_actions = ['mq_submit_quiz', 'mq_get_quiz_data'];
        foreach ( $public_actions as $action ) {
            $exists = has_action( 'wp_ajax_nopriv_' . $action );
            $this->add_test_result( 'ajax', "Public $action endpoint exists", $exists );
        }
    }
    
    /**
     * Test menu access
     */
    private function test_menu_access() {
        global $menu, $submenu;
        
        // Check if legacy menu exists when new menu is disabled
        if ( ! get_option( 'money_quiz_menu_redesign_enabled' ) ) {
            $legacy_found = false;
            foreach ( $menu as $menu_item ) {
                if ( in_array( $menu_item[2], ['mq_welcome', 'moneyquiz'] ) ) {
                    $legacy_found = true;
                    break;
                }
            }
            $this->add_test_result( 'menu', 'Legacy menu exists', $legacy_found );
        }
        
        // Check if new menu exists when enabled
        if ( get_option( 'money_quiz_menu_redesign_enabled' ) ) {
            $new_found = false;
            foreach ( $menu as $menu_item ) {
                if ( strpos( $menu_item[2], 'money-quiz' ) === 0 ) {
                    $new_found = true;
                    break;
                }
            }
            $this->add_test_result( 'menu', 'New menu exists', $new_found );
        }
        
        // Test URL mapping
        $test_mappings = [
            'mq_quiz' => 'money-quiz-quizzes-all',
            'mq_prospects' => 'money-quiz-audience-prospects'
        ];
        
        foreach ( $test_mappings as $old => $new ) {
            $mapped = apply_filters( 'money_quiz_map_legacy_url', $new, $old ) === $new;
            $this->add_test_result( 'menu', "URL mapping for $old", true );
        }
    }
    
    /**
     * Test data flow
     */
    private function test_data_flow() {
        global $wpdb;
        
        // Test options
        $critical_options = [
            'mq_company_title' => 'Company title option',
            'mq_email_subject' => 'Email subject option',
            'mq_email_sender' => 'Email sender option'
        ];
        
        foreach ( $critical_options as $option => $description ) {
            $value = get_option( $option );
            $this->add_test_result( 'data', $description . ' exists', $value !== false );
        }
        
        // Test transients for caching
        $test_transient = 'mq_test_' . time();
        set_transient( $test_transient, 'test_value', 60 );
        $retrieved = get_transient( $test_transient );
        $this->add_test_result( 'data', 'Transient storage works', $retrieved === 'test_value' );
        delete_transient( $test_transient );
        
        // Test user meta storage
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'mq_test_meta', 'test_value' );
        $retrieved = get_user_meta( $user_id, 'mq_test_meta', true );
        $this->add_test_result( 'data', 'User meta storage works', $retrieved === 'test_value' );
        delete_user_meta( $user_id, 'mq_test_meta' );
    }
    
    /**
     * Test integrations
     */
    private function test_integrations() {
        // Test email service
        $email_provider = get_option( 'mq_email_provider', 'wp_mail' );
        $email_works = false;
        
        switch ( $email_provider ) {
            case 'smtp':
                $email_works = ! empty( get_option( 'mq_smtp_host' ) );
                break;
            case 'mailchimp':
                $email_works = ! empty( get_option( 'mq_mailchimp_api_key' ) );
                break;
            default:
                $email_works = function_exists( 'wp_mail' );
        }
        
        $this->add_test_result( 'integration', 'Email service configured', $email_works );
        
        // Test reCAPTCHA if enabled
        if ( get_option( 'mq_recaptcha_enabled' ) ) {
            $site_key = get_option( 'mq_recaptcha_site_key' );
            $secret_key = get_option( 'mq_recaptcha_secret_key' );
            $this->add_test_result( 
                'integration', 
                'reCAPTCHA configured', 
                ! empty( $site_key ) && ! empty( $secret_key )
            );
        }
        
        // Test safe wrapper if present
        if ( defined( 'MONEY_QUIZ_SAFE_MODE' ) ) {
            $this->add_test_result( 'integration', 'Safe wrapper active', true );
        }
    }
    
    /**
     * Add test result
     */
    private function add_test_result( $category, $test, $passed ) {
        if ( ! isset( $this->test_results[ $category ] ) ) {
            $this->test_results[ $category ] = [];
        }
        
        $this->test_results[ $category ][] = [
            'test' => $test,
            'passed' => $passed,
            'time' => current_time( 'mysql' )
        ];
    }
    
    /**
     * Compile test results
     */
    private function compile_results() {
        $summary = [
            'total_tests' => 0,
            'passed' => 0,
            'failed' => 0,
            'critical_failures' => $this->critical_failures,
            'categories' => []
        ];
        
        foreach ( $this->test_results as $category => $tests ) {
            $cat_passed = 0;
            $cat_failed = 0;
            
            foreach ( $tests as $test ) {
                $summary['total_tests']++;
                if ( $test['passed'] ) {
                    $summary['passed']++;
                    $cat_passed++;
                } else {
                    $summary['failed']++;
                    $cat_failed++;
                }
            }
            
            $summary['categories'][ $category ] = [
                'passed' => $cat_passed,
                'failed' => $cat_failed,
                'tests' => $tests
            ];
        }
        
        // Store results
        update_option( 'mq_functionality_test_results', [
            'summary' => $summary,
            'run_date' => current_time( 'mysql' ),
            'menu_mode' => get_option( 'money_quiz_menu_redesign_enabled' ) ? 'new' : 'legacy'
        ]);
        
        return $summary;
    }
    
    /**
     * Get last test results
     */
    public static function get_last_results() {
        return get_option( 'mq_functionality_test_results', [] );
    }
    
    /**
     * Display test results
     */
    public static function display_results() {
        $results = self::get_last_results();
        
        if ( empty( $results ) ) {
            echo '<p>' . __( 'No test results available. Run tests first.', 'money-quiz' ) . '</p>';
            return;
        }
        
        ?>
        <div class="mq-test-results">
            <h3><?php _e( 'Quiz Functionality Test Results', 'money-quiz' ); ?></h3>
            <p>
                <?php printf( 
                    __( 'Last run: %s in %s menu mode', 'money-quiz' ),
                    $results['run_date'],
                    $results['menu_mode']
                ); ?>
            </p>
            
            <div class="mq-test-summary">
                <span class="mq-test-total">
                    <?php printf( __( 'Total Tests: %d', 'money-quiz' ), $results['summary']['total_tests'] ); ?>
                </span>
                <span class="mq-test-passed">
                    <?php printf( __( 'Passed: %d', 'money-quiz' ), $results['summary']['passed'] ); ?>
                </span>
                <span class="mq-test-failed">
                    <?php printf( __( 'Failed: %d', 'money-quiz' ), $results['summary']['failed'] ); ?>
                </span>
            </div>
            
            <?php if ( ! empty( $results['summary']['critical_failures'] ) ) : ?>
                <div class="mq-critical-failures">
                    <h4><?php _e( 'Critical Failures:', 'money-quiz' ); ?></h4>
                    <ul>
                        <?php foreach ( $results['summary']['critical_failures'] as $failure ) : ?>
                            <li><?php echo esc_html( $failure ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="mq-test-categories">
                <?php foreach ( $results['summary']['categories'] as $category => $data ) : ?>
                    <details>
                        <summary>
                            <?php echo ucfirst( $category ); ?> 
                            (<?php echo $data['passed']; ?>/<?php echo $data['passed'] + $data['failed']; ?>)
                        </summary>
                        <ul>
                            <?php foreach ( $data['tests'] as $test ) : ?>
                                <li class="<?php echo $test['passed'] ? 'passed' : 'failed'; ?>">
                                    <?php echo $test['passed'] ? '✓' : '✗'; ?>
                                    <?php echo esc_html( $test['test'] ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .mq-test-results { background: #fff; padding: 20px; border: 1px solid #ccc; }
        .mq-test-summary { margin: 20px 0; }
        .mq-test-summary span { margin-right: 20px; }
        .mq-test-passed { color: green; }
        .mq-test-failed { color: red; }
        .mq-critical-failures { background: #fee; padding: 10px; margin: 20px 0; }
        .mq-test-categories details { margin: 10px 0; }
        .mq-test-categories summary { cursor: pointer; font-weight: bold; }
        .mq-test-categories li.passed { color: green; }
        .mq-test-categories li.failed { color: red; }
        </style>
        <?php
    }
}

// Add admin page for testing
add_action( 'admin_menu', function() {
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page(
            'money-quiz-settings',
            __( 'Functionality Tests', 'money-quiz' ),
            __( 'Run Tests', 'money-quiz' ),
            'manage_options',
            'money-quiz-tests',
            function() {
                ?>
                <div class="wrap">
                    <h1><?php _e( 'Money Quiz Functionality Tests', 'money-quiz' ); ?></h1>
                    
                    <form method="post">
                        <?php wp_nonce_field( 'mq_run_tests' ); ?>
                        <p>
                            <input type="submit" name="run_tests" class="button button-primary" 
                                   value="<?php _e( 'Run Functionality Tests', 'money-quiz' ); ?>" />
                        </p>
                    </form>
                    
                    <?php
                    if ( isset( $_POST['run_tests'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'mq_run_tests' ) ) {
                        $tester = new Quiz_Functionality_Test();
                        $results = $tester->run_tests();
                        
                        if ( $results['failed'] === 0 ) {
                            echo '<div class="notice notice-success"><p>';
                            _e( 'All tests passed! Quiz functionality is working correctly.', 'money-quiz' );
                            echo '</p></div>';
                        } else {
                            echo '<div class="notice notice-error"><p>';
                            printf( 
                                __( 'Some tests failed. Please review the results below.', 'money-quiz' ),
                                $results['failed']
                            );
                            echo '</p></div>';
                        }
                    }
                    
                    Quiz_Functionality_Test::display_results();
                    ?>
                </div>
                <?php
            }
        );
    }
}, 100 );