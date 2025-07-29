<?php
/**
 * Hybrid Routing Admin - Admin interface for monitoring and control
 * 
 * @package MoneyQuiz
 * @subpackage Admin
 * @since 1.5.0
 */

namespace MoneyQuiz\Admin;

use MoneyQuiz\Routing\HybridRouter;
use MoneyQuiz\Routing\FeatureFlagManager;
use MoneyQuiz\Routing\Monitoring\RouteMonitor;
use MoneyQuiz\Routing\Rollback\RollbackManager;

if (!defined('ABSPATH')) {
    exit;
}

class HybridRoutingAdmin {
    
    /**
     * Router instance
     */
    private $router;
    
    /**
     * Feature flag manager
     */
    private $feature_flags;
    
    /**
     * Route monitor
     */
    private $monitor;
    
    /**
     * Rollback manager
     */
    private $rollback_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->router = new HybridRouter();
        $this->feature_flags = new FeatureFlagManager();
        $this->monitor = new RouteMonitor();
        $this->rollback_manager = new RollbackManager();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_mq_update_feature_flag', [$this, 'ajax_update_feature_flag']);
        add_action('wp_ajax_mq_get_routing_stats', [$this, 'ajax_get_routing_stats']);
        add_action('wp_ajax_mq_trigger_rollback', [$this, 'ajax_trigger_rollback']);
        add_action('wp_ajax_mq_clear_rollback', [$this, 'ajax_clear_rollback']);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'money-quiz-settings',
            'Hybrid Routing Control',
            'Routing Control',
            'manage_options',
            'mq-routing-control',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mq-routing-control') === false && 
            $hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_script(
            'mq-routing-admin',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/routing-admin.js',
            ['jquery', 'wp-api'],
            '1.5.0',
            true
        );
        
        wp_enqueue_style(
            'mq-routing-admin',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/routing-admin.css',
            [],
            '1.5.0'
        );
        
        wp_localize_script('mq-routing-admin', 'mqRoutingAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mq_routing_admin'),
            'refreshInterval' => 5000 // 5 seconds
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Get current data
        $health = $this->monitor->get_system_health();
        $flags = $this->feature_flags->get_all_flags();
        $rollback_status = $this->rollback_manager->get_status();
        $routing_stats = $this->router->get_stats(7);
        
        ?>
        <div class="wrap">
            <h1>Money Quiz Hybrid Routing Control</h1>
            
            <?php if ($rollback_status['active']): ?>
            <div class="notice notice-error">
                <p><strong>System Rollback Active:</strong> All traffic is being routed to the legacy system.</p>
                <?php if ($rollback_status['cooldown_remaining'] > 0): ?>
                <p>Cooldown remaining: <?php echo $this->format_time($rollback_status['cooldown_remaining']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- System Health -->
            <div class="mq-health-status mq-card">
                <h2>System Health</h2>
                <div class="mq-health-indicator mq-health-<?php echo esc_attr($health['status']); ?>">
                    <span class="mq-health-icon"></span>
                    <span class="mq-health-text"><?php echo ucfirst($health['status']); ?></span>
                </div>
                
                <?php if (!empty($health['issues'])): ?>
                <div class="mq-health-issues">
                    <h3>Issues:</h3>
                    <ul>
                        <?php foreach ($health['issues'] as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="mq-metrics-grid">
                    <div class="mq-metric">
                        <span class="mq-metric-label">Error Rate</span>
                        <span class="mq-metric-value"><?php echo number_format($health['metrics']['error_rate'] * 100, 2); ?>%</span>
                    </div>
                    <div class="mq-metric">
                        <span class="mq-metric-label">Avg Response</span>
                        <span class="mq-metric-value"><?php echo number_format($health['metrics']['avg_response'], 2); ?>s</span>
                    </div>
                    <div class="mq-metric">
                        <span class="mq-metric-label">Peak Memory</span>
                        <span class="mq-metric-value"><?php echo $health['metrics']['peak_memory']; ?>MB</span>
                    </div>
                    <div class="mq-metric">
                        <span class="mq-metric-label">Modern Traffic</span>
                        <span class="mq-metric-value"><?php echo number_format($health['metrics']['modern_percentage'] * 100, 1); ?>%</span>
                    </div>
                </div>
            </div>
            
            <!-- Feature Flags -->
            <div class="mq-feature-flags mq-card">
                <h2>Feature Flags</h2>
                <p>Control traffic distribution between legacy and modern systems:</p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Description</th>
                            <th>Traffic %</th>
                            <th>Adoption Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flags as $flag_name => $flag): ?>
                        <tr>
                            <td><strong><?php echo esc_html($flag['name']); ?></strong></td>
                            <td><?php echo esc_html($flag['description']); ?></td>
                            <td>
                                <input type="range" 
                                       class="mq-flag-slider" 
                                       data-flag="<?php echo esc_attr($flag_name); ?>"
                                       min="0" 
                                       max="100" 
                                       value="<?php echo $flag['percentage']; ?>"
                                       <?php echo $rollback_status['active'] ? 'disabled' : ''; ?>>
                                <span class="mq-flag-value"><?php echo $flag['percentage']; ?>%</span>
                            </td>
                            <td><?php echo number_format($flag['adoption'], 1); ?>%</td>
                            <td>
                                <button class="button button-small mq-flag-save" 
                                        data-flag="<?php echo esc_attr($flag_name); ?>"
                                        <?php echo $rollback_status['active'] ? 'disabled' : ''; ?>>
                                    Save
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rollback Controls -->
            <div class="mq-rollback-controls mq-card">
                <h2>Rollback Controls</h2>
                
                <?php if ($rollback_status['can_manual_rollback']): ?>
                <p>Manually trigger a rollback to route all traffic to the legacy system:</p>
                <button class="button button-primary button-hero" id="mq-manual-rollback">
                    Trigger Manual Rollback
                </button>
                <?php else: ?>
                <p>Manual rollback is not available during cooldown period.</p>
                <?php endif; ?>
                
                <?php if ($rollback_status['active'] && !$rollback_status['in_cooldown']): ?>
                <p>Clear rollback and allow traffic routing:</p>
                <button class="button button-secondary" id="mq-clear-rollback">
                    Clear Rollback
                </button>
                <?php endif; ?>
                
                <?php if ($last_rollback = $rollback_status['last_rollback']): ?>
                <div class="mq-last-rollback">
                    <h3>Last Rollback</h3>
                    <p>Type: <?php echo esc_html($last_rollback['type']); ?></p>
                    <p>Time: <?php echo esc_html($last_rollback['timestamp']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Traffic Statistics -->
            <div class="mq-traffic-stats mq-card">
                <h2>Traffic Statistics (Last 7 Days)</h2>
                <div id="mq-stats-chart"></div>
                
                <div class="mq-stats-summary">
                    <?php 
                    $total_requests = 0;
                    $total_modern = 0;
                    foreach ($routing_stats as $date => $stats) {
                        $total_requests += $stats['total_requests'];
                        $total_modern += $stats['modern_requests'];
                    }
                    ?>
                    <div class="mq-stat">
                        <span class="mq-stat-label">Total Requests</span>
                        <span class="mq-stat-value"><?php echo number_format($total_requests); ?></span>
                    </div>
                    <div class="mq-stat">
                        <span class="mq-stat-label">Modern System</span>
                        <span class="mq-stat-value"><?php echo number_format($total_modern); ?></span>
                    </div>
                    <div class="mq-stat">
                        <span class="mq-stat-label">Legacy System</span>
                        <span class="mq-stat-value"><?php echo number_format($total_requests - $total_modern); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'mq_routing_status',
            'Money Quiz Routing Status',
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $health = $this->monitor->get_system_health();
        $rollback_status = $this->rollback_manager->get_status();
        
        ?>
        <div class="mq-dashboard-widget">
            <div class="mq-widget-status mq-health-<?php echo esc_attr($health['status']); ?>">
                <span class="mq-health-icon"></span>
                <strong>System Status:</strong> <?php echo ucfirst($health['status']); ?>
            </div>
            
            <?php if ($rollback_status['active']): ?>
            <div class="mq-widget-alert">
                <strong>Rollback Active</strong> - All traffic routed to legacy system
            </div>
            <?php endif; ?>
            
            <div class="mq-widget-metrics">
                <div class="mq-widget-metric">
                    <span class="label">Error Rate:</span>
                    <span class="value"><?php echo number_format($health['metrics']['error_rate'] * 100, 2); ?>%</span>
                </div>
                <div class="mq-widget-metric">
                    <span class="label">Modern Traffic:</span>
                    <span class="value"><?php echo number_format($health['metrics']['modern_percentage'] * 100, 1); ?>%</span>
                </div>
            </div>
            
            <p class="mq-widget-link">
                <a href="<?php echo admin_url('admin.php?page=mq-routing-control'); ?>">
                    View Full Dashboard →
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * AJAX: Update feature flag
     */
    public function ajax_update_feature_flag() {
        check_ajax_referer('mq_routing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $flag = sanitize_text_field($_POST['flag']);
        $value = floatval($_POST['value']) / 100; // Convert percentage to decimal
        
        if ($this->feature_flags->update_flag($flag, $value)) {
            wp_send_json_success([
                'message' => 'Feature flag updated successfully',
                'flag' => $flag,
                'value' => $value * 100
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to update feature flag']);
        }
    }
    
    /**
     * AJAX: Get routing statistics
     */
    public function ajax_get_routing_stats() {
        check_ajax_referer('mq_routing_admin', 'nonce');
        
        $health = $this->monitor->get_system_health();
        $stats = $this->router->get_stats(7);
        
        wp_send_json_success([
            'health' => $health,
            'stats' => $stats,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * AJAX: Trigger rollback
     */
    public function ajax_trigger_rollback() {
        check_ajax_referer('mq_routing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $metrics = $this->monitor->get_recent_metrics(300);
        $this->rollback_manager->execute_rollback($metrics, 'manual');
        
        wp_send_json_success([
            'message' => 'Rollback triggered successfully',
            'status' => $this->rollback_manager->get_status()
        ]);
    }
    
    /**
     * AJAX: Clear rollback
     */
    public function ajax_clear_rollback() {
        check_ajax_referer('mq_routing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $this->rollback_manager->clear_rollback();
        
        wp_send_json_success([
            'message' => 'Rollback cleared successfully',
            'status' => $this->rollback_manager->get_status()
        ]);
    }
    
    /**
     * Format time duration
     * 
     * @param int $seconds
     * @return string
     */
    private function format_time($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }
}