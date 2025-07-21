<?php
/**
 * Money Quiz Plugin - Notification Service
 * Worker 9: Real-time Notifications
 * 
 * Provides comprehensive real-time notification capabilities including
 * browser push notifications, email alerts, and in-app notifications.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Models\Settings;
use MoneyQuiz\Models\Prospect;
use MoneyQuiz\Utilities\SecurityUtil;
use MoneyQuiz\Utilities\DebugUtil;
use MoneyQuiz\Utilities\CacheUtil;

/**
 * Notification Service Class
 * 
 * Handles all notification functionality
 */
class NotificationService {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Email service
     * 
     * @var EmailService
     */
    protected $email_service;
    
    /**
     * Active connections for SSE
     * 
     * @var array
     */
    protected $connections = array();
    
    /**
     * Notification channels
     * 
     * @var array
     */
    protected $channels = array(
        'browser' => true,
        'email' => true,
        'in_app' => true,
        'sms' => false,
        'webhook' => true
    );
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     * @param EmailService   $email_service
     */
    public function __construct( DatabaseService $database, EmailService $email_service = null ) {
        $this->database = $database;
        $this->email_service = $email_service;
        
        $this->init_notification_system();
        
        // Register hooks
        add_action( 'init', array( $this, 'init_push_notifications' ) );
        add_action( 'rest_api_init', array( $this, 'register_api_endpoints' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        
        // Event listeners
        $this->register_event_listeners();
    }
    
    /**
     * Initialize notification system
     */
    protected function init_notification_system() {
        // Create notification tables if needed
        $this->create_notification_tables();
        
        // Load notification settings
        $this->load_notification_settings();
        
        // Initialize notification queue processor
        if ( ! wp_next_scheduled( 'money_quiz_process_notification_queue' ) ) {
            wp_schedule_event( time(), 'money_quiz_minute', 'money_quiz_process_notification_queue' );
        }
        
        add_action( 'money_quiz_process_notification_queue', array( $this, 'process_notification_queue' ) );
    }
    
    /**
     * Send notification
     * 
     * @param array $notification Notification data
     * @return bool Success
     */
    public function send_notification( array $notification ) {
        $defaults = array(
            'type' => 'info',
            'title' => '',
            'message' => '',
            'recipient' => null,
            'channels' => array( 'in_app' ),
            'data' => array(),
            'priority' => 'normal',
            'expires_at' => null,
            'actions' => array()
        );
        
        $notification = wp_parse_args( $notification, $defaults );
        
        // Validate notification
        if ( empty( $notification['title'] ) || empty( $notification['message'] ) ) {
            return false;
        }
        
        // Create notification record
        $notification_id = $this->create_notification_record( $notification );
        
        if ( ! $notification_id ) {
            return false;
        }
        
        // Send through requested channels
        $sent = false;
        foreach ( $notification['channels'] as $channel ) {
            if ( $this->is_channel_enabled( $channel ) ) {
                $method = 'send_' . $channel . '_notification';
                if ( method_exists( $this, $method ) ) {
                    $result = $this->$method( $notification_id, $notification );
                    if ( $result ) {
                        $sent = true;
                    }
                }
            }
        }
        
        return $sent;
    }
    
    /**
     * Send browser push notification
     * 
     * @param int   $notification_id Notification ID
     * @param array $notification Notification data
     * @return bool Success
     */
    protected function send_browser_notification( $notification_id, array $notification ) {
        // Get user's push subscription
        $subscription = $this->get_push_subscription( $notification['recipient'] );
        
        if ( ! $subscription ) {
            return false;
        }
        
        // Prepare push payload
        $payload = json_encode( array(
            'id' => $notification_id,
            'title' => $notification['title'],
            'body' => $notification['message'],
            'icon' => $this->get_notification_icon( $notification['type'] ),
            'badge' => MONEY_QUIZ_PLUGIN_URL . 'assets/images/badge.png',
            'tag' => 'money-quiz-' . $notification['type'],
            'data' => array_merge( $notification['data'], array(
                'id' => $notification_id,
                'type' => $notification['type'],
                'timestamp' => time()
            )),
            'actions' => $this->format_notification_actions( $notification['actions'] ),
            'requireInteraction' => $notification['priority'] === 'high'
        ));
        
        // Send via Web Push
        try {
            $result = $this->send_web_push( $subscription, $payload );
            
            if ( $result ) {
                $this->update_notification_status( $notification_id, 'browser', 'sent' );
                return true;
            }
        } catch ( \Exception $e ) {
            DebugUtil::log( 'Push notification error: ' . $e->getMessage(), 'error' );
        }
        
        return false;
    }
    
    /**
     * Send email notification
     * 
     * @param int   $notification_id Notification ID
     * @param array $notification Notification data
     * @return bool Success
     */
    protected function send_email_notification( $notification_id, array $notification ) {
        if ( ! $this->email_service ) {
            return false;
        }
        
        // Get recipient email
        $recipient_email = $this->get_recipient_email( $notification['recipient'] );
        
        if ( ! $recipient_email ) {
            return false;
        }
        
        // Check email notification preferences
        if ( ! $this->should_send_email_notification( $recipient_email, $notification['type'] ) ) {
            return false;
        }
        
        // Prepare email
        $email_data = array(
            'to' => $recipient_email,
            'subject' => $notification['title'],
            'template' => 'notification',
            'data' => array(
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'actions' => $notification['actions'],
                'notification_id' => $notification_id
            )
        );
        
        // Send email
        $result = $this->email_service->send_email( $email_data );
        
        if ( $result ) {
            $this->update_notification_status( $notification_id, 'email', 'sent' );
            return true;
        }
        
        return false;
    }
    
    /**
     * Send in-app notification
     * 
     * @param int   $notification_id Notification ID
     * @param array $notification Notification data
     * @return bool Success
     */
    protected function send_in_app_notification( $notification_id, array $notification ) {
        // Mark notification as available in app
        $this->update_notification_status( $notification_id, 'in_app', 'available' );
        
        // Broadcast to connected clients if real-time enabled
        if ( $this->is_realtime_enabled() ) {
            $this->broadcast_notification( $notification_id, $notification );
        }
        
        // Update notification count cache
        $this->update_notification_count( $notification['recipient'] );
        
        return true;
    }
    
    /**
     * Broadcast notification to connected clients
     * 
     * @param int   $notification_id Notification ID
     * @param array $notification Notification data
     */
    protected function broadcast_notification( $notification_id, array $notification ) {
        $event_data = json_encode( array(
            'event' => 'notification',
            'data' => array(
                'id' => $notification_id,
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'priority' => $notification['priority'],
                'actions' => $notification['actions'],
                'timestamp' => time()
            )
        ));
        
        // Send to WebSocket/SSE connections
        $this->send_sse_event( $notification['recipient'], $event_data );
        
        // Trigger WordPress action for other integrations
        do_action( 'money_quiz_notification_broadcast', $notification_id, $notification );
    }
    
    /**
     * Register API endpoints
     */
    public function register_api_endpoints() {
        // Subscribe to push notifications
        register_rest_route( 'money-quiz/v1', '/notifications/subscribe', array(
            'methods' => 'POST',
            'callback' => array( $this, 'api_subscribe_push' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
            'args' => array(
                'subscription' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_array( $param ) && isset( $param['endpoint'] );
                    }
                )
            )
        ));
        
        // Get notifications
        register_rest_route( 'money-quiz/v1', '/notifications', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_get_notifications' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
            'args' => array(
                'status' => array(
                    'default' => 'unread',
                    'enum' => array( 'all', 'unread', 'read' )
                ),
                'limit' => array(
                    'default' => 20,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0 && $param <= 100;
                    }
                )
            )
        ));
        
        // Mark notification as read
        register_rest_route( 'money-quiz/v1', '/notifications/(?P<id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array( $this, 'api_mark_read' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
            'args' => array(
                'id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    }
                )
            )
        ));
        
        // SSE endpoint for real-time notifications
        register_rest_route( 'money-quiz/v1', '/notifications/stream', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_notification_stream' ),
            'permission_callback' => array( $this, 'check_api_permission' )
        ));
    }
    
    /**
     * API: Subscribe to push notifications
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function api_subscribe_push( $request ) {
        $subscription = $request->get_param( 'subscription' );
        $user_identifier = $this->get_current_user_identifier();
        
        // Save subscription
        $result = $this->save_push_subscription( $user_identifier, $subscription );
        
        if ( $result ) {
            // Send welcome notification
            $this->send_notification( array(
                'type' => 'success',
                'title' => __( 'Notifications Enabled', 'money-quiz' ),
                'message' => __( 'You will now receive real-time updates!', 'money-quiz' ),
                'recipient' => $user_identifier,
                'channels' => array( 'browser' )
            ));
            
            return new \WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Successfully subscribed to notifications', 'money-quiz' )
            ));
        }
        
        return new \WP_REST_Response( array(
            'success' => false,
            'message' => __( 'Failed to subscribe to notifications', 'money-quiz' )
        ), 500 );
    }
    
    /**
     * API: Get notifications
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function api_get_notifications( $request ) {
        $user_identifier = $this->get_current_user_identifier();
        $status = $request->get_param( 'status' );
        $limit = $request->get_param( 'limit' );
        
        $notifications = $this->get_user_notifications( $user_identifier, $status, $limit );
        
        return new \WP_REST_Response( array(
            'notifications' => $notifications,
            'unread_count' => $this->get_unread_count( $user_identifier )
        ));
    }
    
    /**
     * API: Notification stream (SSE)
     * 
     * @param \WP_REST_Request $request
     */
    public function api_notification_stream( $request ) {
        // Set SSE headers
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );
        
        // Disable output buffering
        while ( ob_get_level() ) {
            ob_end_clean();
        }
        
        $user_identifier = $this->get_current_user_identifier();
        $last_event_id = $request->get_header( 'Last-Event-ID' );
        
        // Send initial connection event
        echo "event: connected\n";
        echo "data: " . json_encode( array( 'status' => 'connected' ) ) . "\n\n";
        flush();
        
        // Keep connection alive
        $start_time = time();
        $timeout = 30; // 30 seconds timeout
        
        while ( time() - $start_time < $timeout ) {
            // Check for new notifications
            $new_notifications = $this->get_new_notifications( $user_identifier, $last_event_id );
            
            foreach ( $new_notifications as $notification ) {
                echo "id: {$notification['id']}\n";
                echo "event: notification\n";
                echo "data: " . json_encode( $notification ) . "\n\n";
                flush();
                
                $last_event_id = $notification['id'];
            }
            
            // Send heartbeat
            echo "event: heartbeat\n";
            echo "data: " . json_encode( array( 'time' => time() ) ) . "\n\n";
            flush();
            
            // Sleep for 1 second
            sleep( 1 );
            
            // Check if client disconnected
            if ( connection_aborted() ) {
                break;
            }
        }
    }
    
    /**
     * Create notification tables
     */
    protected function create_notification_tables() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->database->get_table('notifications')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            recipient varchar(255) DEFAULT NULL,
            channels text,
            data longtext,
            priority varchar(20) DEFAULT 'normal',
            status varchar(20) DEFAULT 'pending',
            read_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY recipient (recipient),
            KEY status (status),
            KEY created_at (created_at)
        )";
        
        $this->database->create_table( 'notifications', $sql );
        
        // Push subscriptions table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->database->get_table('push_subscriptions')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_identifier varchar(255) NOT NULL,
            endpoint text NOT NULL,
            public_key varchar(255),
            auth_token varchar(255),
            user_agent varchar(255),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_endpoint (user_identifier, endpoint(255))
        )";
        
        $this->database->create_table( 'push_subscriptions', $sql );
        
        // Notification delivery status
        $sql = "CREATE TABLE IF NOT EXISTS {$this->database->get_table('notification_delivery')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) NOT NULL,
            channel varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            delivered_at datetime DEFAULT NULL,
            error_message text,
            PRIMARY KEY (id),
            KEY notification_id (notification_id),
            KEY channel_status (channel, status)
        )";
        
        $this->database->create_table( 'notification_delivery', $sql );
    }
    
    /**
     * Register event listeners for automatic notifications
     */
    protected function register_event_listeners() {
        // Quiz completion
        add_action( 'money_quiz_completed', array( $this, 'notify_quiz_completion' ), 10, 3 );
        
        // New prospect
        add_action( 'money_quiz_prospect_created', array( $this, 'notify_new_prospect' ), 10, 1 );
        
        // High score achieved
        add_action( 'money_quiz_high_score', array( $this, 'notify_high_score' ), 10, 2 );
        
        // System alerts
        add_action( 'money_quiz_system_alert', array( $this, 'notify_system_alert' ), 10, 2 );
        
        // Milestone reached
        add_action( 'money_quiz_milestone_reached', array( $this, 'notify_milestone' ), 10, 2 );
    }
    
    /**
     * Notify quiz completion
     * 
     * @param int $result_id Result ID
     * @param int $prospect_id Prospect ID
     * @param int $archetype_id Archetype ID
     */
    public function notify_quiz_completion( $result_id, $prospect_id, $archetype_id ) {
        // Notify admin
        $this->send_notification( array(
            'type' => 'info',
            'title' => __( 'New Quiz Completion', 'money-quiz' ),
            'message' => sprintf( 
                __( 'A user has completed the Money Quiz with result: %s', 'money-quiz' ),
                $this->get_archetype_name( $archetype_id )
            ),
            'recipient' => 'admin',
            'channels' => array( 'in_app', 'browser' ),
            'data' => array(
                'result_id' => $result_id,
                'prospect_id' => $prospect_id,
                'archetype_id' => $archetype_id
            ),
            'actions' => array(
                array(
                    'action' => 'view',
                    'title' => __( 'View Result', 'money-quiz' ),
                    'url' => admin_url( 'admin.php?page=money-quiz-results&id=' . $result_id )
                )
            )
        ));
        
        // Check for notification triggers
        $this->check_notification_triggers( 'quiz_completion', array(
            'result_id' => $result_id,
            'prospect_id' => $prospect_id,
            'archetype_id' => $archetype_id
        ));
    }
    
    /**
     * Send Web Push notification
     * 
     * @param array  $subscription Push subscription
     * @param string $payload Notification payload
     * @return bool Success
     */
    protected function send_web_push( $subscription, $payload ) {
        $auth = array(
            'VAPID' => array(
                'subject' => get_site_url(),
                'publicKey' => $this->get_vapid_public_key(),
                'privateKey' => $this->get_vapid_private_key()
            )
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
            'TTL' => 86400, // 24 hours
            'Urgency' => 'normal'
        );
        
        // Encrypt payload
        $encrypted = $this->encrypt_payload( 
            $payload, 
            $subscription['public_key'], 
            $subscription['auth_token'] 
        );
        
        // Send to push service
        $response = wp_remote_post( $subscription['endpoint'], array(
            'headers' => array_merge( $headers, $this->get_vapid_headers( $auth ) ),
            'body' => $encrypted,
            'timeout' => 10
        ));
        
        if ( is_wp_error( $response ) ) {
            throw new \Exception( $response->get_error_message() );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        // Handle response
        if ( $status_code === 201 || $status_code === 204 ) {
            return true;
        } elseif ( $status_code === 410 ) {
            // Subscription expired, remove it
            $this->remove_push_subscription( $subscription['id'] );
        }
        
        return false;
    }
    
    /**
     * Process notification queue
     */
    public function process_notification_queue() {
        $pending_notifications = $this->database->get_results( 'notifications', array(
            'where' => array( 'status' => 'pending' ),
            'orderby' => 'priority DESC, created_at ASC',
            'limit' => 50
        ));
        
        foreach ( $pending_notifications as $notification ) {
            $notification_data = array(
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'recipient' => $notification->recipient,
                'channels' => json_decode( $notification->channels, true ),
                'data' => json_decode( $notification->data, true ),
                'priority' => $notification->priority
            );
            
            // Process each channel
            foreach ( $notification_data['channels'] as $channel ) {
                // Check if already sent via this channel
                if ( ! $this->is_notification_sent( $notification->id, $channel ) ) {
                    $method = 'send_' . $channel . '_notification';
                    if ( method_exists( $this, $method ) ) {
                        $this->$method( $notification->id, $notification_data );
                    }
                }
            }
            
            // Update status
            $this->database->update( 'notifications',
                array( 'status' => 'processed' ),
                array( 'id' => $notification->id )
            );
        }
    }
    
    /**
     * Get notification icon based on type
     * 
     * @param string $type Notification type
     * @return string Icon URL
     */
    protected function get_notification_icon( $type ) {
        $icons = array(
            'success' => 'success-icon.png',
            'info' => 'info-icon.png',
            'warning' => 'warning-icon.png',
            'error' => 'error-icon.png',
            'quiz' => 'quiz-icon.png',
            'achievement' => 'achievement-icon.png'
        );
        
        $icon_file = $icons[ $type ] ?? 'default-icon.png';
        
        return MONEY_QUIZ_PLUGIN_URL . 'assets/images/notifications/' . $icon_file;
    }
}

/**
 * Notification Manager Class
 * 
 * Handles admin interface for notifications
 */
class NotificationManager {
    
    /**
     * Notification service
     * 
     * @var NotificationService
     */
    protected $notification_service;
    
    /**
     * Constructor
     * 
     * @param NotificationService $notification_service
     */
    public function __construct( NotificationService $notification_service ) {
        $this->notification_service = $notification_service;
        
        // Register hooks
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_notifications' ), 100 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_money_quiz_get_notifications', array( $this, 'ajax_get_notifications' ) );
        add_action( 'wp_ajax_money_quiz_mark_notification_read', array( $this, 'ajax_mark_read' ) );
        add_action( 'wp_ajax_money_quiz_send_test_notification', array( $this, 'ajax_send_test' ) );
    }
    
    /**
     * Add notifications to admin bar
     * 
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function add_admin_bar_notifications( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $unread_count = $this->notification_service->get_unread_count( 'admin' );
        
        $title = '<span class="ab-icon dashicons dashicons-bell"></span>';
        if ( $unread_count > 0 ) {
            $title .= '<span class="ab-label money-quiz-notification-count">' . $unread_count . '</span>';
        }
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-notifications',
            'title' => $title,
            'href' => admin_url( 'admin.php?page=money-quiz-notifications' ),
            'meta' => array(
                'class' => 'money-quiz-notifications-menu'
            )
        ));
        
        // Add recent notifications
        $recent = $this->notification_service->get_user_notifications( 'admin', 'unread', 5 );
        
        foreach ( $recent as $notification ) {
            $wp_admin_bar->add_node( array(
                'id' => 'money-quiz-notification-' . $notification['id'],
                'parent' => 'money-quiz-notifications',
                'title' => '<strong>' . esc_html( $notification['title'] ) . '</strong><br>' . 
                          esc_html( $notification['message'] ),
                'href' => $notification['actions'][0]['url'] ?? '#',
                'meta' => array(
                    'class' => 'money-quiz-notification-item'
                )
            ));
        }
        
        // View all link
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-notifications-all',
            'parent' => 'money-quiz-notifications',
            'title' => __( 'View All Notifications', 'money-quiz' ),
            'href' => admin_url( 'admin.php?page=money-quiz-notifications' )
        ));
    }
}

/**
 * Real-time notification handler
 */
class RealtimeNotificationHandler {
    
    /**
     * WebSocket server instance
     * 
     * @var mixed
     */
    protected $websocket_server;
    
    /**
     * Connected clients
     * 
     * @var array
     */
    protected $clients = array();
    
    /**
     * Initialize real-time handler
     */
    public function __construct() {
        // Initialize based on available technology
        if ( $this->is_websocket_available() ) {
            $this->init_websocket();
        } else {
            $this->init_sse();
        }
    }
    
    /**
     * Broadcast message to clients
     * 
     * @param string $user_identifier User identifier
     * @param array  $message Message data
     */
    public function broadcast( $user_identifier, array $message ) {
        $clients = $this->get_user_clients( $user_identifier );
        
        foreach ( $clients as $client ) {
            $this->send_to_client( $client, $message );
        }
    }
    
    /**
     * Send message to specific client
     * 
     * @param mixed $client Client connection
     * @param array $message Message data
     */
    protected function send_to_client( $client, array $message ) {
        $json = json_encode( $message );
        
        if ( $this->is_websocket_available() ) {
            $client->send( $json );
        } else {
            // SSE format
            echo "event: message\n";
            echo "data: {$json}\n\n";
            flush();
        }
    }
}