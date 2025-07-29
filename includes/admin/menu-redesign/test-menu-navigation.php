<?php
/**
 * Menu Navigation Test Suite
 * 
 * Tests menu registration, navigation, and functionality
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MoneyQuiz_Menu_Navigation_Test {
    
    private $test_results = [];
    private $menu_redesign;
    
    public function __construct() {
        // Get menu redesign instance
        $this->menu_redesign = new \MoneyQuiz\Admin\Menu_Redesign();
    }
    
    /**
     * Run all navigation tests
     */
    public function run_tests() {
        ?>
        <div class="wrap">
            <h1><?php _e( '🧪 Money Quiz Menu Navigation Tests', 'money-quiz' ); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e( 'This page tests the new menu system registration, navigation, and functionality.', 'money-quiz' ); ?></p>
            </div>
            
            <?php
            // Run test groups
            $this->test_menu_registration();
            $this->test_submenu_structure();
            $this->test_legacy_redirects();
            $this->test_menu_capabilities();
            $this->test_navigation_flow();
            $this->test_quick_access_menu();
            $this->test_body_classes();
            $this->test_asset_loading();
            
            // Display summary
            $this->display_test_summary();
            ?>
            
        </div>
        <?php
    }
    
    /**
     * Test 1: Menu Registration
     */
    private function test_menu_registration() {
        $this->start_test_group( __( 'Menu Registration', 'money-quiz' ) );
        
        global $menu, $submenu;
        
        // Test main menu exists
        $main_menu_found = false;
        foreach ( $menu as $menu_item ) {
            if ( $menu_item[2] === 'money-quiz' ) {
                $main_menu_found = true;
                break;
            }
        }
        $this->add_test_result( 'Main Money Quiz menu registered', $main_menu_found );
        
        // Test section menus
        $sections = ['dashboard', 'quizzes', 'audience', 'marketing', 'settings'];
        foreach ( $sections as $section ) {
            $section_found = false;
            foreach ( $menu as $menu_item ) {
                if ( $menu_item[2] === 'money-quiz-' . $section ) {
                    $section_found = true;
                    break;
                }
            }
            $this->add_test_result( ucfirst( $section ) . ' menu registered', $section_found );
        }
        
        $this->end_test_group();
    }
    
    /**
     * Test 2: Submenu Structure
     */
    private function test_submenu_structure() {
        $this->start_test_group( __( 'Submenu Structure', 'money-quiz' ) );
        
        global $submenu;
        
        // Expected submenu structure
        $expected_structure = [
            'money-quiz-dashboard' => ['overview', 'activity', 'stats', 'health'],
            'money-quiz-quizzes' => ['all', 'add-new', 'questions', 'archetypes'],
            'money-quiz-audience' => ['results', 'prospects', 'campaigns'],
            'money-quiz-marketing' => ['cta', 'popups'],
            'money-quiz-settings' => ['general', 'email', 'integrations', 'security', 'advanced']
        ];
        
        foreach ( $expected_structure as $parent => $expected_items ) {
            if ( isset( $submenu[$parent] ) ) {
                $submenu_slugs = array_map( function($item) use ($parent) {
                    // Extract the submenu part from the full slug
                    return str_replace( $parent . '-', '', $item[2] );
                }, $submenu[$parent] );
                
                foreach ( $expected_items as $expected_item ) {
                    $found = in_array( $expected_item, $submenu_slugs ) || 
                            in_array( $parent . '-' . $expected_item, array_column( $submenu[$parent], 2 ) );
                    $this->add_test_result( 
                        ucfirst( str_replace( '-', ' ', $parent ) ) . ' → ' . ucfirst( str_replace( '-', ' ', $expected_item ) ),
                        $found
                    );
                }
            } else {
                $this->add_test_result( 
                    ucfirst( str_replace( 'money-quiz-', '', $parent ) ) . ' submenu exists',
                    false,
                    'Parent menu not found'
                );
            }
        }
        
        $this->end_test_group();
    }
    
    /**
     * Test 3: Legacy Redirects
     */
    private function test_legacy_redirects() {
        $this->start_test_group( __( 'Legacy URL Redirects', 'money-quiz' ) );
        
        // Test redirect mappings
        $legacy_mappings = [
            'money_quiz' => 'money-quiz-dashboard-overview',
            'mq_question' => 'money-quiz-quizzes-questions',
            'mq_archetypes' => 'money-quiz-quizzes-archetypes',
            'mq_leads' => 'money-quiz-audience-prospects',
            'mq_results' => 'money-quiz-audience-results'
        ];
        
        foreach ( $legacy_mappings as $old => $new ) {
            // Check if mapping exists
            $mapping_exists = isset( $this->menu_redesign->get_legacy_mappings()[$old] );
            $this->add_test_result( 
                'Legacy mapping: ' . $old . ' → ' . $new,
                $mapping_exists
            );
        }
        
        $this->end_test_group();
    }
    
    /**
     * Test 4: Menu Capabilities
     */
    private function test_menu_capabilities() {
        $this->start_test_group( __( 'Menu Capabilities', 'money-quiz' ) );
        
        global $menu;
        
        // Test capability requirements
        $capability_tests = [
            'money-quiz' => 'read',
            'money-quiz-dashboard' => 'read',
            'money-quiz-quizzes' => 'edit_posts',
            'money-quiz-audience' => 'read',
            'money-quiz-marketing' => 'edit_posts',
            'money-quiz-settings' => 'manage_options'
        ];
        
        foreach ( $menu as $menu_item ) {
            if ( isset( $capability_tests[$menu_item[2]] ) ) {
                $expected_cap = $capability_tests[$menu_item[2]];
                $actual_cap = $menu_item[1];
                $this->add_test_result(
                    $menu_item[2] . ' capability: ' . $expected_cap,
                    $actual_cap === $expected_cap,
                    'Found: ' . $actual_cap
                );
            }
        }
        
        $this->end_test_group();
    }
    
    /**
     * Test 5: Navigation Flow
     */
    private function test_navigation_flow() {
        $this->start_test_group( __( 'Navigation Flow', 'money-quiz' ) );
        
        // Test page generation
        $test_pages = [
            'money-quiz-dashboard-overview' => 'render_dashboard_overview',
            'money-quiz-quizzes-all' => 'render_quizzes_all',
            'money-quiz-audience-results' => 'render_audience_results',
            'money-quiz-marketing-cta' => 'render_marketing_cta',
            'money-quiz-settings-general' => 'render_settings_general'
        ];
        
        foreach ( $test_pages as $page => $method ) {
            $method_exists = method_exists( $this->menu_redesign, $method );
            $this->add_test_result(
                'Render method exists: ' . $method,
                $method_exists
            );
        }
        
        // Test template files
        $template_files = [
            'dashboard-overview.php',
            'quizzes-all.php',
            'audience-results.php',
            'marketing-cta.php',
            'settings-general.php'
        ];
        
        $template_dir = plugin_dir_path( __FILE__ ) . 'templates/';
        foreach ( $template_files as $template ) {
            $file_exists = file_exists( $template_dir . $template );
            $this->add_test_result(
                'Template file exists: ' . $template,
                $file_exists
            );
        }
        
        $this->end_test_group();
    }
    
    /**
     * Test 6: Quick Access Menu
     */
    private function test_quick_access_menu() {
        $this->start_test_group( __( 'Quick Access Admin Bar Menu', 'money-quiz' ) );
        
        global $wp_admin_bar;
        
        // Check if admin bar menu method exists
        $method_exists = method_exists( $this->menu_redesign, 'add_quick_access_menu' );
        $this->add_test_result( 'Quick access menu method exists', $method_exists );
        
        // Check expected menu items
        $expected_items = [
            'Create Quiz',
            'View Results',
            'Manage Leads',
            'Settings'
        ];
        
        // Note: Admin bar items would need to be tested in a full WordPress environment
        $this->add_test_result( 
            'Admin bar integration ready',
            $method_exists,
            'Full test requires admin bar context'
        );
        
        $this->end_test_group();
    }
    
    /**
     * Test 7: Body Classes
     */
    private function test_body_classes() {
        $this->start_test_group( __( 'Admin Body Classes', 'money-quiz' ) );
        
        // Test body class method
        $method_exists = method_exists( $this->menu_redesign, 'add_menu_body_class' );
        $this->add_test_result( 'Body class method exists', $method_exists );
        
        // Test expected classes for different pages
        $test_cases = [
            'money-quiz-dashboard-overview' => 'mq-section-dashboard',
            'money-quiz-quizzes-all' => 'mq-section-quizzes',
            'money-quiz-audience-results' => 'mq-section-audience'
        ];
        
        foreach ( $test_cases as $page => $expected_class ) {
            // Simulate page context
            $_GET['page'] = $page;
            $classes = $this->menu_redesign->add_menu_body_class( '' );
            $has_class = strpos( $classes, $expected_class ) !== false;
            $this->add_test_result(
                'Body class for ' . $page,
                $has_class,
                'Expected: ' . $expected_class
            );
        }
        
        unset( $_GET['page'] );
        
        $this->end_test_group();
    }
    
    /**
     * Test 8: Asset Loading
     */
    private function test_asset_loading() {
        $this->start_test_group( __( 'Asset Loading', 'money-quiz' ) );
        
        // Check CSS files
        $css_files = [
            'menu-redesign.css',
            'menu-redesign.min.css',
            'migration-notice.css'
        ];
        
        $css_dir = MONEY_QUIZ_PLUGIN_PATH . 'assets/css/';
        foreach ( $css_files as $file ) {
            $exists = file_exists( $css_dir . $file );
            $this->add_test_result( 'CSS file exists: ' . $file, $exists );
        }
        
        // Check JS files
        $js_files = [
            'menu-redesign.js',
            'menu-redesign.min.js',
            'migration-notice.js'
        ];
        
        $js_dir = MONEY_QUIZ_PLUGIN_PATH . 'assets/js/';
        foreach ( $js_files as $file ) {
            $exists = file_exists( $js_dir . $file );
            $this->add_test_result( 'JS file exists: ' . $file, $exists );
        }
        
        // Check enqueue method
        $method_exists = method_exists( $this->menu_redesign, 'enqueue_menu_styles' );
        $this->add_test_result( 'Asset enqueue method exists', $method_exists );
        
        $this->end_test_group();
    }
    
    /**
     * Helper: Start test group
     */
    private function start_test_group( $title ) {
        echo '<div class="mq-test-group">';
        echo '<h2>' . esc_html( $title ) . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Test</th><th>Status</th><th>Details</th></tr></thead>';
        echo '<tbody>';
    }
    
    /**
     * Helper: End test group
     */
    private function end_test_group() {
        echo '</tbody></table></div>';
    }
    
    /**
     * Helper: Add test result
     */
    private function add_test_result( $test_name, $passed, $details = '' ) {
        $this->test_results[] = $passed;
        
        $status_icon = $passed ? '✅' : '❌';
        $status_class = $passed ? 'passed' : 'failed';
        
        echo '<tr class="test-' . $status_class . '">';
        echo '<td>' . esc_html( $test_name ) . '</td>';
        echo '<td>' . $status_icon . ' ' . ( $passed ? 'Passed' : 'Failed' ) . '</td>';
        echo '<td>' . esc_html( $details ) . '</td>';
        echo '</tr>';
    }
    
    /**
     * Display test summary
     */
    private function display_test_summary() {
        $total_tests = count( $this->test_results );
        $passed_tests = array_sum( $this->test_results );
        $failed_tests = $total_tests - $passed_tests;
        $pass_rate = $total_tests > 0 ? round( ( $passed_tests / $total_tests ) * 100, 2 ) : 0;
        
        $summary_class = $pass_rate === 100 ? 'notice-success' : ( $pass_rate >= 80 ? 'notice-warning' : 'notice-error' );
        
        ?>
        <div class="notice <?php echo $summary_class; ?> mq-test-summary">
            <h2><?php _e( 'Test Summary', 'money-quiz' ); ?></h2>
            <p>
                <strong><?php _e( 'Total Tests:', 'money-quiz' ); ?></strong> <?php echo $total_tests; ?><br>
                <strong><?php _e( 'Passed:', 'money-quiz' ); ?></strong> <?php echo $passed_tests; ?><br>
                <strong><?php _e( 'Failed:', 'money-quiz' ); ?></strong> <?php echo $failed_tests; ?><br>
                <strong><?php _e( 'Pass Rate:', 'money-quiz' ); ?></strong> <?php echo $pass_rate; ?>%
            </p>
            
            <?php if ( $pass_rate === 100 ) : ?>
                <p class="description"><?php _e( '🎉 All navigation tests passed! The menu system is ready for use.', 'money-quiz' ); ?></p>
            <?php elseif ( $pass_rate >= 80 ) : ?>
                <p class="description"><?php _e( '⚠️ Most tests passed, but some issues need attention.', 'money-quiz' ); ?></p>
            <?php else : ?>
                <p class="description"><?php _e( '❌ Multiple tests failed. Please review and fix the issues.', 'money-quiz' ); ?></p>
            <?php endif; ?>
        </div>
        
        <style>
            .mq-test-group {
                margin: 20px 0;
                background: #fff;
                padding: 20px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
            }
            .mq-test-group h2 {
                margin-top: 0;
            }
            .test-passed {
                background-color: #f0f8f0;
            }
            .test-failed {
                background-color: #fef0f0;
            }
            .mq-test-summary {
                margin-top: 30px;
                padding: 20px;
            }
            .mq-test-summary h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Get legacy mappings (helper method)
     */
    public function get_legacy_mappings() {
        // Return the legacy mappings for testing
        return [
            'money_quiz' => 'dashboard/overview',
            'mq_question' => 'quizzes/questions',
            'mq_archetypes' => 'quizzes/archetypes',
            'mq_leads' => 'audience/prospects',
            'mq_results' => 'audience/results',
            // ... other mappings
        ];
    }
}

// Add test page to menu
add_action( 'admin_menu', function() {
    add_submenu_page(
        'money-quiz-settings',
        __( 'Menu Navigation Tests', 'money-quiz' ),
        __( 'Navigation Tests', 'money-quiz' ),
        'manage_options',
        'money-quiz-navigation-tests',
        function() {
            $tester = new MoneyQuiz_Menu_Navigation_Test();
            $tester->run_tests();
        }
    );
}, 100 );

// Also accessible via direct URL for emergency testing
if ( isset( $_GET['page'] ) && $_GET['page'] === 'money-quiz-tests' ) {
    add_action( 'admin_init', function() {
        $tester = new MoneyQuiz_Menu_Navigation_Test();
        $tester->run_tests();
        exit;
    });
}