<?php
/**
 * Webhook Core Interfaces
 * 
 * @package MoneyQuiz\Webhooks
 * @version 1.0.0
 */

namespace MoneyQuiz\Webhooks;

/**
 * Webhook Interface
 */
interface WebhookInterface {
    public function trigger($event, $data);
    public function validate();
    public function getUrl();
    public function getEvents();
    public function isActive();
}

/**
 * Webhook Event Interface
 */
interface WebhookEventInterface {
    public function getName();
    public function getPayload();
    public function getTimestamp();
    public function serialize();
}

/**
 * Webhook Delivery Interface
 */
interface WebhookDeliveryInterface {
    public function send($webhook, $event);
    public function getResponse();
    public function getDeliveryTime();
    public function isSuccessful();
}

/**
 * Webhook Storage Interface
 */
interface WebhookStorageInterface {
    public function save($webhook);
    public function get($id);
    public function getAll();
    public function delete($id);
    public function getByEvent($event);
}

/**
 * Base Webhook Class
 */
abstract class WebhookBase implements WebhookInterface {
    
    protected $id;
    protected $url;
    protected $events = [];
    protected $secret;
    protected $active = true;
    protected $headers = [];
    protected $created_at;
    protected $updated_at;
    
    /**
     * Constructor
     */
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Trigger webhook
     */
    public function trigger($event, $data) {
        if (!$this->isActive() || !in_array($event, $this->events)) {
            return false;
        }
        
        $event_obj = new WebhookEvent($event, $data);
        $delivery = new WebhookDelivery();
        
        return $delivery->send($this, $event_obj);
    }
    
    /**
     * Validate webhook
     */
    public function validate() {
        if (empty($this->url)) {
            throw new \Exception('Webhook URL is required');
        }
        
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid webhook URL');
        }
        
        if (empty($this->events)) {
            throw new \Exception('At least one event must be specified');
        }
        
        return true;
    }
    
    /**
     * Generate signature
     */
    public function generateSignature($payload) {
        if (empty($this->secret)) {
            return '';
        }
        
        return hash_hmac('sha256', $payload, $this->secret);
    }
    
    /**
     * Get headers
     */
    public function getHeaders($event, $signature = '') {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'MoneyQuiz-Webhook/1.0',
            'X-Webhook-Event' => $event,
            'X-Webhook-Timestamp' => time()
        ], $this->headers);
        
        if ($signature) {
            $headers['X-Webhook-Signature'] = $signature;
        }
        
        return $headers;
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getUrl() { return $this->url; }
    public function getEvents() { return $this->events; }
    public function isActive() { return $this->active; }
    public function getSecret() { return $this->secret; }
}

/**
 * Webhook Event Class
 */
class WebhookEvent implements WebhookEventInterface {
    
    private $name;
    private $payload;
    private $timestamp;
    private $id;
    
    public function __construct($name, $payload) {
        $this->name = $name;
        $this->payload = $payload;
        $this->timestamp = time();
        $this->id = wp_generate_uuid4();
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getPayload() {
        return $this->payload;
    }
    
    public function getTimestamp() {
        return $this->timestamp;
    }
    
    public function serialize() {
        return json_encode([
            'id' => $this->id,
            'event' => $this->name,
            'data' => $this->payload,
            'timestamp' => $this->timestamp
        ]);
    }
}

/**
 * Webhook Delivery (stub for interface)
 */
class WebhookDelivery implements WebhookDeliveryInterface {
    public function send($webhook, $event) { return true; }
    public function getResponse() { return []; }
    public function getDeliveryTime() { return 0; }
    public function isSuccessful() { return true; }
}