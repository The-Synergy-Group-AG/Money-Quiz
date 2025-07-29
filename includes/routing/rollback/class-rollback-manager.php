<?php
/**
 * Rollback Manager - Handles automatic and manual rollbacks
 * 
 * @package MoneyQuiz
 * @subpackage Routing\Rollback
 * @since 4.0.0
 */

namespace MoneyQuiz\Routing\Rollback;

/**
 * Manages rollback operations for hybrid migration
 */
class RollbackManager {
    
    /**
     * @var float Error threshold for rollback
     */
    const ERROR_THRESHOLD = 0.05; // 5% error rate
    
    /**
     * @var float Response time threshold (seconds)
     */
    const RESPONSE_THRESHOLD = 5.0; // 5 seconds
    
    /**
     * @var int Memory threshold (MB)
     */
    const MEMORY_THRESHOLD = 256; // 256MB
    
    /**
     * @var array Rollback configuration
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        
        // Register AJAX handlers for manual rollback
        add_action('wp_ajax_mq_manual_rollback', [$this, 'handle_manual_rollback']);
    }
    
    /**
     * Load rollback configuration
     */
    private function load_config() {
        $defaults = [
            'auto_rollback' => true,
            'manual_override' => true,
            'notification_emails' => [get_option('admin_email')],
            'cooldown_minutes' => 60,
            'error_threshold' => self::ERROR_THRESHOLD,
            'response_threshold' => self::RESPONSE_THRESHOLD,
            'memory_threshold' => self::MEMORY_THRESHOLD
        ];
        
        $saved = get_option('mq_rollback_config', []);
        $this->config = wp_parse_args($saved, $defaults);
    }
    
    /**
     * Check if rollback should be triggered
     * 
     * @param array $metrics Current metrics
     * @return bool
     */
    public function should_rollback($metrics) {
        // Check if auto rollback is enabled
        if (!$this->config['auto_rollback']) {
            return false;
        }
        
        // Check if we're in cooldown period
        if ($this->in_cooldown()) {
            return false;
        }
        
        // Check if already rolled back
        if (get_transient('mq_emergency_rollback')) {
            return false;
        }
        
        // Check thresholds
        $triggers = [];
        
        // Error rate check
        if (isset($metrics['error_rate']) && $metrics['error_rate'] > $this->config['error_threshold']) {
            $triggers[] = sprintf(
                'Error rate (%.1f%%) exceeds threshold (%.1f%%)',
                $metrics['error_rate'] * 100,
                $this->config['error_threshold'] * 100
            );
        }
        
        // Response time check
        if (isset($metrics['avg_response']) && $metrics['avg_response'] > $this->config['response_threshold']) {
            $triggers[] = sprintf(
                'Response time (%.1fs) exceeds threshold (%.1fs)',
                $metrics['avg_response'],
                $this->config['response_threshold']
            );
        }
        
        // Memory check
        if (isset($metrics['peak_memory']) && $metrics['peak_memory'] > $this->config['memory_threshold']) {
            $triggers[] = sprintf(
                'Memory usage (%dMB) exceeds threshold (%dMB)',
                $metrics['peak_memory'],
                $this->config['memory_threshold']
            );
        }
        
        // Store triggers for logging
        if (!empty($triggers)) {
            $this->last_triggers = $triggers;
            return true;
        }
        
        return false;
    }
    
    /**
     * Execute rollback
     * 
     * @param array $metrics Current metrics that triggered rollback
     * @param string $type 'auto' or 'manual'
     * @return bool
     */
    public function execute_rollback($metrics = [], $type = 'auto') {
        // Set emergency rollback flag
        set_transient('mq_emergency_rollback', true, DAY_IN_SECONDS);
        
        // Reset all feature flags to 0
        update_option('mq_feature_flags', array_fill_keys([
            'modern_quiz_display',
            'modern_quiz_list',
            'modern_archetype_fetch',
            'modern_statistics',
            'modern_quiz_submit',
            'modern_prospect_save',
            'modern_email_send'
        ], 0.0));
        
        // Log rollback event
        $this->log_rollback_event($type, $metrics);
        
        // Send notifications
        $this->send_rollback_notifications($type, $metrics);
        
        // Set cooldown
        set_transient('mq_rollback_cooldown', true, $this->config['cooldown_minutes'] * MINUTE_IN_SECONDS);
        
        // Clear any cached routing decisions
        wp_cache_flush();
        
        // Trigger action for other systems to respond
        do_action('mq_routing_rollback_executed', $type, $metrics);
        
        return true;
    }
    
    /**
     * Check if in cooldown period
     * 
     * @return bool
     */
    private function in_cooldown() {
        return (bool) get_transient('mq_rollback_cooldown');
    }
    
    /**
     * Log rollback event
     * 
     * @param string $type
     * @param array $metrics
     */
    private function log_rollback_event($type, $metrics) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_rollback_events';
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $this->create_rollback_table();
        }
        
        // Prepare trigger details
        $trigger_details = [];
        if (isset($this->last_triggers)) {
            $trigger_details = $this->last_triggers;
        }
        
        $wpdb->insert(
            $table,
            [
                'rollback_type' => $type,
                'trigger_type' => $type === 'manual' ? 'manual' : 'threshold',
                'trigger_details' => json_encode($trigger_details),
                'metrics_snapshot' => json_encode($metrics),
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s']
        );
    }
    
    /**
     * Send rollback notifications
     * 
     * @param string $type
     * @param array $metrics
     */
    private function send_rollback_notifications($type, $metrics) {
        $subject = sprintf(
            '[Money Quiz] Emergency Rollback %s',
            $type === 'manual' ? '(Manual)' : '(Automatic)'
        );
        
        $message = "An emergency rollback has been executed for the Money Quiz hybrid routing system.\n\n";
        $message .= "Rollback Type: " . ucfirst($type) . "\n";
        $message .= "Timestamp: " . current_time('mysql') . "\n\n";
        
        if ($type === 'auto' && isset($this->last_triggers)) {
            $message .= "Triggers:\n";
            foreach ($this->last_triggers as $trigger) {
                $message .= "- $trigger\n";
            }
            $message .= "\n";
        }
        
        if (!empty($metrics)) {
            $message .= "Current Metrics:\n";
            $message .= sprintf("- Error Rate: %.1f%%\n", ($metrics['error_rate'] ?? 0) * 100);
            $message .= sprintf("- Avg Response: %.1fs\n", $metrics['avg_response'] ?? 0);
            $message .= sprintf("- Peak Memory: %dMB\n", $metrics['peak_memory'] ?? 0);
            $message .= sprintf("- Total Requests: %d\n", $metrics['total'] ?? 0);
            $message .= "\n";
        }
        
        $message .= "All traffic has been routed back to the legacy system.\n";
        $message .= "Please investigate the issue before re-enabling modern routing.\n\n";
        $message .= "To re-enable routing after fixing issues:\n";
        $message .= "1. Clear the rollback flag: wp transient delete mq_emergency_rollback\n";
        $message .= "2. Reconfigure feature flags in the admin panel\n";
        
        // Send to all configured emails
        foreach ($this->config['notification_emails'] as $email) {
            wp_mail($email, $subject, $message);
        }
    }
    
    /**
     * Handle manual rollback request
     */
    public function handle_manual_rollback() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        check_ajax_referer('mq_rollback_nonce', 'nonce');
        
        // Get current metrics for logging
        $monitor = new \MoneyQuiz\Routing\Monitoring\RouteMonitor();
        $metrics = $monitor->get_recent_metrics(300);
        
        // Execute rollback
        $success = $this->execute_rollback($metrics, 'manual');
        
        if ($success) {
            wp_send_json_success([
                'message' => 'Rollback executed successfully. All traffic routed to legacy system.',
                'metrics' => $metrics
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Rollback failed. Please check error logs.'
            ]);
        }
    }
    
    /**
     * Get rollback history
     * 
     * @param int $limit
     * @return array
     */
    public function get_rollback_history($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_rollback_events';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            ORDER BY timestamp DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }
    
    /**
     * Check if can recover from rollback
     * 
     * @return array
     */
    public function can_recover() {
        $rollback_active = get_transient('mq_emergency_rollback');
        $in_cooldown = $this->in_cooldown();
        
        $can_recover = !$in_cooldown;
        $reasons = [];
        
        if ($in_cooldown) {
            $cooldown_remaining = get_option('_transient_timeout_mq_rollback_cooldown') - time();
            $reasons[] = sprintf(
                'Cooldown period active (%d minutes remaining)',
                ceil($cooldown_remaining / 60)
            );
        }
        
        if (!$rollback_active) {
            $reasons[] = 'No active rollback';
        }
        
        return [
            'can_recover' => $can_recover,
            'rollback_active' => $rollback_active,
            'in_cooldown' => $in_cooldown,
            'reasons' => $reasons
        ];
    }
    
    /**
     * Clear rollback state
     * 
     * @return bool
     */
    public function clear_rollback() {
        delete_transient('mq_emergency_rollback');
        delete_transient('mq_rollback_cooldown');
        
        // Log recovery
        global $wpdb;
        $table = $wpdb->prefix . 'mq_rollback_events';
        
        $wpdb->insert(
            $table,
            [
                'rollback_type' => 'recovery',
                'trigger_type' => 'manual_clear',
                'trigger_details' => json_encode(['action' => 'rollback_cleared']),
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );
        
        return true;
    }
    
    /**
     * Create rollback events table
     */
    private function create_rollback_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_rollback_events';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            rollback_type varchar(20) NOT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_details text,
            metrics_snapshot text,
            user_id bigint(20) DEFAULT 0,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_type (rollback_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Update configuration
     * 
     * @param array $config
     * @return bool
     */
    public function update_config($config) {
        $this->config = wp_parse_args($config, $this->config);
        return update_option('mq_rollback_config', $this->config);
    }
}