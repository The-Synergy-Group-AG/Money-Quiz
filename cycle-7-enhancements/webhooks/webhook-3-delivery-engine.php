<?php
/**
 * Webhook Delivery Engine
 * 
 * @package MoneyQuiz\Webhooks
 * @version 1.0.0
 */

namespace MoneyQuiz\Webhooks;

/**
 * Delivery Engine
 */
class DeliveryEngine implements WebhookDeliveryInterface {
    
    private $response;
    private $delivery_time;
    private $successful;
    private $max_attempts = 3;
    private $timeout = 30;
    
    /**
     * Send webhook
     */
    public function send($webhook, $event) {
        $start_time = microtime(true);
        
        try {
            // Prepare payload
            $payload = $event->serialize();
            
            // Generate signature
            $signature = $webhook->generateSignature($payload);
            
            // Prepare request
            $args = [
                'method' => 'POST',
                'timeout' => $this->timeout,
                'headers' => $webhook->getHeaders($event->getName(), $signature),
                'body' => $payload,
                'sslverify' => apply_filters('money_quiz_webhook_ssl_verify', true)
            ];
            
            // Log delivery attempt
            $this->logDeliveryAttempt($webhook, $event);
            
            // Send request
            $response = wp_remote_post($webhook->getUrl(), $args);
            
            // Process response
            $this->processResponse($response);
            
            // Log result
            $this->logDeliveryResult($webhook, $event, $this->response);
            
        } catch (\Exception $e) {
            $this->successful = false;
            $this->response = [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
        
        $this->delivery_time = microtime(true) - $start_time;
        
        return $this->successful;
    }
    
    /**
     * Process response
     */
    private function processResponse($response) {
        if (is_wp_error($response)) {
            $this->successful = false;
            $this->response = [
                'error' => $response->get_error_message(),
                'code' => $response->get_error_code()
            ];
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->response = [
            'code' => $code,
            'body' => $body,
            'headers' => wp_remote_retrieve_headers($response)->getAll()
        ];
        
        // Consider 2xx responses as successful
        $this->successful = $code >= 200 && $code < 300;
    }
    
    /**
     * Log delivery attempt
     */
    private function logDeliveryAttempt($webhook, $event) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_webhook_logs',
            [
                'webhook_id' => $webhook->getId(),
                'event' => $event->getName(),
                'url' => $webhook->getUrl(),
                'payload' => $event->serialize(),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Log delivery result
     */
    private function logDeliveryResult($webhook, $event, $response) {
        global $wpdb;
        
        $status = $this->successful ? 'success' : 'failed';
        $response_data = is_array($response) ? json_encode($response) : $response;
        
        // Update log
        $wpdb->update(
            $wpdb->prefix . 'money_quiz_webhook_logs',
            [
                'status' => $status,
                'response' => $response_data,
                'response_code' => $response['code'] ?? 0,
                'delivery_time' => $this->delivery_time,
                'completed_at' => current_time('mysql')
            ],
            ['webhook_id' => $webhook->getId(), 'status' => 'pending'],
            ['%s', '%s', '%d', '%f', '%s'],
            ['%d', '%s']
        );
    }
    
    /**
     * Get response
     */
    public function getResponse() {
        return $this->response;
    }
    
    /**
     * Get delivery time
     */
    public function getDeliveryTime() {
        return $this->delivery_time;
    }
    
    /**
     * Check if successful
     */
    public function isSuccessful() {
        return $this->successful;
    }
}

/**
 * Batch Delivery Engine
 */
class BatchDeliveryEngine {
    
    private $queue = [];
    private $results = [];
    
    /**
     * Add to queue
     */
    public function addToQueue($webhook, $event) {
        $this->queue[] = [
            'webhook' => $webhook,
            'event' => $event
        ];
    }
    
    /**
     * Process queue
     */
    public function process() {
        $engine = new DeliveryEngine();
        
        foreach ($this->queue as $item) {
            $success = $engine->send($item['webhook'], $item['event']);
            
            $this->results[] = [
                'webhook_id' => $item['webhook']->getId(),
                'event' => $item['event']->getName(),
                'success' => $success,
                'response' => $engine->getResponse(),
                'delivery_time' => $engine->getDeliveryTime()
            ];
        }
        
        // Clear queue
        $this->queue = [];
        
        return $this->results;
    }
    
    /**
     * Get results
     */
    public function getResults() {
        return $this->results;
    }
}