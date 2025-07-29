<?php
/**
 * Integration Settings Page
 * 
 * Provides UI for managing integration features and monitoring
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Admin;

class Integration_Settings {
    
    /**
     * Initialize settings
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mq_save_integration_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_mq_get_integration_status', [ $this, 'ajax_get_status' ] );
        add_action( 'wp_ajax_mq_run_migration_tool', [ $this, 'ajax_run_migration' ] );
    }
    
    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'moneyquiz',
            __( 'Integration Settings', 'money-quiz' ),
            __( 'Integration', 'money-quiz' ),
            'manage_options',
            'moneyquiz-integration',
            [ $this, 'render_page' ]
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets( $hook ) {
        if ( 'money-quiz_page_moneyquiz-integration' !== $hook ) {
            return;
        }
        
        wp_enqueue_script(
            'mq-integration-settings',
            MONEY_QUIZ_PLUGIN_URL . 'assets/js/integration-settings.js',
            [ 'jquery', 'wp-util' ],
            MONEY_QUIZ_VERSION,
            true
        );
        
        wp_localize_script( 'mq-integration-settings', 'mqIntegration', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mq_integration_settings' ),
            'strings' => [
                'saving' => __( 'Saving...', 'money-quiz' ),
                'saved' => __( 'Settings saved successfully!', 'money-quiz' ),
                'error' => __( 'Error saving settings. Please try again.', 'money-quiz' ),
                'confirm_migration' => __( 'Are you sure you want to run the database migration? This will modify your database queries.', 'money-quiz' ),
                'migration_complete' => __( 'Migration completed successfully!', 'money-quiz' ),
                'migration_error' => __( 'Migration failed. Check the logs for details.', 'money-quiz' )
            ]
        ] );
        
        wp_enqueue_style(
            'mq-integration-settings',
            MONEY_QUIZ_PLUGIN_URL . 'assets/css/integration-settings.css',
            [],
            MONEY_QUIZ_VERSION
        );
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Get current settings
        $settings = $this->get_settings();
        $status = $this->get_integration_status();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="mq-integration-dashboard">
                <!-- Status Overview -->
                <div class="mq-status-card">
                    <h2><?php _e( 'Integration Status', 'money-quiz' ); ?></h2>
                    <div class="mq-status-grid">
                        <div class="mq-status-item">
                            <span class="mq-status-label"><?php _e( 'Overall Health', 'money-quiz' ); ?></span>
                            <span class="mq-status-value <?php echo esc_attr( $status['health_class'] ); ?>">
                                <?php echo esc_html( $status['health_text'] ); ?>
                            </span>
                        </div>
                        <div class="mq-status-item">
                            <span class="mq-status-label"><?php _e( 'Safe Queries', 'money-quiz' ); ?></span>
                            <span class="mq-status-value"><?php echo esc_html( $status['safe_queries'] ); ?>%</span>
                        </div>
                        <div class="mq-status-item">
                            <span class="mq-status-label"><?php _e( 'Error Rate', 'money-quiz' ); ?></span>
                            <span class="mq-status-value"><?php echo esc_html( $status['error_rate'] ); ?></span>
                        </div>
                        <div class="mq-status-item">
                            <span class="mq-status-label"><?php _e( 'Performance', 'money-quiz' ); ?></span>
                            <span class="mq-status-value"><?php echo esc_html( $status['avg_load_time'] ); ?>s</span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature Flags -->
                <div class="mq-settings-card">
                    <h2><?php _e( 'Feature Flags', 'money-quiz' ); ?></h2>
                    <form id="mq-integration-settings-form">
                        <?php wp_nonce_field( 'mq_integration_settings' ); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="modern_rollout"><?php _e( 'Modern Implementation Rollout', 'money-quiz' ); ?></label>
                                </th>
                                <td>
                                    <input type="range" id="modern_rollout" name="modern_rollout" 
                                           min="0" max="100" step="10" 
                                           value="<?php echo esc_attr( $settings['modern_rollout'] ); ?>">
                                    <span id="rollout_value"><?php echo esc_html( $settings['modern_rollout'] ); ?>%</span>
                                    <p class="description">
                                        <?php _e( 'Percentage of requests that will use modern implementations. Start low and increase gradually.', 'money-quiz' ); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e( 'Safety Features', 'money-quiz' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="enable_query_protection" value="1" 
                                                   <?php checked( $settings['enable_query_protection'] ); ?>>
                                            <?php _e( 'Enable database query protection', 'money-quiz' ); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="enable_input_sanitization" value="1" 
                                                   <?php checked( $settings['enable_input_sanitization'] ); ?>>
                                            <?php _e( 'Enable automatic input sanitization', 'money-quiz' ); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="enable_csrf_protection" value="1" 
                                                   <?php checked( $settings['enable_csrf_protection'] ); ?>>
                                            <?php _e( 'Enable CSRF protection', 'money-quiz' ); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="enable_error_logging" value="1" 
                                                   <?php checked( $settings['enable_error_logging'] ); ?>>
                                            <?php _e( 'Enable enhanced error logging', 'money-quiz' ); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e( 'Function Routing', 'money-quiz' ); ?></th>
                                <td>
                                    <div class="mq-function-flags">
                                        <?php foreach ( $this->get_available_functions() as $func => $info ) : ?>
                                        <label class="mq-function-toggle">
                                            <input type="checkbox" name="function_flags[<?php echo esc_attr( $func ); ?>]" 
                                                   value="1" <?php checked( $settings['function_flags'][ $func ] ?? false ); ?>>
                                            <span class="mq-function-name"><?php echo esc_html( $func ); ?></span>
                                            <span class="mq-function-risk mq-risk-<?php echo esc_attr( $info['risk'] ); ?>">
                                                <?php echo esc_html( $info['risk'] ); ?> risk
                                            </span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e( 'Save Settings', 'money-quiz' ); ?>
                            </button>
                        </p>
                    </form>
                </div>
                
                <!-- Migration Tools -->
                <div class="mq-tools-card">
                    <h2><?php _e( 'Migration Tools', 'money-quiz' ); ?></h2>
                    
                    <div class="mq-tool-item">
                        <h3><?php _e( 'Database Query Migration', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'Automatically migrate unsafe database queries to use prepared statements.', 'money-quiz' ); ?></p>
                        <button type="button" class="button" id="mq-run-migration-dry">
                            <?php _e( 'Run Dry Run', 'money-quiz' ); ?>
                        </button>
                        <button type="button" class="button button-primary" id="mq-run-migration">
                            <?php _e( 'Run Migration', 'money-quiz' ); ?>
                        </button>
                        <div id="migration-output" class="mq-output"></div>
                    </div>
                    
                    <div class="mq-tool-item">
                        <h3><?php _e( 'Error Log Viewer', 'money-quiz' ); ?></h3>
                        <p><?php _e( 'View recent errors and security events.', 'money-quiz' ); ?></p>
                        <a href="<?php echo admin_url( 'admin.php?page=moneyquiz-logs' ); ?>" class="button">
                            <?php _e( 'View Logs', 'money-quiz' ); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div class="mq-metrics-card">
                    <h2><?php _e( 'Performance Metrics', 'money-quiz' ); ?></h2>
                    <div id="mq-metrics-chart"></div>
                    <table class="mq-metrics-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'Metric', 'money-quiz' ); ?></th>
                                <th><?php _e( 'Current', 'money-quiz' ); ?></th>
                                <th><?php _e( '24h Avg', 'money-quiz' ); ?></th>
                                <th><?php _e( 'Change', 'money-quiz' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $status['metrics'] as $metric => $data ) : ?>
                            <tr>
                                <td><?php echo esc_html( $data['label'] ); ?></td>
                                <td><?php echo esc_html( $data['current'] ); ?></td>
                                <td><?php echo esc_html( $data['avg'] ); ?></td>
                                <td class="<?php echo $data['change'] > 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo esc_html( $data['change'] ); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get current settings
     */
    private function get_settings() {
        return [
            'modern_rollout' => get_option( 'money_quiz_modern_rollout', 10 ),
            'enable_query_protection' => get_option( 'money_quiz_enable_query_protection', true ),
            'enable_input_sanitization' => get_option( 'money_quiz_enable_input_sanitization', true ),
            'enable_csrf_protection' => get_option( 'money_quiz_enable_csrf_protection', true ),
            'enable_error_logging' => defined( 'MONEY_QUIZ_ERROR_LOGGING' ) && MONEY_QUIZ_ERROR_LOGGING,
            'function_flags' => get_option( 'money_quiz_feature_flags', [] )
        ];
    }
    
    /**
     * Get integration status
     */
    private function get_integration_status() {
        $issues = [];
        if ( class_exists( '\MoneyQuiz\Integration\Legacy_Integration' ) ) {
            $integration = \MoneyQuiz\Integration\Legacy_Integration::instance();
            $issues = $integration->check_integration_issues();
        }
        
        // Calculate metrics
        $metrics = get_option( 'money_quiz_integration_metrics', [] );
        $recent = array_slice( $metrics, -10 );
        $avg_time = ! empty( $recent ) ? 
            array_sum( array_column( $recent, 'execution_time' ) ) / count( $recent ) : 0;
        
        // Calculate safe query percentage
        $safe_queries = 0;
        $total_queries = 0;
        if ( class_exists( '\MoneyQuiz\Legacy\Legacy_DB_Wrapper' ) ) {
            $db_wrapper = mq_safe_db();
            $query_log = $db_wrapper->get_query_log();
            $total_queries = count( $query_log );
            foreach ( $query_log as $query ) {
                if ( strpos( $query['query'], '%' ) !== false ) {
                    $safe_queries++;
                }
            }
        }
        
        $safe_percentage = $total_queries > 0 ? round( ( $safe_queries / $total_queries ) * 100 ) : 100;
        
        // Get error rate
        $error_rate = '0/hour';
        if ( class_exists( '\MoneyQuiz\Debug\Enhanced_Error_Logger' ) ) {
            $logger = new \MoneyQuiz\Debug\Enhanced_Error_Logger();
            $stats = $logger->get_stats( 1 );
            $total_errors = 0;
            foreach ( $stats as $day_stats ) {
                $total_errors += array_sum( $day_stats );
            }
            $error_rate = $total_errors . '/day';
        }
        
        return [
            'health_class' => empty( $issues ) ? 'healthy' : 'warning',
            'health_text' => empty( $issues ) ? __( 'Healthy', 'money-quiz' ) : __( 'Issues Detected', 'money-quiz' ),
            'safe_queries' => $safe_percentage,
            'error_rate' => $error_rate,
            'avg_load_time' => round( $avg_time, 2 ),
            'metrics' => [
                'load_time' => [
                    'label' => __( 'Page Load Time', 'money-quiz' ),
                    'current' => round( $avg_time, 2 ) . 's',
                    'avg' => round( $avg_time, 2 ) . 's',
                    'change' => 0
                ],
                'memory_usage' => [
                    'label' => __( 'Memory Usage', 'money-quiz' ),
                    'current' => $this->format_bytes( memory_get_usage( true ) ),
                    'avg' => $this->format_bytes( memory_get_usage( true ) ),
                    'change' => 0
                ],
                'query_count' => [
                    'label' => __( 'Database Queries', 'money-quiz' ),
                    'current' => get_num_queries(),
                    'avg' => get_num_queries(),
                    'change' => 0
                ]
            ]
        ];
    }
    
    /**
     * Get available functions for routing
     */
    private function get_available_functions() {
        return [
            'mq_get_quiz_questions' => [ 'risk' => 'low' ],
            'mq_save_quiz_result' => [ 'risk' => 'medium' ],
            'mq_calculate_archetype' => [ 'risk' => 'low' ],
            'mq_send_result_email' => [ 'risk' => 'medium' ],
            'mq_get_prospects' => [ 'risk' => 'low' ],
            'mq_get_setting' => [ 'risk' => 'low' ]
        ];
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'mq_integration_settings' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        // Save settings
        update_option( 'money_quiz_modern_rollout', absint( $_POST['modern_rollout'] ?? 10 ) );
        update_option( 'money_quiz_enable_query_protection', ! empty( $_POST['enable_query_protection'] ) );
        update_option( 'money_quiz_enable_input_sanitization', ! empty( $_POST['enable_input_sanitization'] ) );
        update_option( 'money_quiz_enable_csrf_protection', ! empty( $_POST['enable_csrf_protection'] ) );
        
        // Save function flags
        $function_flags = [];
        if ( ! empty( $_POST['function_flags'] ) && is_array( $_POST['function_flags'] ) ) {
            foreach ( $_POST['function_flags'] as $func => $enabled ) {
                $function_flags[ sanitize_key( $func ) ] = ! empty( $enabled );
            }
        }
        update_option( 'money_quiz_feature_flags', $function_flags );
        
        wp_send_json_success( [ 'message' => __( 'Settings saved successfully!', 'money-quiz' ) ] );
    }
    
    /**
     * AJAX: Get status
     */
    public function ajax_get_status() {
        check_ajax_referer( 'mq_integration_settings' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        wp_send_json_success( $this->get_integration_status() );
    }
    
    /**
     * AJAX: Run migration
     */
    public function ajax_run_migration() {
        check_ajax_referer( 'mq_integration_settings' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $dry_run = ! empty( $_POST['dry_run'] );
        $migration_script = MONEY_QUIZ_PLUGIN_DIR . 'tools/migrate-database-queries.php';
        
        if ( ! file_exists( $migration_script ) ) {
            wp_send_json_error( 'Migration script not found' );
        }
        
        // Run migration script
        $command = sprintf(
            'php %s %s 2>&1',
            escapeshellarg( $migration_script ),
            $dry_run ? '--dry-run' : ''
        );
        
        $output = shell_exec( $command );
        
        wp_send_json_success( [
            'output' => $output,
            'dry_run' => $dry_run
        ] );
    }
    
    /**
     * Format bytes
     */
    private function format_bytes( $bytes, $precision = 2 ) {
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );
        
        $bytes /= pow( 1024, $pow );
        
        return round( $bytes, $precision ) . ' ' . $units[ $pow ];
    }
}

// Initialize
add_action( 'init', function() {
    $integration_settings = new Integration_Settings();
    $integration_settings->init();
} );