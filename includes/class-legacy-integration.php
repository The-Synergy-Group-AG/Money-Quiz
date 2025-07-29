<?php
/**
 * Legacy Integration Manager
 * 
 * Coordinates all legacy code integration and safety measures
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Integration;

class Legacy_Integration {
    
    /**
     * @var bool Integration enabled
     */
    private $enabled = true;
    
    /**
     * @var array Integration status
     */
    private $status = [];
    
    /**
     * @var array Performance metrics
     */
    private $metrics = [];
    
    /**
     * Initialize legacy integration
     */
    public function init() {
        // Check if integration should be enabled
        $this->enabled = $this->should_enable_integration();
        
        if ( ! $this->enabled ) {
            return;
        }
        
        // Load integration components
        $this->load_components();
        
        // Apply patches
        $this->apply_patches();
        
        // Setup monitoring
        $this->setup_monitoring();
        
        // Hook into WordPress
        $this->setup_hooks();
    }
    
    /**
     * Check if integration should be enabled
     */
    private function should_enable_integration() {
        // Check for override constant
        if ( defined( 'MONEY_QUIZ_DISABLE_INTEGRATION' ) && MONEY_QUIZ_DISABLE_INTEGRATION ) {
            $this->status['disabled_reason'] = 'Disabled by constant';
            return false;
        }
        
        // Check if modern architecture is fully active
        if ( defined( 'MONEY_QUIZ_MODERN_ONLY' ) && MONEY_QUIZ_MODERN_ONLY ) {
            $this->status['disabled_reason'] = 'Modern architecture only mode';
            return false;
        }
        
        // Check if legacy code exists
        if ( ! $this->legacy_code_exists() ) {
            $this->status['disabled_reason'] = 'No legacy code found';
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if legacy code exists
     */
    private function legacy_code_exists() {
        $legacy_files = [
            'moneyquiz.php',
            'quiz.moneycoach.php',
            'prospects.admin.php'
        ];
        
        foreach ( $legacy_files as $file ) {
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . $file ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Load integration components
     */
    private function load_components() {
        $components = [
            'legacy-integration-loader.php',
            'class-legacy-db-wrapper.php',
            'class-legacy-input-sanitizer.php',
            'class-legacy-function-router.php',
            'class-version-manager.php',
            'class-enhanced-error-logger.php',
            'admin/class-integration-settings.php',
            'admin/class-integration-notice.php'
        ];
        
        foreach ( $components as $component ) {
            $file = MONEY_QUIZ_PLUGIN_DIR . 'includes/' . $component;
            if ( file_exists( $file ) ) {
                require_once $file;
                $this->status['loaded_components'][] = $component;
            }
        }
    }
    
    /**
     * Apply security patches
     */
    private function apply_patches() {
        $patch_dir = MONEY_QUIZ_PLUGIN_DIR . 'includes/legacy-patches/';
        
        if ( ! is_dir( $patch_dir ) ) {
            return;
        }
        
        $patches = [
            'patch-quiz-submission.php' => 'quiz_submission',
            'patch-admin-security.php' => 'admin_security'
        ];
        
        foreach ( $patches as $file => $name ) {
            $patch_file = $patch_dir . $file;
            if ( file_exists( $patch_file ) ) {
                require_once $patch_file;
                $this->status['applied_patches'][] = $name;
            }
        }
    }
    
    /**
     * Setup monitoring
     */
    private function setup_monitoring() {
        // Monitor performance
        add_action( 'init', [ $this, 'start_performance_monitoring' ], 1 );
        add_action( 'shutdown', [ $this, 'end_performance_monitoring' ], 999 );
        
        // Monitor errors
        if ( defined( 'MONEY_QUIZ_ERROR_LOGGING' ) && MONEY_QUIZ_ERROR_LOGGING ) {
            new \MoneyQuiz\Debug\Enhanced_Error_Logger();
        }
        
        // Monitor security events
        add_action( 'money_quiz_security_event', [ $this, 'log_security_event' ], 10, 2 );
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Admin notices
        add_action( 'admin_notices', [ $this, 'show_integration_notices' ] );
        
        // Health check
        add_filter( 'site_status_tests', [ $this, 'add_health_checks' ] );
        
        // Admin bar indicator
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_indicator' ], 999 );
        
        // AJAX monitoring
        add_action( 'wp_ajax_mq_integration_status', [ $this, 'ajax_get_status' ] );
    }
    
    /**
     * Start performance monitoring
     */
    public function start_performance_monitoring() {
        $this->metrics['start_time'] = microtime( true );
        $this->metrics['start_memory'] = memory_get_usage( true );
        $this->metrics['start_queries'] = get_num_queries();
    }
    
    /**
     * End performance monitoring
     */
    public function end_performance_monitoring() {
        if ( ! isset( $this->metrics['start_time'] ) ) {
            return;
        }
        
        $this->metrics['end_time'] = microtime( true );
        $this->metrics['end_memory'] = memory_get_usage( true );
        $this->metrics['end_queries'] = get_num_queries();
        
        $this->metrics['execution_time'] = $this->metrics['end_time'] - $this->metrics['start_time'];
        $this->metrics['memory_used'] = $this->metrics['end_memory'] - $this->metrics['start_memory'];
        $this->metrics['queries_run'] = $this->metrics['end_queries'] - $this->metrics['start_queries'];
        
        // Store metrics
        $this->store_metrics();
    }
    
    /**
     * Store performance metrics
     */
    private function store_metrics() {
        $metrics_history = get_option( 'money_quiz_integration_metrics', [] );
        
        $metrics_history[] = [
            'timestamp' => current_time( 'mysql' ),
            'execution_time' => round( $this->metrics['execution_time'], 4 ),
            'memory_used' => $this->format_bytes( $this->metrics['memory_used'] ),
            'queries_run' => $this->metrics['queries_run'],
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        // Keep only last 100 entries
        if ( count( $metrics_history ) > 100 ) {
            $metrics_history = array_slice( $metrics_history, -100 );
        }
        
        update_option( 'money_quiz_integration_metrics', $metrics_history );
    }
    
    /**
     * Show integration notices
     */
    public function show_integration_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Check for issues
        $issues = $this->check_integration_issues();
        
        if ( ! empty( $issues ) ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __( 'Money Quiz Integration Notice:', 'money-quiz' ) . '</strong></p>';
            echo '<ul>';
            foreach ( $issues as $issue ) {
                echo '<li>' . esc_html( $issue ) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Check for integration issues
     */
    private function check_integration_issues() {
        $issues = [];
        
        // Check database queries
        if ( class_exists( '\MoneyQuiz\Legacy\Legacy_DB_Wrapper' ) ) {
            $db_wrapper = mq_safe_db();
            $query_log = $db_wrapper->get_query_log();
            
            $unsafe_count = 0;
            foreach ( $query_log as $query ) {
                if ( strpos( $query['query'], '$' ) !== false ) {
                    $unsafe_count++;
                }
            }
            
            if ( $unsafe_count > 0 ) {
                $issues[] = sprintf(
                    __( '%d potentially unsafe database queries detected. Run migration tool.', 'money-quiz' ),
                    $unsafe_count
                );
            }
        }
        
        // Check error rate
        if ( class_exists( '\MoneyQuiz\Debug\Enhanced_Error_Logger' ) ) {
            $logger = new \MoneyQuiz\Debug\Enhanced_Error_Logger();
            $recent_errors = $logger->get_recent_errors( 10 );
            
            if ( count( $recent_errors ) > 5 ) {
                $issues[] = __( 'High error rate detected. Check error logs.', 'money-quiz' );
            }
        }
        
        // Check performance
        $metrics = get_option( 'money_quiz_integration_metrics', [] );
        if ( ! empty( $metrics ) ) {
            $recent_metrics = array_slice( $metrics, -10 );
            $avg_time = array_sum( array_column( $recent_metrics, 'execution_time' ) ) / count( $recent_metrics );
            
            if ( $avg_time > 2.0 ) {
                $issues[] = sprintf(
                    __( 'Performance issue: Average page load time is %.2fs', 'money-quiz' ),
                    $avg_time
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Add health checks
     */
    public function add_health_checks( $tests ) {
        $tests['direct']['money_quiz_integration'] = [
            'label' => __( 'Money Quiz Integration', 'money-quiz' ),
            'test' => [ $this, 'health_check' ]
        ];
        
        return $tests;
    }
    
    /**
     * Run health check
     */
    public function health_check() {
        $result = [
            'label' => __( 'Money Quiz Integration is functioning properly', 'money-quiz' ),
            'status' => 'good',
            'badge' => [
                'label' => __( 'Money Quiz', 'money-quiz' ),
                'color' => 'green'
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __( 'All Money Quiz integration components are working correctly.', 'money-quiz' )
            ),
            'actions' => '',
            'test' => 'money_quiz_integration'
        ];
        
        $issues = $this->check_integration_issues();
        
        if ( ! empty( $issues ) ) {
            $result['status'] = 'recommended';
            $result['label'] = __( 'Money Quiz Integration has some issues', 'money-quiz' );
            $result['badge']['color'] = 'orange';
            $result['description'] = '<p>' . implode( '</p><p>', array_map( 'esc_html', $issues ) ) . '</p>';
            $result['actions'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'admin.php?page=moneyquiz-settings&tab=integration' ),
                __( 'View Integration Settings', 'money-quiz' )
            );
        }
        
        return $result;
    }
    
    /**
     * Add admin bar indicator
     */
    public function add_admin_bar_indicator( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $issues = $this->check_integration_issues();
        $color = empty( $issues ) ? '#46b450' : '#ffb900';
        
        $wp_admin_bar->add_node( [
            'id' => 'money-quiz-integration',
            'title' => '<span class="ab-icon dashicons dashicons-shield" style="color: ' . $color . ';"></span>' . 
                      __( 'MQ Integration', 'money-quiz' ),
            'href' => admin_url( 'admin.php?page=moneyquiz-settings&tab=integration' ),
            'meta' => [
                'title' => empty( $issues ) ? 
                    __( 'Money Quiz Integration: All systems operational', 'money-quiz' ) :
                    __( 'Money Quiz Integration: Issues detected', 'money-quiz' )
            ]
        ] );
    }
    
    /**
     * AJAX handler for status
     */
    public function ajax_get_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        wp_send_json_success( [
            'enabled' => $this->enabled,
            'status' => $this->status,
            'metrics' => $this->metrics,
            'issues' => $this->check_integration_issues()
        ] );
    }
    
    /**
     * Log security event
     */
    public function log_security_event( $event_type, $data ) {
        $log = get_option( 'money_quiz_security_log', [] );
        
        $log[] = [
            'timestamp' => current_time( 'mysql' ),
            'event_type' => $event_type,
            'data' => $data,
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Keep only last 500 entries
        if ( count( $log ) > 500 ) {
            $log = array_slice( $log, -500 );
        }
        
        update_option( 'money_quiz_security_log', $log );
    }
    
    /**
     * Format bytes
     */
    private function format_bytes( $bytes, $precision = 2 ) {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );
        
        $bytes /= pow( 1024, $pow );
        
        return round( $bytes, $precision ) . ' ' . $units[ $pow ];
    }
    
    /**
     * Get instance
     */
    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }
}

// Initialize integration
add_action( 'plugins_loaded', function() {
    if ( defined( 'MONEY_QUIZ_PLUGIN_FILE' ) ) {
        Legacy_Integration::instance()->init();
    }
}, 15 ); // After plugin loads but before most other actions