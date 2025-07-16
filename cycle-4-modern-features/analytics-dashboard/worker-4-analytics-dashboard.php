<?php
/**
 * Money Quiz Plugin - Analytics Dashboard
 * Worker 4: Analytics Dashboard UI and Controllers
 * 
 * Provides the admin dashboard interface and controllers for displaying
 * analytics data with interactive charts and visualizations.
 * 
 * @package MoneyQuiz
 * @subpackage Admin
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin;

use MoneyQuiz\Services\AnalyticsService;
use MoneyQuiz\Utilities\ResponseUtil;
use MoneyQuiz\Utilities\SecurityUtil;
use MoneyQuiz\Utilities\DateUtil;

/**
 * Analytics Dashboard Controller
 * 
 * Handles analytics dashboard display and AJAX requests
 */
class AnalyticsDashboardController {
    
    /**
     * Analytics service
     * 
     * @var AnalyticsService
     */
    protected $analytics_service;
    
    /**
     * Constructor
     * 
     * @param AnalyticsService $analytics_service
     */
    public function __construct( AnalyticsService $analytics_service ) {
        $this->analytics_service = $analytics_service;
        
        // Register hooks
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_money_quiz_get_analytics', array( $this, 'ajax_get_analytics' ) );
        add_action( 'wp_ajax_money_quiz_export_analytics', array( $this, 'ajax_export_analytics' ) );
        add_action( 'wp_ajax_money_quiz_refresh_widget', array( $this, 'ajax_refresh_widget' ) );
    }
    
    /**
     * Add analytics menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'money-quiz',
            __( 'Analytics Dashboard', 'money-quiz' ),
            __( 'Analytics', 'money-quiz' ),
            'manage_options',
            'money-quiz-analytics',
            array( $this, 'render_dashboard' )
        );
    }
    
    /**
     * Enqueue dashboard assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'money-quiz_page_money-quiz-analytics' ) {
            return;
        }
        
        // Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            array(),
            '4.4.0',
            true
        );
        
        // Date range picker
        wp_enqueue_script(
            'daterangepicker',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
            array( 'jquery', 'moment' ),
            '3.1.0',
            true
        );
        
        wp_enqueue_style(
            'daterangepicker',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css',
            array(),
            '3.1.0'
        );
        
        // Custom analytics scripts
        wp_enqueue_script(
            'money-quiz-analytics',
            MONEY_QUIZ_PLUGIN_URL . 'admin/js/analytics-dashboard.js',
            array( 'jquery', 'chartjs', 'daterangepicker' ),
            MONEY_QUIZ_VERSION,
            true
        );
        
        // Custom analytics styles
        wp_enqueue_style(
            'money-quiz-analytics',
            MONEY_QUIZ_PLUGIN_URL . 'admin/css/analytics-dashboard.css',
            array(),
            MONEY_QUIZ_VERSION
        );
        
        // Localize script
        wp_localize_script( 'money-quiz-analytics', 'moneyQuizAnalytics', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'money_quiz_analytics' ),
            'i18n' => array(
                'loading' => __( 'Loading...', 'money-quiz' ),
                'error' => __( 'Error loading data', 'money-quiz' ),
                'noData' => __( 'No data available', 'money-quiz' ),
                'exportSuccess' => __( 'Export completed successfully', 'money-quiz' ),
                'exportError' => __( 'Export failed', 'money-quiz' )
            ),
            'chartColors' => array(
                'primary' => '#3498db',
                'success' => '#27ae60',
                'warning' => '#f39c12',
                'danger' => '#e74c3c',
                'info' => '#9b59b6'
            )
        ));
    }
    
    /**
     * Render analytics dashboard
     */
    public function render_dashboard() {
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Get initial data
        $overview = $this->analytics_service->get_dashboard_overview();
        
        ?>
        <div class="wrap money-quiz-analytics">
            <h1><?php _e( 'Analytics Dashboard', 'money-quiz' ); ?></h1>
            
            <!-- Date Range Selector -->
            <div class="analytics-toolbar">
                <div class="date-range-selector">
                    <label><?php _e( 'Date Range:', 'money-quiz' ); ?></label>
                    <select id="quick-date-range" class="date-range-quick">
                        <option value="7days"><?php _e( 'Last 7 Days', 'money-quiz' ); ?></option>
                        <option value="30days" selected><?php _e( 'Last 30 Days', 'money-quiz' ); ?></option>
                        <option value="90days"><?php _e( 'Last 90 Days', 'money-quiz' ); ?></option>
                        <option value="12months"><?php _e( 'Last 12 Months', 'money-quiz' ); ?></option>
                        <option value="custom"><?php _e( 'Custom Range', 'money-quiz' ); ?></option>
                    </select>
                    <input type="text" id="custom-date-range" class="date-range-picker" style="display:none;">
                </div>
                
                <div class="analytics-actions">
                    <button class="button" id="refresh-analytics">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e( 'Refresh', 'money-quiz' ); ?>
                    </button>
                    <button class="button" id="export-analytics">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e( 'Export', 'money-quiz' ); ?>
                    </button>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="analytics-summary-cards">
                <?php $this->render_summary_cards( $overview['summary'] ); ?>
            </div>
            
            <!-- Main Charts -->
            <div class="analytics-charts-row">
                <div class="chart-container large">
                    <h3><?php _e( 'Quiz Completions Over Time', 'money-quiz' ); ?></h3>
                    <canvas id="completions-chart"></canvas>
                </div>
                
                <div class="chart-container medium">
                    <h3><?php _e( 'Archetype Distribution', 'money-quiz' ); ?></h3>
                    <canvas id="archetype-chart"></canvas>
                </div>
            </div>
            
            <!-- Conversion Funnel -->
            <div class="analytics-section">
                <h3><?php _e( 'Conversion Funnel', 'money-quiz' ); ?></h3>
                <div class="conversion-funnel">
                    <?php $this->render_conversion_funnel( $overview['conversion_funnel'] ); ?>
                </div>
            </div>
            
            <!-- Engagement Metrics -->
            <div class="analytics-charts-row">
                <div class="chart-container small">
                    <h3><?php _e( 'Average Completion Time', 'money-quiz' ); ?></h3>
                    <div class="metric-display">
                        <span class="metric-value">
                            <?php echo esc_html( $overview['engagement_metrics']['avg_completion_time']['value'] ); ?>
                        </span>
                        <span class="metric-unit">
                            <?php echo esc_html( $overview['engagement_metrics']['avg_completion_time']['unit'] ); ?>
                        </span>
                    </div>
                </div>
                
                <div class="chart-container small">
                    <h3><?php _e( 'Repeat Quiz Rate', 'money-quiz' ); ?></h3>
                    <div class="metric-display">
                        <span class="metric-value">
                            <?php echo esc_html( $overview['engagement_metrics']['repeat_rate'] ); ?>%
                        </span>
                        <span class="metric-unit"><?php _e( 'of users', 'money-quiz' ); ?></span>
                    </div>
                </div>
                
                <div class="chart-container small">
                    <h3><?php _e( 'Response Patterns', 'money-quiz' ); ?></h3>
                    <canvas id="response-patterns-chart"></canvas>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="analytics-section">
                <h3><?php _e( 'Recent Activity', 'money-quiz' ); ?></h3>
                <div class="activity-timeline">
                    <?php $this->render_activity_timeline( $overview['recent_activity'] ); ?>
                </div>
            </div>
            
            <!-- Advanced Analytics Tabs -->
            <div class="analytics-tabs">
                <ul class="tab-nav">
                    <li class="active">
                        <a href="#demographics" data-tab="demographics">
                            <?php _e( 'Demographics', 'money-quiz' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="#behavior" data-tab="behavior">
                            <?php _e( 'Behavior', 'money-quiz' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="#performance" data-tab="performance">
                            <?php _e( 'Performance', 'money-quiz' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="#questions" data-tab="questions">
                            <?php _e( 'Questions', 'money-quiz' ); ?>
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div id="demographics" class="tab-pane active">
                        <!-- Demographics content loaded via AJAX -->
                        <div class="loading-spinner">
                            <span class="spinner is-active"></span>
                        </div>
                    </div>
                    
                    <div id="behavior" class="tab-pane">
                        <!-- Behavior content loaded via AJAX -->
                    </div>
                    
                    <div id="performance" class="tab-pane">
                        <!-- Performance content loaded via AJAX -->
                    </div>
                    
                    <div id="questions" class="tab-pane">
                        <!-- Questions content loaded via AJAX -->
                    </div>
                </div>
            </div>
            
            <!-- Export Modal -->
            <div id="export-modal" class="analytics-modal" style="display:none;">
                <div class="modal-content">
                    <h3><?php _e( 'Export Analytics Data', 'money-quiz' ); ?></h3>
                    
                    <div class="export-options">
                        <label>
                            <input type="radio" name="export_format" value="csv" checked>
                            <?php _e( 'CSV (Excel compatible)', 'money-quiz' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="export_format" value="json">
                            <?php _e( 'JSON (Raw data)', 'money-quiz' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="export_format" value="pdf">
                            <?php _e( 'PDF Report', 'money-quiz' ); ?>
                        </label>
                    </div>
                    
                    <div class="export-sections">
                        <h4><?php _e( 'Include Sections:', 'money-quiz' ); ?></h4>
                        <label>
                            <input type="checkbox" name="export_section[]" value="summary" checked>
                            <?php _e( 'Summary Statistics', 'money-quiz' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="export_section[]" value="trends" checked>
                            <?php _e( 'Trend Data', 'money-quiz' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="export_section[]" value="archetypes" checked>
                            <?php _e( 'Archetype Distribution', 'money-quiz' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="export_section[]" value="funnel">
                            <?php _e( 'Conversion Funnel', 'money-quiz' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="export_section[]" value="engagement">
                            <?php _e( 'Engagement Metrics', 'money-quiz' ); ?>
                        </label>
                    </div>
                    
                    <div class="modal-actions">
                        <button class="button button-primary" id="confirm-export">
                            <?php _e( 'Export', 'money-quiz' ); ?>
                        </button>
                        <button class="button" id="cancel-export">
                            <?php _e( 'Cancel', 'money-quiz' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            // Initialize dashboard with data
            jQuery(document).ready(function($) {
                window.moneyQuizAnalyticsData = <?php echo json_encode( $overview ); ?>;
            });
        </script>
        <?php
    }
    
    /**
     * Render summary cards
     * 
     * @param array $summary Summary data
     */
    protected function render_summary_cards( array $summary ) {
        $cards = array(
            array(
                'title' => __( 'Total Completions', 'money-quiz' ),
                'value' => number_format( $summary['total_completed'] ),
                'change' => $summary['growth']['completions'] ?? 0,
                'icon' => 'chart-line'
            ),
            array(
                'title' => __( 'Conversion Rate', 'money-quiz' ),
                'value' => $summary['conversion_rate'] . '%',
                'change' => $summary['growth']['conversion'] ?? 0,
                'icon' => 'performance'
            ),
            array(
                'title' => __( 'Total Prospects', 'money-quiz' ),
                'value' => number_format( $summary['total_prospects'] ),
                'change' => $summary['growth']['prospects'] ?? 0,
                'icon' => 'groups'
            ),
            array(
                'title' => __( 'Average Score', 'money-quiz' ),
                'value' => $summary['average_score'],
                'change' => $summary['growth']['score'] ?? 0,
                'icon' => 'awards'
            )
        );
        
        foreach ( $cards as $card ) {
            $change_class = $card['change'] > 0 ? 'positive' : ( $card['change'] < 0 ? 'negative' : 'neutral' );
            ?>
            <div class="summary-card">
                <div class="card-icon">
                    <span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>"></span>
                </div>
                <div class="card-content">
                    <h4><?php echo esc_html( $card['title'] ); ?></h4>
                    <div class="card-value"><?php echo esc_html( $card['value'] ); ?></div>
                    <div class="card-change <?php echo esc_attr( $change_class ); ?>">
                        <?php if ( $card['change'] > 0 ) : ?>
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                        <?php elseif ( $card['change'] < 0 ) : ?>
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-minus"></span>
                        <?php endif; ?>
                        <?php echo abs( $card['change'] ); ?>%
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render conversion funnel
     * 
     * @param array $funnel Funnel data
     */
    protected function render_conversion_funnel( array $funnel ) {
        foreach ( $funnel as $index => $stage ) {
            $width = $stage['rate'] . '%';
            ?>
            <div class="funnel-stage">
                <div class="stage-header">
                    <span class="stage-name"><?php echo esc_html( $stage['stage'] ); ?></span>
                    <span class="stage-count"><?php echo number_format( $stage['count'] ); ?></span>
                </div>
                <div class="stage-bar">
                    <div class="stage-fill" style="width: <?php echo esc_attr( $width ); ?>">
                        <span class="stage-rate"><?php echo esc_html( $stage['rate'] ); ?>%</span>
                    </div>
                </div>
                <?php if ( $index < count( $funnel ) - 1 ) : ?>
                    <div class="stage-arrow">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    /**
     * Render activity timeline
     * 
     * @param array $activities Recent activities
     */
    protected function render_activity_timeline( array $activities ) {
        ?>
        <div class="timeline">
            <?php foreach ( $activities as $activity ) : ?>
                <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="activity-description">
                            <?php echo esc_html( $activity['description'] ); ?>
                        </div>
                        <div class="activity-time">
                            <?php echo esc_html( $activity['time'] ); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics() {
        // Verify nonce
        if ( ! SecurityUtil::verify_nonce( $_POST['nonce'] ?? '', 'analytics' ) ) {
            ResponseUtil::error( __( 'Security check failed', 'money-quiz' ), 403 );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            ResponseUtil::error( __( 'Insufficient permissions', 'money-quiz' ), 403 );
        }
        
        $type = sanitize_text_field( $_POST['type'] ?? 'overview' );
        $filters = array(
            'period' => sanitize_text_field( $_POST['period'] ?? '30days' ),
            'start_date' => sanitize_text_field( $_POST['start_date'] ?? '' ),
            'end_date' => sanitize_text_field( $_POST['end_date'] ?? '' )
        );
        
        try {
            switch ( $type ) {
                case 'overview':
                    $data = $this->analytics_service->get_dashboard_overview( $filters );
                    break;
                    
                case 'custom':
                    $config = array(
                        'period' => $filters['period'],
                        'sections' => $_POST['sections'] ?? array(),
                        'filters' => $filters
                    );
                    $data = $this->analytics_service->generate_custom_report( $config );
                    break;
                    
                default:
                    throw new \Exception( __( 'Invalid analytics type', 'money-quiz' ) );
            }
            
            ResponseUtil::success( $data );
            
        } catch ( \Exception $e ) {
            ResponseUtil::error( $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for exporting analytics
     */
    public function ajax_export_analytics() {
        // Verify nonce
        if ( ! SecurityUtil::verify_nonce( $_POST['nonce'] ?? '', 'analytics' ) ) {
            ResponseUtil::error( __( 'Security check failed', 'money-quiz' ), 403 );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            ResponseUtil::error( __( 'Insufficient permissions', 'money-quiz' ), 403 );
        }
        
        $format = sanitize_text_field( $_POST['format'] ?? 'csv' );
        $sections = array_map( 'sanitize_text_field', $_POST['sections'] ?? array() );
        $filters = array(
            'period' => sanitize_text_field( $_POST['period'] ?? '30days' )
        );
        
        try {
            // Get data for selected sections
            $data = array();
            if ( in_array( 'summary', $sections ) ) {
                $overview = $this->analytics_service->get_dashboard_overview( $filters );
                $data['summary'] = $overview['summary'];
            }
            
            if ( in_array( 'trends', $sections ) ) {
                $overview = $overview ?? $this->analytics_service->get_dashboard_overview( $filters );
                $data['trends'] = $overview['trends'];
            }
            
            // Add other sections as needed...
            
            // Export data
            $export_url = $this->analytics_service->export_analytics( $format, $data );
            
            ResponseUtil::success( array(
                'url' => $export_url,
                'filename' => basename( $export_url )
            ));
            
        } catch ( \Exception $e ) {
            ResponseUtil::error( $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for refreshing widgets
     */
    public function ajax_refresh_widget() {
        // Verify nonce
        if ( ! SecurityUtil::verify_nonce( $_POST['nonce'] ?? '', 'analytics' ) ) {
            ResponseUtil::error( __( 'Security check failed', 'money-quiz' ), 403 );
        }
        
        $widget = sanitize_text_field( $_POST['widget'] ?? '' );
        $filters = array(
            'period' => sanitize_text_field( $_POST['period'] ?? '30days' )
        );
        
        try {
            $data = array();
            
            switch ( $widget ) {
                case 'summary':
                    $overview = $this->analytics_service->get_dashboard_overview( $filters );
                    $data = $overview['summary'];
                    break;
                    
                case 'trends':
                    $overview = $this->analytics_service->get_dashboard_overview( $filters );
                    $data = $overview['trends'];
                    break;
                    
                case 'funnel':
                    $overview = $this->analytics_service->get_dashboard_overview( $filters );
                    $data = $overview['conversion_funnel'];
                    break;
                    
                default:
                    throw new \Exception( __( 'Invalid widget type', 'money-quiz' ) );
            }
            
            ResponseUtil::success( $data );
            
        } catch ( \Exception $e ) {
            ResponseUtil::error( $e->getMessage() );
        }
    }
}