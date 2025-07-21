<?php
/**
 * Webhook Retry Logic
 * 
 * @package MoneyQuiz\Webhooks
 * @version 1.0.0
 */

namespace MoneyQuiz\Webhooks;

/**
 * Retry Manager
 */
class RetryManager {
    
    private static $instance = null;
    private $max_retries = 3;
    private $retry_delays = [60, 300, 900]; // 1min, 5min, 15min
    
    private function __construct() {}
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize retry system
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Schedule retry cron
        if (!wp_next_scheduled('money_quiz_webhook_retry')) {
            wp_schedule_event(time(), 'every_five_minutes', 'money_quiz_webhook_retry');
        }
        
        add_action('money_quiz_webhook_retry', [$instance, 'processRetryQueue']);
        
        // Add custom cron schedule
        add_filter('cron_schedules', [$instance, 'addCronSchedules']);
    }
    
    /**
     * Add cron schedules
     */
    public function addCronSchedules($schedules) {
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 minutes'
        ];
        return $schedules;
    }
    
    /**
     * Queue for retry
     */
    public function queueForRetry($webhook, $event, $attempt = 0) {
        global $wpdb;
        
        if ($attempt >= $this->max_retries) {
            $this->markAsFailed($webhook, $event);
            return false;
        }
        
        $retry_at = $this->calculateRetryTime($attempt);
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_webhook_retries',
            [
                'webhook_id' => $webhook->getId(),
                'event' => $event->getName(),
                'payload' => $event->serialize(),
                'attempt' => $attempt + 1,
                'retry_at' => $retry_at,
                'created_at' => current_time('mysql')
            ]
        );
        
        return true;
    }
    
    /**
     * Calculate retry time
     */
    private function calculateRetryTime($attempt) {
        $delay = isset($this->retry_delays[$attempt]) 
            ? $this->retry_delays[$attempt] 
            : end($this->retry_delays);
        
        return date('Y-m-d H:i:s', time() + $delay);
    }
    
    /**
     * Process retry queue
     */
    public function processRetryQueue() {
        global $wpdb;
        
        // Get pending retries
        $retries = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, w.url, w.secret, w.headers
            FROM {$wpdb->prefix}money_quiz_webhook_retries r
            JOIN {$wpdb->prefix}money_quiz_webhooks w ON r.webhook_id = w.id
            WHERE r.status = 'pending' 
            AND r.retry_at <= %s
            ORDER BY r.retry_at ASC
            LIMIT 10
        ", current_time('mysql')));
        
        foreach ($retries as $retry) {
            $this->processRetry($retry);
        }
    }
    
    /**
     * Process single retry
     */
    private function processRetry($retry) {
        global $wpdb;
        
        // Mark as processing
        $wpdb->update(
            $wpdb->prefix . 'money_quiz_webhook_retries',
            ['status' => 'processing'],
            ['id' => $retry->id]
        );
        
        // Recreate webhook and event
        $webhook = new WebhookBase([
            'id' => $retry->webhook_id,
            'url' => $retry->url,
            'secret' => $retry->secret,
            'headers' => json_decode($retry->headers, true)
        ]);
        
        $event_data = json_decode($retry->payload, true);
        $event = new WebhookEvent($event_data['event'], $event_data['data']);
        
        // Attempt delivery
        $engine = new DeliveryEngine();
        $success = $engine->send($webhook, $event);
        
        if ($success) {
            // Mark as completed
            $wpdb->update(
                $wpdb->prefix . 'money_quiz_webhook_retries',
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $retry->id]
            );
        } else {
            // Queue for next retry or mark as failed
            if ($retry->attempt < $this->max_retries) {
                $this->queueForRetry($webhook, $event, $retry->attempt);
            }
            
            $wpdb->update(
                $wpdb->prefix . 'money_quiz_webhook_retries',
                ['status' => 'failed'],
                ['id' => $retry->id]
            );
        }
    }
    
    /**
     * Mark as failed
     */
    private function markAsFailed($webhook, $event) {
        // Log permanent failure
        do_action('money_quiz_webhook_failed', $webhook, $event);
        
        // Notify admin
        $this->notifyAdmin($webhook, $event);
    }
    
    /**
     * Notify admin of failure
     */
    private function notifyAdmin($webhook, $event) {
        $admin_email = get_option('admin_email');
        
        $subject = '[Money Quiz] Webhook Delivery Failed';
        $message = sprintf(
            "A webhook delivery has failed after %d attempts.\n\n" .
            "Webhook URL: %s\n" .
            "Event: %s\n" .
            "Time: %s\n\n" .
            "Please check your webhook configuration.",
            $this->max_retries,
            $webhook->getUrl(),
            $event->getName(),
            current_time('mysql')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get retry status
     */
    public function getRetryStatus($webhook_id = null) {
        global $wpdb;
        
        $where = $webhook_id ? $wpdb->prepare('WHERE webhook_id = %d', $webhook_id) : '';
        
        return [
            'pending' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_webhook_retries 
                {$where} AND status = 'pending'
            "),
            'completed' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_webhook_retries 
                {$where} AND status = 'completed'
            "),
            'failed' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_webhook_retries 
                {$where} AND status = 'failed'
            ")
        ];
    }
    
    /**
     * Clean old retries
     */
    public function cleanOldRetries($days = 30) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}money_quiz_webhook_retries
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND status IN ('completed', 'failed')
        ", $days));
    }
}