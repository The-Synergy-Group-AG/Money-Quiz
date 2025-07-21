<?php
/**
 * Webhook System Loader
 * 
 * @package MoneyQuiz\Webhooks
 * @version 1.0.0
 */

namespace MoneyQuiz\Webhooks;

// Load webhook components
require_once __DIR__ . '/webhook-1-interfaces.php';
require_once __DIR__ . '/webhook-2-event-manager.php';
require_once __DIR__ . '/webhook-3-delivery-engine.php';
require_once __DIR__ . '/webhook-4-retry-logic.php';

/**
 * Webhook Manager
 */
class WebhookManager {
    
    private static $instance = null;
    private $storage;
    private $event_manager;
    private $retry_manager;
    
    private function __construct() {
        $this->event_manager = EventManager::getInstance();
        $this->retry_manager = RetryManager::getInstance();
        $this->storage = new WebhookStorage();
    }
    
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
     * Initialize webhook system
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Initialize components
        EventManager::init();
        RetryManager::init();
        
        // Register webhook listener
        $instance->event_manager->addListener('*', [$instance, 'handleEvent']);
        
        // Add admin menu
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Add REST endpoints
        add_action('rest_api_init', [$instance, 'registerRestEndpoints']);
        
        // Create database tables
        add_action('plugins_loaded', [$instance, 'createTables']);
    }
    
    /**
     * Handle webhook event
     */
    public function handleEvent($event, $data) {
        $webhooks = $this->storage->getByEvent($event);
        
        if (empty($webhooks)) {
            return;
        }
        
        $batch = new BatchDeliveryEngine();
        $event_obj = new WebhookEvent($event, $data);
        
        foreach ($webhooks as $webhook) {
            if ($webhook->isActive()) {
                $batch->addToQueue($webhook, $event_obj);
            }
        }
        
        // Process batch
        $results = $batch->process();
        
        // Handle failures
        foreach ($results as $result) {
            if (!$result['success']) {
                $webhook = $this->storage->get($result['webhook_id']);
                $this->retry_manager->queueForRetry($webhook, $event_obj);
            }
        }
    }
    
    /**
     * Register webhook
     */
    public function registerWebhook($url, $events, $options = []) {
        $webhook = new Webhook(array_merge([
            'url' => $url,
            'events' => (array) $events,
            'secret' => wp_generate_password(32, false),
            'active' => true
        ], $options));
        
        $webhook->validate();
        
        return $this->storage->save($webhook);
    }
    
    /**
     * Get webhook
     */
    public function getWebhook($id) {
        return $this->storage->get($id);
    }
    
    /**
     * Update webhook
     */
    public function updateWebhook($id, $data) {
        $webhook = $this->storage->get($id);
        
        if (!$webhook) {
            throw new \Exception('Webhook not found');
        }
        
        foreach ($data as $key => $value) {
            if (property_exists($webhook, $key)) {
                $webhook->$key = $value;
            }
        }
        
        $webhook->validate();
        
        return $this->storage->save($webhook);
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook($id) {
        return $this->storage->delete($id);
    }
    
    /**
     * Test webhook
     */
    public function testWebhook($id) {
        $webhook = $this->storage->get($id);
        
        if (!$webhook) {
            throw new \Exception('Webhook not found');
        }
        
        $test_event = new WebhookEvent('test', [
            'message' => 'This is a test webhook delivery',
            'timestamp' => time()
        ]);
        
        $engine = new DeliveryEngine();
        return $engine->send($webhook, $test_event);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'money-quiz',
            'Webhooks',
            'Webhooks',
            'manage_options',
            'money-quiz-webhooks',
            [$this, 'renderAdminPage']
        );
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        include __DIR__ . '/views/webhook-admin.php';
    }
    
    /**
     * Register REST endpoints
     */
    public function registerRestEndpoints() {
        register_rest_route('money-quiz/v1', '/webhooks', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getWebhooksEndpoint'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createWebhookEndpoint'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                }
            ]
        ]);
    }
    
    /**
     * Get webhooks endpoint
     */
    public function getWebhooksEndpoint() {
        $webhooks = $this->storage->getAll();
        return rest_ensure_response(['webhooks' => $webhooks]);
    }
    
    /**
     * Create webhook endpoint
     */
    public function createWebhookEndpoint($request) {
        try {
            $webhook_id = $this->registerWebhook(
                $request->get_param('url'),
                $request->get_param('events'),
                $request->get_params()
            );
            
            return rest_ensure_response([
                'success' => true,
                'webhook_id' => $webhook_id
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('webhook_error', $e->getMessage(), ['status' => 400]);
        }
    }
    
    /**
     * Create database tables
     */
    public function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Webhooks table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_webhooks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            events text NOT NULL,
            secret varchar(64),
            headers text,
            active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY (id),
            KEY active (active)
        ) $charset_collate;";
        
        // Webhook logs table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_webhook_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            webhook_id bigint(20) NOT NULL,
            event varchar(50) NOT NULL,
            url varchar(255) NOT NULL,
            payload longtext,
            response longtext,
            response_code int(3),
            status varchar(20),
            delivery_time float,
            created_at datetime NOT NULL,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY event (event),
            KEY status (status)
        ) $charset_collate;";
        
        // Retry queue table
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_webhook_retries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            webhook_id bigint(20) NOT NULL,
            event varchar(50) NOT NULL,
            payload longtext,
            attempt int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            retry_at datetime NOT NULL,
            created_at datetime NOT NULL,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY status_retry (status, retry_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }
}

/**
 * Webhook Storage Implementation
 */
class WebhookStorage implements WebhookStorageInterface {
    
    public function save($webhook) {
        global $wpdb;
        
        $data = [
            'url' => $webhook->getUrl(),
            'events' => json_encode($webhook->getEvents()),
            'secret' => $webhook->getSecret(),
            'headers' => json_encode($webhook->headers ?? []),
            'active' => $webhook->isActive() ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];
        
        if ($webhook->getId()) {
            $wpdb->update(
                $wpdb->prefix . 'money_quiz_webhooks',
                $data,
                ['id' => $webhook->getId()]
            );
            return $webhook->getId();
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'money_quiz_webhooks', $data);
            return $wpdb->insert_id;
        }
    }
    
    public function get($id) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_webhooks WHERE id = %d",
            $id
        ));
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateWebhook($row);
    }
    
    public function getAll() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}money_quiz_webhooks ORDER BY created_at DESC"
        );
        
        return array_map([$this, 'hydrateWebhook'], $results);
    }
    
    public function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'money_quiz_webhooks',
            ['id' => $id]
        );
    }
    
    public function getByEvent($event) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}money_quiz_webhooks WHERE active = 1"
        );
        
        $webhooks = [];
        foreach ($results as $row) {
            $webhook = $this->hydrateWebhook($row);
            if (in_array($event, $webhook->getEvents())) {
                $webhooks[] = $webhook;
            }
        }
        
        return $webhooks;
    }
    
    private function hydrateWebhook($row) {
        return new Webhook([
            'id' => $row->id,
            'url' => $row->url,
            'events' => json_decode($row->events, true),
            'secret' => $row->secret,
            'headers' => json_decode($row->headers, true),
            'active' => (bool) $row->active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at
        ]);
    }
}

/**
 * Concrete Webhook Class
 */
class Webhook extends WebhookBase {
    // Inherits all functionality from WebhookBase
}

// Initialize webhook system
add_action('plugins_loaded', [WebhookManager::class, 'init']);