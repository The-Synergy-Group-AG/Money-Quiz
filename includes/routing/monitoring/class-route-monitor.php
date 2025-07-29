<?php
/**
 * Route Monitor - Tracks routing performance and errors
 * 
 * @package MoneyQuiz
 * @subpackage Routing\Monitoring
 * @since 4.0.0
 */

namespace MoneyQuiz\Routing\Monitoring;

use MoneyQuiz\Routing\Rollback\RollbackManager;

/**
 * Monitors routing performance and triggers rollbacks
 */
class RouteMonitor {
    
    /**
     * @var RollbackManager
     */
    private $rollback_manager;
    
    /**
     * @var array Current session metrics
     */
    private $session_metrics = [
        'requests' => 0,
        'errors' => 0,
        'total_duration' => 0,
        'peak_memory' => 0
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->rollback_manager = new RollbackManager();
        
        // Set up periodic checks
        if (!wp_next_scheduled('mq_check_routing_health')) {
            wp_schedule_event(time(), 'mq_five_minutes', 'mq_check_routing_health');
        }
        
        add_action('mq_check_routing_health', [$this, 'check_health']);
        add_action('shutdown', [$this, 'flush_metrics']);
    }
    
    /**
     * Record successful request
     * 
     * @param string $system 'modern' or 'legacy'
     * @param string $action
     * @param float $duration
     * @param int $memory
     */
    public function record_success($system, $action, $duration, $memory = null) {
        $this->session_metrics['requests']++;
        $this->session_metrics['total_duration'] += $duration;
        
        if ($memory && $memory > $this->session_metrics['peak_memory']) {
            $this->session_metrics['peak_memory'] = $memory;
        }
        
        $this->update_metrics([
            'system' => $system,
            'action' => $action,
            'status' => 'success',
            'duration' => $duration,
            'memory' => $memory,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Record error
     * 
     * @param \Exception $exception
     * @param string $action
     * @param array $context
     */
    public function record_error($exception, $action, $context = []) {
        $this->session_metrics['errors']++;
        
        $error_data = [
            'system' => 'error',
            'action' => $action,
            'status' => 'error',
            'error_type' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'timestamp' => current_time('mysql')
        ];
        
        // Add context if provided
        if (!empty($context)) {
            $error_data['context'] = json_encode($context);
        }
        
        $this->update_metrics($error_data);
        
        // Log to error log as well
        error_log(sprintf(
            '[Money Quiz Routing Error] %s in %s: %s',
            $action,
            $exception->getFile() . ':' . $exception->getLine(),
            $exception->getMessage()
        ));
        
        // Check if we need to trigger rollback
        $this->check_rollback_thresholds();
    }
    
    /**
     * Update metrics in database
     * 
     * @param array $data
     */
    private function update_metrics($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $this->create_metrics_table();
        }
        
        $wpdb->insert(
            $table,
            $data,
            $this->get_data_format($data)
        );
        
        // Clean old metrics periodically
        if (mt_rand(1, 100) === 1) { // 1% chance
            $this->cleanup_old_metrics();
        }
    }
    
    /**
     * Check if rollback thresholds are exceeded
     */
    public function check_rollback_thresholds() {
        $recent_metrics = $this->get_recent_metrics(300); // Last 5 minutes
        
        if ($this->rollback_manager->should_rollback($recent_metrics)) {
            $this->rollback_manager->execute_rollback($recent_metrics);
        }
    }
    
    /**
     * Get recent metrics
     * 
     * @param int $seconds
     * @return array
     */
    public function get_recent_metrics($seconds = 300) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        $since = date('Y-m-d H:i:s', time() - $seconds);
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                AVG(duration) as avg_response,
                MAX(duration) as max_response,
                MAX(memory) as peak_memory,
                SUM(CASE WHEN system = 'modern' THEN 1 ELSE 0 END) as modern_count,
                SUM(CASE WHEN system = 'legacy' THEN 1 ELSE 0 END) as legacy_count
            FROM $table
            WHERE timestamp > %s
        ", $since), ARRAY_A);
        
        $metrics = $results[0] ?? [];
        
        // Calculate error rate
        if ($metrics['total'] > 0) {
            $metrics['error_rate'] = $metrics['errors'] / $metrics['total'];
            $metrics['modern_percentage'] = $metrics['modern_count'] / $metrics['total'];
        } else {
            $metrics['error_rate'] = 0;
            $metrics['modern_percentage'] = 0;
        }
        
        // Convert memory to MB
        if ($metrics['peak_memory']) {
            $metrics['peak_memory'] = $metrics['peak_memory'] / 1048576; // Convert to MB
        }
        
        return $metrics;
    }
    
    /**
     * Get traffic distribution
     * 
     * @param int $hours
     * @return array
     */
    public function get_traffic_distribution($hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                system,
                COUNT(*) as count,
                AVG(duration) as avg_duration
            FROM $table
            WHERE timestamp > %s
            AND status = 'success'
            GROUP BY system
        ", $since), ARRAY_A);
    }
    
    /**
     * Get error rates by action
     * 
     * @param int $hours
     * @return array
     */
    public function get_error_rates($hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                action,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                ROUND(SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as error_rate
            FROM $table
            WHERE timestamp > %s
            GROUP BY action
            HAVING errors > 0
            ORDER BY error_rate DESC
        ", $since), ARRAY_A);
    }
    
    /**
     * Get performance metrics comparison
     * 
     * @param int $hours
     * @return array
     */
    public function get_performance_metrics($hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                system,
                action,
                COUNT(*) as requests,
                ROUND(AVG(duration), 3) as avg_duration,
                ROUND(MIN(duration), 3) as min_duration,
                ROUND(MAX(duration), 3) as max_duration,
                ROUND(STD(duration), 3) as std_duration
            FROM $table
            WHERE timestamp > %s
            AND status = 'success'
            AND system IN ('modern', 'legacy')
            GROUP BY system, action
            ORDER BY action, system
        ", $since), ARRAY_A);
    }
    
    /**
     * Get system health status
     * 
     * @return array
     */
    public function get_system_health() {
        $recent_metrics = $this->get_recent_metrics(300); // Last 5 minutes
        
        $status = 'good';
        $issues = [];
        
        // Check error rate
        if ($recent_metrics['error_rate'] > 0.05) {
            $status = 'critical';
            $issues[] = sprintf('High error rate: %.1f%%', $recent_metrics['error_rate'] * 100);
        } elseif ($recent_metrics['error_rate'] > 0.02) {
            $status = 'warning';
            $issues[] = sprintf('Elevated error rate: %.1f%%', $recent_metrics['error_rate'] * 100);
        }
        
        // Check response time
        if ($recent_metrics['avg_response'] > 5.0) {
            $status = 'critical';
            $issues[] = sprintf('Slow response time: %.1fs', $recent_metrics['avg_response']);
        } elseif ($recent_metrics['avg_response'] > 3.0) {
            $status = ($status === 'critical') ? 'critical' : 'warning';
            $issues[] = sprintf('Response time warning: %.1fs', $recent_metrics['avg_response']);
        }
        
        // Check memory usage
        if ($recent_metrics['peak_memory'] > 256) {
            $status = 'critical';
            $issues[] = sprintf('High memory usage: %dMB', $recent_metrics['peak_memory']);
        } elseif ($recent_metrics['peak_memory'] > 128) {
            $status = ($status === 'critical') ? 'critical' : 'warning';
            $issues[] = sprintf('Memory usage warning: %dMB', $recent_metrics['peak_memory']);
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => $recent_metrics,
            'can_increase_traffic' => $status === 'good',
            'should_rollback' => $status === 'critical'
        ];
    }
    
    /**
     * Get week metrics for reporting
     * 
     * @param int $week
     * @return array
     */
    public function get_week_metrics($week) {
        $start_date = get_option('mq_hybrid_start_date');
        if (!$start_date) {
            return [];
        }
        
        $week_start = date('Y-m-d H:i:s', strtotime($start_date) + (($week - 1) * 7 * 86400));
        $week_end = date('Y-m-d H:i:s', strtotime($week_start) + (7 * 86400));
        
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        $results = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN system = 'modern' THEN 1 ELSE 0 END) as modern,
                SUM(CASE WHEN system = 'legacy' THEN 1 ELSE 0 END) as legacy,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                AVG(duration) as avg_response,
                MAX(memory) / 1048576 as peak_memory_mb
            FROM $table
            WHERE timestamp >= %s
            AND timestamp < %s
        ", $week_start, $week_end), ARRAY_A);
        
        // Get rollback events
        $rollback_table = $wpdb->prefix . 'mq_rollback_events';
        $results['rollbacks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $rollback_table
            WHERE timestamp >= %s
            AND timestamp < %s
        ", $week_start, $week_end));
        
        return $results;
    }
    
    /**
     * Create metrics table
     */
    private function create_metrics_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            system varchar(20) NOT NULL,
            action varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            duration float DEFAULT NULL,
            memory int(11) DEFAULT NULL,
            error_type varchar(100) DEFAULT NULL,
            error_message text DEFAULT NULL,
            error_file varchar(255) DEFAULT NULL,
            error_line int(11) DEFAULT NULL,
            context text DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_system_status (system, status),
            KEY idx_action (action)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get data format for wpdb
     * 
     * @param array $data
     * @return array
     */
    private function get_data_format($data) {
        $formats = [
            'system' => '%s',
            'action' => '%s',
            'status' => '%s',
            'duration' => '%f',
            'memory' => '%d',
            'error_type' => '%s',
            'error_message' => '%s',
            'error_file' => '%s',
            'error_line' => '%d',
            'context' => '%s',
            'timestamp' => '%s'
        ];
        
        return array_intersect_key($formats, $data);
    }
    
    /**
     * Clean up old metrics
     */
    private function cleanup_old_metrics() {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_routing_metrics';
        
        // Keep 30 days of data
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE timestamp < %s",
            $cutoff
        ));
    }
    
    /**
     * Flush session metrics
     */
    public function flush_metrics() {
        if ($this->session_metrics['requests'] > 0) {
            // Store session summary
            $summary = [
                'session_id' => session_id() ?: 'cli',
                'requests' => $this->session_metrics['requests'],
                'errors' => $this->session_metrics['errors'],
                'avg_duration' => $this->session_metrics['total_duration'] / $this->session_metrics['requests'],
                'peak_memory_mb' => $this->session_metrics['peak_memory'] / 1048576,
                'timestamp' => current_time('mysql')
            ];
            
            // Could store this in a separate session metrics table if needed
            do_action('mq_routing_session_complete', $summary);
        }
    }
    
    /**
     * Check system health (scheduled)
     */
    public function check_health() {
        $health = $this->get_system_health();
        
        if ($health['status'] === 'critical') {
            // Notify admins
            $this->notify_critical_status($health);
        }
        
        // Store health check result
        set_transient('mq_routing_health', $health, 300);
    }
    
    /**
     * Notify admins of critical status
     * 
     * @param array $health
     */
    private function notify_critical_status($health) {
        // Check if we've already notified recently
        if (get_transient('mq_routing_critical_notified')) {
            return;
        }
        
        $message = "Critical issues detected in Money Quiz routing system:\n\n";
        foreach ($health['issues'] as $issue) {
            $message .= "- $issue\n";
        }
        $message .= "\nAutomatic rollback may be triggered if issues persist.";
        
        wp_mail(
            get_option('admin_email'),
            '[Money Quiz] Critical Routing Issues Detected',
            $message
        );
        
        // Don't spam - wait 1 hour before next notification
        set_transient('mq_routing_critical_notified', true, HOUR_IN_SECONDS);
    }
}