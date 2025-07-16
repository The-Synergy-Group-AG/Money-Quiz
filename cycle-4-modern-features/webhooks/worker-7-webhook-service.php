<?php
/**
 * Money Quiz Plugin - Webhook Service
 * Worker 7: Webhook Integration
 * 
 * Provides comprehensive webhook functionality for integrating with
 * external services and automating workflows.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Models\Settings;
use MoneyQuiz\Models\ActivityLog;
use MoneyQuiz\Utilities\SecurityUtil;
use MoneyQuiz\Utilities\DebugUtil;
use Exception;

/**
 * Webhook Service Class
 * 
 * Handles all webhook functionality
 */
class WebhookService {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Registered webhooks
     * 
     * @var array
     */
    protected $webhooks = array();
    
    /**
     * Default timeout for webhook requests
     * 
     * @var int
     */
    protected $timeout = 30;
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     */
    public function __construct( DatabaseService $database ) {
        $this->database = $database;
        $this->load_webhooks();
        
        // Register webhook events
        $this->register_webhook_events();
        
        // Add incoming webhook endpoint
        add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
    }
    
    /**
     * Register webhook events
     */
    protected function register_webhook_events() {
        // Quiz events
        add_action( 'money_quiz_completed', array( $this, 'trigger_quiz_completed' ), 10, 3 );
        add_action( 'money_quiz_started', array( $this, 'trigger_quiz_started' ), 10, 2 );
        add_action( 'money_quiz_abandoned', array( $this, 'trigger_quiz_abandoned' ), 10, 2 );
        
        // Prospect events
        add_action( 'money_quiz_prospect_created', array( $this, 'trigger_prospect_created' ), 10, 1 );
        add_action( 'money_quiz_prospect_updated', array( $this, 'trigger_prospect_updated' ), 10, 2 );
        
        // Email events
        add_action( 'money_quiz_email_sent', array( $this, 'trigger_email_sent' ), 10, 2 );
        add_action( 'money_quiz_email_opened', array( $this, 'trigger_email_opened' ), 10, 1 );
        add_action( 'money_quiz_email_clicked', array( $this, 'trigger_email_clicked' ), 10, 2 );
        
        // Custom events
        add_action( 'money_quiz_custom_event', array( $this, 'trigger_custom_event' ), 10, 2 );
    }
    
    /**
     * Create webhook
     * 
     * @param array $config Webhook configuration
     * @return int Webhook ID
     * @throws Exception
     */
    public function create_webhook( array $config ) {
        $defaults = array(
            'name' => '',
            'url' => '',
            'events' => array(),
            'headers' => array(),
            'secret' => '',
            'method' => 'POST',
            'format' => 'json',
            'retry_count' => 3,
            'retry_delay' => 60,
            'is_active' => true,
            'filters' => array(),
            'transform' => ''
        );
        
        $config = wp_parse_args( $config, $defaults );
        
        // Validate configuration
        $this->validate_webhook_config( $config );
        
        // Generate secret if not provided
        if ( empty( $config['secret'] ) ) {
            $config['secret'] = SecurityUtil::generate_token( 32 );
        }
        
        // Insert webhook
        $webhook_id = $this->database->insert( 'webhooks', array(
            'name' => $config['name'],
            'url' => $config['url'],
            'events' => json_encode( $config['events'] ),
            'headers' => json_encode( $config['headers'] ),
            'secret' => $config['secret'],
            'method' => $config['method'],
            'format' => $config['format'],
            'retry_count' => $config['retry_count'],
            'retry_delay' => $config['retry_delay'],
            'is_active' => $config['is_active'] ? 1 : 0,
            'filters' => json_encode( $config['filters'] ),
            'transform' => $config['transform'],
            'created_by' => get_current_user_id()
        ));
        
        if ( ! $webhook_id ) {
            throw new Exception( __( 'Failed to create webhook', 'money-quiz' ) );
        }
        
        // Reload webhooks
        $this->load_webhooks();
        
        // Log creation
        ActivityLog::log( 'webhook_created', array(
            'webhook_id' => $webhook_id,
            'name' => $config['name'],
            'url' => $config['url']
        ));
        
        return $webhook_id;
    }
    
    /**
     * Send webhook
     * 
     * @param int   $webhook_id Webhook ID
     * @param array $data Data to send
     * @param array $context Event context
     * @return bool Success
     */
    public function send_webhook( $webhook_id, array $data, array $context = array() ) {
        $webhook = $this->get_webhook( $webhook_id );
        
        if ( ! $webhook || ! $webhook['is_active'] ) {
            return false;
        }
        
        // Apply filters
        if ( ! $this->apply_webhook_filters( $webhook['filters'], $data, $context ) ) {
            return false;
        }
        
        // Transform data if needed
        if ( ! empty( $webhook['transform'] ) ) {
            $data = $this->transform_webhook_data( $data, $webhook['transform'] );
        }
        
        // Prepare payload
        $payload = $this->prepare_payload( $data, $webhook['format'] );
        
        // Add signature
        $signature = $this->generate_signature( $payload, $webhook['secret'] );
        
        // Prepare headers
        $headers = $this->prepare_headers( $webhook, $signature );
        
        // Send request
        $response = $this->send_request( 
            $webhook['url'], 
            $webhook['method'], 
            $payload, 
            $headers 
        );
        
        // Log delivery
        $this->log_webhook_delivery( $webhook_id, $data, $response );
        
        // Handle retry if failed
        if ( ! $response['success'] && $webhook['retry_count'] > 0 ) {
            $this->schedule_retry( $webhook_id, $data, $context, 1 );
        }
        
        return $response['success'];
    }
    
    /**
     * Trigger quiz completed webhook
     * 
     * @param int $result_id Quiz result ID
     * @param int $prospect_id Prospect ID
     * @param int $archetype_id Archetype ID
     */
    public function trigger_quiz_completed( $result_id, $prospect_id, $archetype_id ) {
        $data = $this->prepare_quiz_completed_data( $result_id, $prospect_id, $archetype_id );
        $this->trigger_webhooks( 'quiz.completed', $data );
    }
    
    /**
     * Trigger webhooks for event
     * 
     * @param string $event Event name
     * @param array  $data Event data
     * @param array  $context Additional context
     */
    protected function trigger_webhooks( $event, array $data, array $context = array() ) {
        foreach ( $this->webhooks as $webhook ) {
            if ( ! $webhook['is_active'] ) {
                continue;
            }
            
            $events = json_decode( $webhook['events'], true ) ?? array();
            
            if ( in_array( $event, $events ) || in_array( '*', $events ) ) {
                // Queue webhook for async delivery
                $this->queue_webhook( $webhook['id'], $event, $data, $context );
            }
        }
    }
    
    /**
     * Queue webhook for delivery
     * 
     * @param int    $webhook_id Webhook ID
     * @param string $event Event name
     * @param array  $data Event data
     * @param array  $context Context
     */
    protected function queue_webhook( $webhook_id, $event, array $data, array $context = array() ) {
        $queue_id = $this->database->insert( 'webhook_queue', array(
            'webhook_id' => $webhook_id,
            'event' => $event,
            'data' => json_encode( $data ),
            'context' => json_encode( $context ),
            'status' => 'pending',
            'attempts' => 0,
            'scheduled_at' => current_time( 'mysql' )
        ));
        
        // Process immediately or schedule
        if ( apply_filters( 'money_quiz_webhook_async', true ) ) {
            wp_schedule_single_event( time(), 'money_quiz_process_webhook', array( $queue_id ) );
        } else {
            $this->process_queued_webhook( $queue_id );
        }
    }
    
    /**
     * Process queued webhook
     * 
     * @param int $queue_id Queue ID
     */
    public function process_queued_webhook( $queue_id ) {
        $item = $this->database->get_row( 'webhook_queue', array(
            'id' => $queue_id
        ));
        
        if ( ! $item || $item->status !== 'pending' ) {
            return;
        }
        
        // Update status
        $this->database->update( 'webhook_queue',
            array( 
                'status' => 'processing',
                'attempts' => $item->attempts + 1,
                'last_attempt' => current_time( 'mysql' )
            ),
            array( 'id' => $queue_id )
        );
        
        // Send webhook
        $data = json_decode( $item->data, true );
        $context = json_decode( $item->context, true );
        
        $success = $this->send_webhook( $item->webhook_id, array_merge( $data, array(
            'event' => $item->event,
            'timestamp' => current_time( 'c' )
        )), $context );
        
        // Update status
        $this->database->update( 'webhook_queue',
            array( 'status' => $success ? 'delivered' : 'failed' ),
            array( 'id' => $queue_id )
        );
    }
    
    /**
     * Register incoming webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route( 'money-quiz/v1', '/webhook/(?P<key>[a-zA-Z0-9-_]+)', array(
            'methods' => array( 'POST', 'GET' ),
            'callback' => array( $this, 'handle_incoming_webhook' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'key' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return preg_match( '/^[a-zA-Z0-9-_]+$/', $param );
                    }
                )
            )
        ));
    }
    
    /**
     * Handle incoming webhook request
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function handle_incoming_webhook( $request ) {
        $key = $request->get_param( 'key' );
        
        // Find webhook endpoint by key
        $endpoint = $this->database->get_row( 'webhook_endpoints', array(
            'key' => $key,
            'is_active' => 1
        ));
        
        if ( ! $endpoint ) {
            return new \WP_REST_Response( array(
                'error' => 'Invalid webhook key'
            ), 404 );
        }
        
        // Verify signature if required
        if ( ! empty( $endpoint->secret ) ) {
            $signature = $request->get_header( 'X-Webhook-Signature' );
            
            if ( ! $this->verify_incoming_signature( $request, $endpoint->secret, $signature ) ) {
                return new \WP_REST_Response( array(
                    'error' => 'Invalid signature'
                ), 401 );
            }
        }
        
        // Process webhook
        try {
            $result = $this->process_incoming_webhook( $endpoint, $request );
            
            // Log incoming webhook
            $this->log_incoming_webhook( $endpoint->id, $request, $result );
            
            return new \WP_REST_Response( $result, 200 );
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'Incoming webhook error: ' . $e->getMessage(), 'error' );
            
            return new \WP_REST_Response( array(
                'error' => 'Webhook processing failed'
            ), 500 );
        }
    }
    
    /**
     * Process incoming webhook
     * 
     * @param object           $endpoint Endpoint configuration
     * @param \WP_REST_Request $request Request object
     * @return array Response data
     */
    protected function process_incoming_webhook( $endpoint, $request ) {
        $data = $request->get_json_params();
        
        // Apply transformation if configured
        if ( ! empty( $endpoint->transform ) ) {
            $data = $this->transform_incoming_data( $data, $endpoint->transform );
        }
        
        // Trigger action for processing
        $result = apply_filters( 
            'money_quiz_process_incoming_webhook',
            array( 'success' => true ),
            $endpoint->type,
            $data,
            $endpoint
        );
        
        // Handle specific webhook types
        switch ( $endpoint->type ) {
            case 'payment':
                $result = $this->process_payment_webhook( $data );
                break;
                
            case 'email':
                $result = $this->process_email_webhook( $data );
                break;
                
            case 'crm':
                $result = $this->process_crm_webhook( $data );
                break;
                
            case 'custom':
                $result = apply_filters( 
                    'money_quiz_custom_webhook_' . $endpoint->key,
                    $result,
                    $data
                );
                break;
        }
        
        return $result;
    }
    
    /**
     * Create webhook endpoint for receiving
     * 
     * @param array $config Endpoint configuration
     * @return array Endpoint details
     */
    public function create_webhook_endpoint( array $config ) {
        $defaults = array(
            'name' => '',
            'type' => 'custom',
            'secret' => '',
            'transform' => '',
            'is_active' => true
        );
        
        $config = wp_parse_args( $config, $defaults );
        
        // Generate unique key
        $key = $this->generate_endpoint_key( $config['name'] );
        
        // Generate secret if not provided
        if ( empty( $config['secret'] ) ) {
            $config['secret'] = SecurityUtil::generate_token( 32 );
        }
        
        // Insert endpoint
        $endpoint_id = $this->database->insert( 'webhook_endpoints', array(
            'key' => $key,
            'name' => $config['name'],
            'type' => $config['type'],
            'secret' => $config['secret'],
            'transform' => $config['transform'],
            'is_active' => $config['is_active'] ? 1 : 0,
            'created_by' => get_current_user_id()
        ));
        
        if ( ! $endpoint_id ) {
            throw new Exception( __( 'Failed to create webhook endpoint', 'money-quiz' ) );
        }
        
        return array(
            'id' => $endpoint_id,
            'key' => $key,
            'url' => rest_url( 'money-quiz/v1/webhook/' . $key ),
            'secret' => $config['secret']
        );
    }
    
    /**
     * Get webhook statistics
     * 
     * @param int $webhook_id Webhook ID (optional)
     * @return array Statistics
     */
    public function get_webhook_statistics( $webhook_id = null ) {
        $stats = array();
        
        if ( $webhook_id ) {
            // Single webhook stats
            $stats = $this->get_single_webhook_stats( $webhook_id );
        } else {
            // Overall stats
            $stats = array(
                'total_webhooks' => count( $this->webhooks ),
                'active_webhooks' => count( array_filter( $this->webhooks, function( $w ) {
                    return $w['is_active'];
                })),
                'deliveries_24h' => $this->get_delivery_count( '-24 hours' ),
                'failures_24h' => $this->get_failure_count( '-24 hours' ),
                'average_response_time' => $this->get_average_response_time(),
                'popular_events' => $this->get_popular_events()
            );
        }
        
        return $stats;
    }
    
    /**
     * Test webhook
     * 
     * @param int   $webhook_id Webhook ID
     * @param array $test_data Test data
     * @return array Test result
     */
    public function test_webhook( $webhook_id, array $test_data = array() ) {
        $webhook = $this->get_webhook( $webhook_id );
        
        if ( ! $webhook ) {
            return array(
                'success' => false,
                'error' => __( 'Webhook not found', 'money-quiz' )
            );
        }
        
        // Use test data or generate sample
        if ( empty( $test_data ) ) {
            $test_data = $this->generate_test_data( $webhook['events'] );
        }
        
        // Send test webhook
        $start_time = microtime( true );
        $response = $this->send_test_webhook( $webhook, $test_data );
        $duration = round( ( microtime( true ) - $start_time ) * 1000 );
        
        return array(
            'success' => $response['success'],
            'status_code' => $response['status_code'] ?? 0,
            'response_time' => $duration,
            'response_body' => $response['body'] ?? '',
            'error' => $response['error'] ?? ''
        );
    }
    
    /**
     * Load webhooks from database
     */
    protected function load_webhooks() {
        $this->webhooks = $this->database->get_results( 'webhooks', array(
            'orderby' => 'created_at',
            'order' => 'DESC'
        ), ARRAY_A );
    }
    
    /**
     * Get webhook by ID
     * 
     * @param int $webhook_id Webhook ID
     * @return array|null Webhook data
     */
    protected function get_webhook( $webhook_id ) {
        foreach ( $this->webhooks as $webhook ) {
            if ( $webhook['id'] == $webhook_id ) {
                return $webhook;
            }
        }
        
        return null;
    }
    
    /**
     * Validate webhook configuration
     * 
     * @param array $config Configuration
     * @throws Exception
     */
    protected function validate_webhook_config( array $config ) {
        if ( empty( $config['name'] ) ) {
            throw new Exception( __( 'Webhook name is required', 'money-quiz' ) );
        }
        
        if ( empty( $config['url'] ) || ! filter_var( $config['url'], FILTER_VALIDATE_URL ) ) {
            throw new Exception( __( 'Valid webhook URL is required', 'money-quiz' ) );
        }
        
        if ( empty( $config['events'] ) ) {
            throw new Exception( __( 'At least one event must be selected', 'money-quiz' ) );
        }
        
        $allowed_methods = array( 'POST', 'PUT', 'PATCH', 'GET' );
        if ( ! in_array( $config['method'], $allowed_methods ) ) {
            throw new Exception( __( 'Invalid HTTP method', 'money-quiz' ) );
        }
    }
    
    /**
     * Apply webhook filters
     * 
     * @param string $filters JSON filters
     * @param array  $data Event data
     * @param array  $context Event context
     * @return bool Pass filters
     */
    protected function apply_webhook_filters( $filters, array $data, array $context ) {
        $filters = json_decode( $filters, true );
        
        if ( empty( $filters ) ) {
            return true;
        }
        
        foreach ( $filters as $filter ) {
            if ( ! $this->evaluate_filter( $filter, $data, $context ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Transform webhook data
     * 
     * @param array  $data Original data
     * @param string $transform Transform script
     * @return array Transformed data
     */
    protected function transform_webhook_data( array $data, $transform ) {
        // Simple key mapping transform
        if ( is_array( $transform_rules = json_decode( $transform, true ) ) ) {
            $transformed = array();
            
            foreach ( $transform_rules as $new_key => $old_key ) {
                $transformed[ $new_key ] = ArrayUtil::get( $data, $old_key );
            }
            
            return $transformed;
        }
        
        // Custom transform function
        return apply_filters( 'money_quiz_webhook_transform', $data, $transform );
    }
    
    /**
     * Prepare payload
     * 
     * @param array  $data Data to send
     * @param string $format Format (json, form, xml)
     * @return string Formatted payload
     */
    protected function prepare_payload( array $data, $format ) {
        switch ( $format ) {
            case 'json':
                return json_encode( $data );
                
            case 'form':
                return http_build_query( $data );
                
            case 'xml':
                return $this->array_to_xml( $data );
                
            default:
                return json_encode( $data );
        }
    }
    
    /**
     * Generate webhook signature
     * 
     * @param string $payload Payload data
     * @param string $secret Webhook secret
     * @return string Signature
     */
    protected function generate_signature( $payload, $secret ) {
        return hash_hmac( 'sha256', $payload, $secret );
    }
    
    /**
     * Prepare headers
     * 
     * @param array  $webhook Webhook configuration
     * @param string $signature Payload signature
     * @return array Headers
     */
    protected function prepare_headers( array $webhook, $signature ) {
        $headers = array(
            'X-Money-Quiz-Event' => $webhook['current_event'] ?? 'custom',
            'X-Money-Quiz-Signature' => $signature,
            'X-Money-Quiz-Webhook-ID' => $webhook['id']
        );
        
        // Add content type
        switch ( $webhook['format'] ) {
            case 'json':
                $headers['Content-Type'] = 'application/json';
                break;
            case 'form':
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                break;
            case 'xml':
                $headers['Content-Type'] = 'application/xml';
                break;
        }
        
        // Add custom headers
        $custom_headers = json_decode( $webhook['headers'], true ) ?? array();
        $headers = array_merge( $headers, $custom_headers );
        
        return $headers;
    }
    
    /**
     * Send HTTP request
     * 
     * @param string $url URL
     * @param string $method HTTP method
     * @param string $payload Payload
     * @param array  $headers Headers
     * @return array Response data
     */
    protected function send_request( $url, $method, $payload, array $headers ) {
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'body' => $payload,
            'timeout' => $this->timeout,
            'sslverify' => true,
            'data_format' => 'body'
        );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        return array(
            'success' => $status_code >= 200 && $status_code < 300,
            'status_code' => $status_code,
            'body' => $body,
            'headers' => wp_remote_retrieve_headers( $response )
        );
    }
    
    /**
     * Log webhook delivery
     * 
     * @param int   $webhook_id Webhook ID
     * @param array $data Sent data
     * @param array $response Response data
     */
    protected function log_webhook_delivery( $webhook_id, array $data, array $response ) {
        $this->database->insert( 'webhook_logs', array(
            'webhook_id' => $webhook_id,
            'event' => $data['event'] ?? 'custom',
            'status' => $response['success'] ? 'success' : 'failed',
            'status_code' => $response['status_code'] ?? 0,
            'request_data' => json_encode( $data ),
            'response_data' => json_encode( array(
                'body' => substr( $response['body'] ?? '', 0, 1000 ),
                'headers' => $response['headers'] ?? array()
            )),
            'error' => $response['error'] ?? '',
            'created_at' => current_time( 'mysql' )
        ));
    }
    
    /**
     * Schedule webhook retry
     * 
     * @param int   $webhook_id Webhook ID
     * @param array $data Event data
     * @param array $context Context
     * @param int   $attempt Attempt number
     */
    protected function schedule_retry( $webhook_id, array $data, array $context, $attempt ) {
        $webhook = $this->get_webhook( $webhook_id );
        
        if ( ! $webhook || $attempt > $webhook['retry_count'] ) {
            return;
        }
        
        $delay = $webhook['retry_delay'] * $attempt;
        
        wp_schedule_single_event( 
            time() + $delay,
            'money_quiz_webhook_retry',
            array( $webhook_id, $data, $context, $attempt )
        );
    }
    
    /**
     * Generate endpoint key
     * 
     * @param string $name Endpoint name
     * @return string Unique key
     */
    protected function generate_endpoint_key( $name ) {
        $base_key = sanitize_title( $name );
        $key = $base_key;
        $counter = 1;
        
        while ( $this->database->exists( 'webhook_endpoints', array( 'key' => $key ) ) ) {
            $key = $base_key . '-' . $counter;
            $counter++;
        }
        
        return $key;
    }
}

/**
 * Webhook Manager Class
 * 
 * Handles admin interface for webhooks
 */
class WebhookManager {
    
    /**
     * Webhook service
     * 
     * @var WebhookService
     */
    protected $webhook_service;
    
    /**
     * Constructor
     * 
     * @param WebhookService $webhook_service
     */
    public function __construct( WebhookService $webhook_service ) {
        $this->webhook_service = $webhook_service;
        
        // Register hooks
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_money_quiz_create_webhook', array( $this, 'ajax_create_webhook' ) );
        add_action( 'wp_ajax_money_quiz_test_webhook', array( $this, 'ajax_test_webhook' ) );
        add_action( 'wp_ajax_money_quiz_get_webhook_logs', array( $this, 'ajax_get_logs' ) );
    }
    
    /**
     * Add webhooks menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'money-quiz',
            __( 'Webhooks', 'money-quiz' ),
            __( 'Webhooks', 'money-quiz' ),
            'manage_options',
            'money-quiz-webhooks',
            array( $this, 'render_page' )
        );
    }
    
    /**
     * Render webhooks page
     */
    public function render_page() {
        ?>
        <div class="wrap money-quiz-webhooks">
            <h1>
                <?php _e( 'Webhooks', 'money-quiz' ); ?>
                <a href="#" class="page-title-action" id="create-webhook">
                    <?php _e( 'Add Webhook', 'money-quiz' ); ?>
                </a>
                <a href="#" class="page-title-action" id="create-endpoint">
                    <?php _e( 'Create Endpoint', 'money-quiz' ); ?>
                </a>
            </h1>
            
            <div class="webhook-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#outgoing" class="nav-tab nav-tab-active">
                        <?php _e( 'Outgoing Webhooks', 'money-quiz' ); ?>
                    </a>
                    <a href="#incoming" class="nav-tab">
                        <?php _e( 'Incoming Endpoints', 'money-quiz' ); ?>
                    </a>
                    <a href="#logs" class="nav-tab">
                        <?php _e( 'Logs', 'money-quiz' ); ?>
                    </a>
                </h2>
                
                <div class="tab-content">
                    <!-- Tab content loaded via JavaScript -->
                </div>
            </div>
        </div>
        <?php
    }
}