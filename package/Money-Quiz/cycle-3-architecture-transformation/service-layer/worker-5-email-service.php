<?php
/**
 * Money Quiz Plugin - Email Service
 * Worker 5: Service Layer - Email Operations
 * 
 * Handles all email functionality including sending results,
 * integrating with email providers, and managing subscribers.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use Exception;

/**
 * Email Service Class
 * 
 * Manages email operations and integrations
 */
class EmailService {
    
    /**
     * Validation service instance
     * 
     * @var ValidationService
     */
    protected $validation_service;
    
    /**
     * Email provider instance
     * 
     * @var EmailProviderInterface
     */
    protected $provider;
    
    /**
     * Email settings
     * 
     * @var array
     */
    protected $settings;
    
    /**
     * Constructor
     * 
     * @param ValidationService $validation_service
     */
    public function __construct( ValidationService $validation_service ) {
        $this->validation_service = $validation_service;
        $this->load_settings();
        $this->init_provider();
    }
    
    /**
     * Load email settings
     */
    protected function load_settings() {
        $this->settings = array(
            'provider' => get_option( 'money_quiz_email_provider', 'none' ),
            'api_key' => get_option( 'money_quiz_api_key', '' ),
            'list_id' => get_option( 'money_quiz_list_id', '' ),
            'from_name' => get_option( 'money_quiz_from_name', get_bloginfo( 'name' ) ),
            'from_email' => get_option( 'money_quiz_from_email', get_option( 'admin_email' ) ),
            'reply_to' => get_option( 'money_quiz_reply_to', get_option( 'admin_email' ) ),
            'send_results' => get_option( 'money_quiz_send_results', 'yes' ),
            'send_admin_notification' => get_option( 'money_quiz_admin_notification', 'yes' ),
            'admin_email' => get_option( 'money_quiz_admin_email', get_option( 'admin_email' ) ),
            'double_optin' => get_option( 'money_quiz_double_optin', 'no' ),
            'tags' => get_option( 'money_quiz_tags', 'money-quiz' )
        );
    }
    
    /**
     * Initialize email provider
     */
    protected function init_provider() {
        $provider_class = $this->get_provider_class( $this->settings['provider'] );
        
        if ( $provider_class && class_exists( $provider_class ) ) {
            $this->provider = new $provider_class( 
                $this->settings['api_key'],
                $this->settings['list_id']
            );
        }
    }
    
    /**
     * Send results email
     * 
     * @param string $email Recipient email
     * @param int    $result_id Result ID
     * @return bool
     */
    public function send_results_email( $email, $result_id ) {
        try {
            // Validate email
            if ( ! $this->validation_service->validate_email( $email ) ) {
                throw new Exception( __( 'Invalid email address', 'money-quiz' ) );
            }
            
            // Check if sending is enabled
            if ( $this->settings['send_results'] !== 'yes' ) {
                return true;
            }
            
            // Get result data
            $result_data = $this->get_result_data( $result_id );
            if ( ! $result_data ) {
                throw new Exception( __( 'Result not found', 'money-quiz' ) );
            }
            
            // Prepare email content
            $subject = $this->prepare_subject( $result_data );
            $body = $this->prepare_body( $result_data );
            $headers = $this->prepare_headers();
            
            // Send email
            $sent = wp_mail( $email, $subject, $body, $headers );
            
            if ( ! $sent ) {
                throw new Exception( __( 'Failed to send email', 'money-quiz' ) );
            }
            
            // Log email sent
            $this->log_email_sent( $email, $result_id, 'results' );
            
            return true;
            
        } catch ( Exception $e ) {
            error_log( 'Money Quiz Email Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Send admin notification
     * 
     * @param array $submission_data Submission data
     * @return bool
     */
    public function send_admin_notification( $submission_data ) {
        try {
            // Check if admin notifications are enabled
            if ( $this->settings['send_admin_notification'] !== 'yes' ) {
                return true;
            }
            
            $admin_email = $this->settings['admin_email'];
            if ( ! $this->validation_service->validate_email( $admin_email ) ) {
                return false;
            }
            
            // Prepare notification
            $subject = sprintf(
                __( '[%s] New Money Quiz Submission', 'money-quiz' ),
                get_bloginfo( 'name' )
            );
            
            $body = $this->prepare_admin_notification_body( $submission_data );
            $headers = $this->prepare_headers();
            
            // Send notification
            return wp_mail( $admin_email, $subject, $body, $headers );
            
        } catch ( Exception $e ) {
            error_log( 'Money Quiz Admin Notification Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Add subscriber to email list
     * 
     * @param array $subscriber_data Subscriber data
     * @return bool
     */
    public function add_to_list( $subscriber_data ) {
        try {
            // Check if provider is configured
            if ( ! $this->provider || $this->settings['provider'] === 'none' ) {
                return true;
            }
            
            // Validate email
            if ( ! $this->validation_service->validate_email( $subscriber_data['email'] ) ) {
                throw new Exception( __( 'Invalid email address', 'money-quiz' ) );
            }
            
            // Prepare subscriber data
            $formatted_data = $this->format_subscriber_data( $subscriber_data );
            
            // Add to provider
            $result = $this->provider->add_subscriber( $formatted_data );
            
            if ( ! $result ) {
                throw new Exception( __( 'Failed to add subscriber', 'money-quiz' ) );
            }
            
            // Log subscription
            $this->log_subscription( $subscriber_data['email'], $this->settings['provider'] );
            
            return true;
            
        } catch ( Exception $e ) {
            error_log( 'Money Quiz List Subscription Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Update subscriber
     * 
     * @param string $email Email address
     * @param array  $data Update data
     * @return bool
     */
    public function update_subscriber( $email, $data ) {
        try {
            if ( ! $this->provider ) {
                return false;
            }
            
            return $this->provider->update_subscriber( $email, $data );
            
        } catch ( Exception $e ) {
            error_log( 'Money Quiz Update Subscriber Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Check if email exists in list
     * 
     * @param string $email Email address
     * @return bool
     */
    public function subscriber_exists( $email ) {
        try {
            if ( ! $this->provider ) {
                return false;
            }
            
            return $this->provider->subscriber_exists( $email );
            
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Get available email providers
     * 
     * @return array
     */
    public function get_available_providers() {
        return array(
            'none' => __( 'None', 'money-quiz' ),
            'mailerlite' => __( 'MailerLite', 'money-quiz' ),
            'mailchimp' => __( 'Mailchimp', 'money-quiz' ),
            'activecampaign' => __( 'ActiveCampaign', 'money-quiz' ),
            'convertkit' => __( 'ConvertKit', 'money-quiz' ),
            'aweber' => __( 'AWeber', 'money-quiz' ),
            'getresponse' => __( 'GetResponse', 'money-quiz' ),
            'sendinblue' => __( 'Sendinblue', 'money-quiz' ),
            'drip' => __( 'Drip', 'money-quiz' ),
            'constantcontact' => __( 'Constant Contact', 'money-quiz' )
        );
    }
    
    /**
     * Test email provider connection
     * 
     * @param string $provider Provider name
     * @param string $api_key API key
     * @param string $list_id List ID
     * @return array Test result
     */
    public function test_provider_connection( $provider, $api_key, $list_id ) {
        try {
            $provider_class = $this->get_provider_class( $provider );
            
            if ( ! $provider_class || ! class_exists( $provider_class ) ) {
                throw new Exception( __( 'Invalid provider', 'money-quiz' ) );
            }
            
            $test_provider = new $provider_class( $api_key, $list_id );
            $result = $test_provider->test_connection();
            
            return array(
                'success' => $result,
                'message' => $result 
                    ? __( 'Connection successful!', 'money-quiz' )
                    : __( 'Connection failed. Please check your credentials.', 'money-quiz' )
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get provider class name
     * 
     * @param string $provider Provider name
     * @return string|null
     */
    protected function get_provider_class( $provider ) {
        $providers = array(
            'mailerlite' => 'MoneyQuiz\\EmailProviders\\MailerLiteProvider',
            'mailchimp' => 'MoneyQuiz\\EmailProviders\\MailchimpProvider',
            'activecampaign' => 'MoneyQuiz\\EmailProviders\\ActiveCampaignProvider',
            'convertkit' => 'MoneyQuiz\\EmailProviders\\ConvertKitProvider',
            'aweber' => 'MoneyQuiz\\EmailProviders\\AWeberProvider',
            'getresponse' => 'MoneyQuiz\\EmailProviders\\GetResponseProvider',
            'sendinblue' => 'MoneyQuiz\\EmailProviders\\SendinblueProvider',
            'drip' => 'MoneyQuiz\\EmailProviders\\DripProvider',
            'constantcontact' => 'MoneyQuiz\\EmailProviders\\ConstantContactProvider'
        );
        
        return isset( $providers[ $provider ] ) ? $providers[ $provider ] : null;
    }
    
    /**
     * Prepare email subject
     * 
     * @param array $result_data Result data
     * @return string
     */
    protected function prepare_subject( $result_data ) {
        $subject = get_option( 'money_quiz_email_subject', 
            __( 'Your Money Quiz Results - {{archetype}}', 'money-quiz' )
        );
        
        return $this->replace_placeholders( $subject, $result_data );
    }
    
    /**
     * Prepare email body
     * 
     * @param array $result_data Result data
     * @return string
     */
    protected function prepare_body( $result_data ) {
        $template = get_option( 'money_quiz_email_template' );
        
        if ( ! $template ) {
            $template = $this->get_default_email_template();
        }
        
        $body = $this->replace_placeholders( $template, $result_data );
        
        // Convert to HTML if needed
        if ( strpos( $body, '<html' ) === false ) {
            $body = $this->wrap_in_html_template( $body );
        }
        
        return $body;
    }
    
    /**
     * Prepare email headers
     * 
     * @return array
     */
    protected function prepare_headers() {
        $headers = array();
        
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = sprintf( 'From: %s <%s>', 
            $this->settings['from_name'], 
            $this->settings['from_email'] 
        );
        
        if ( $this->settings['reply_to'] ) {
            $headers[] = 'Reply-To: ' . $this->settings['reply_to'];
        }
        
        return $headers;
    }
    
    /**
     * Replace placeholders in text
     * 
     * @param string $text Text with placeholders
     * @param array  $data Replacement data
     * @return string
     */
    protected function replace_placeholders( $text, $data ) {
        $replacements = array(
            '{{first_name}}' => $data['first_name'] ?? '',
            '{{last_name}}' => $data['last_name'] ?? '',
            '{{email}}' => $data['email'] ?? '',
            '{{archetype}}' => $data['archetype']['name'] ?? '',
            '{{score}}' => $data['score'] ?? '',
            '{{percentage}}' => $data['percentage'] ?? '',
            '{{description}}' => $data['archetype']['description'] ?? '',
            '{{recommendations}}' => $data['archetype']['recommendations'] ?? '',
            '{{results_url}}' => $data['results_url'] ?? '',
            '{{site_name}}' => get_bloginfo( 'name' ),
            '{{site_url}}' => home_url(),
            '{{date}}' => current_time( get_option( 'date_format' ) )
        );
        
        return str_replace( 
            array_keys( $replacements ), 
            array_values( $replacements ), 
            $text 
        );
    }
    
    /**
     * Format subscriber data for provider
     * 
     * @param array $data Raw subscriber data
     * @return array
     */
    protected function format_subscriber_data( $data ) {
        $formatted = array(
            'email' => $data['email'],
            'status' => $this->settings['double_optin'] === 'yes' ? 'pending' : 'subscribed',
            'merge_fields' => array(),
            'tags' => explode( ',', $this->settings['tags'] )
        );
        
        // Add merge fields
        if ( ! empty( $data['first_name'] ) ) {
            $formatted['merge_fields']['FNAME'] = $data['first_name'];
        }
        
        if ( ! empty( $data['last_name'] ) ) {
            $formatted['merge_fields']['LNAME'] = $data['last_name'];
        }
        
        if ( ! empty( $data['phone'] ) ) {
            $formatted['merge_fields']['PHONE'] = $data['phone'];
        }
        
        if ( ! empty( $data['archetype'] ) ) {
            $formatted['merge_fields']['ARCHETYPE'] = $data['archetype'];
            $formatted['tags'][] = 'archetype-' . sanitize_title( $data['archetype'] );
        }
        
        if ( ! empty( $data['score'] ) ) {
            $formatted['merge_fields']['SCORE'] = $data['score'];
        }
        
        // Add quiz completion date
        $formatted['merge_fields']['QUIZ_DATE'] = current_time( 'Y-m-d' );
        
        return apply_filters( 'money_quiz_subscriber_data', $formatted, $data );
    }
    
    /**
     * Get result data for email
     * 
     * @param int $result_id Result ID
     * @return array|null
     */
    protected function get_result_data( $result_id ) {
        global $wpdb;
        
        // This would normally use the QuizService, but for isolation we'll query directly
        $table_prefix = $wpdb->prefix;
        
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, p.Email, p.FirstName, p.LastName, a.Name as archetype_name, 
                    a.Description as archetype_description, a.Recommendations as archetype_recommendations
             FROM {$table_prefix}mq_taken t
             JOIN {$table_prefix}mq_prospects p ON t.Prospect_ID = p.Prospect_ID
             JOIN {$table_prefix}mq_archetypes a ON t.Archetype_ID = a.Archetype_ID
             WHERE t.Taken_ID = %d",
            $result_id
        ), ARRAY_A );
        
        if ( ! $result ) {
            return null;
        }
        
        // Format data
        return array(
            'result_id' => $result_id,
            'email' => $result['Email'],
            'first_name' => $result['FirstName'],
            'last_name' => $result['LastName'],
            'score' => $result['Score_Total'],
            'percentage' => round( ( $result['Score_Total'] / 100 ) * 100, 2 ),
            'archetype' => array(
                'name' => $result['archetype_name'],
                'description' => $result['archetype_description'],
                'recommendations' => $result['archetype_recommendations']
            ),
            'results_url' => add_query_arg( 'result', $result_id, 
                get_permalink( get_option( 'money_quiz_results_page' ) )
            )
        );
    }
    
    /**
     * Get default email template
     * 
     * @return string
     */
    protected function get_default_email_template() {
        return '
<h2>Hi {{first_name}},</h2>

<p>Thank you for taking the Money Quiz! Based on your responses, you are a <strong>{{archetype}}</strong>.</p>

<h3>Your Results:</h3>
<p>Score: {{score}} ({{percentage}}%)</p>
<p>{{description}}</p>

<h3>Recommendations:</h3>
{{recommendations}}

<p><a href="{{results_url}}" style="display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 3px;">View Your Full Results</a></p>

<p>Best regards,<br>
{{site_name}} Team</p>
';
    }
    
    /**
     * Wrap content in HTML template
     * 
     * @param string $content Email content
     * @return string
     */
    protected function wrap_in_html_template( $content ) {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . get_bloginfo( 'name' ) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h2, h3 { color: #007cba; }
        a { color: #007cba; }
        .button { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        ' . $content . '
    </div>
</body>
</html>';
    }
    
    /**
     * Prepare admin notification body
     * 
     * @param array $data Submission data
     * @return string
     */
    protected function prepare_admin_notification_body( $data ) {
        $body = '<h3>' . __( 'New Money Quiz Submission', 'money-quiz' ) . '</h3>';
        $body .= '<p><strong>' . __( 'Date:', 'money-quiz' ) . '</strong> ' . current_time( 'Y-m-d H:i:s' ) . '</p>';
        
        if ( ! empty( $data['email'] ) ) {
            $body .= '<p><strong>' . __( 'Email:', 'money-quiz' ) . '</strong> ' . esc_html( $data['email'] ) . '</p>';
        }
        
        if ( ! empty( $data['first_name'] ) || ! empty( $data['last_name'] ) ) {
            $name = trim( $data['first_name'] . ' ' . $data['last_name'] );
            $body .= '<p><strong>' . __( 'Name:', 'money-quiz' ) . '</strong> ' . esc_html( $name ) . '</p>';
        }
        
        if ( ! empty( $data['phone'] ) ) {
            $body .= '<p><strong>' . __( 'Phone:', 'money-quiz' ) . '</strong> ' . esc_html( $data['phone'] ) . '</p>';
        }
        
        if ( ! empty( $data['archetype'] ) ) {
            $body .= '<p><strong>' . __( 'Archetype:', 'money-quiz' ) . '</strong> ' . esc_html( $data['archetype'] ) . '</p>';
        }
        
        if ( ! empty( $data['score'] ) ) {
            $body .= '<p><strong>' . __( 'Score:', 'money-quiz' ) . '</strong> ' . esc_html( $data['score'] ) . '</p>';
        }
        
        $admin_url = admin_url( 'admin.php?page=money-quiz-results' );
        $body .= '<p><a href="' . $admin_url . '">' . __( 'View in admin', 'money-quiz' ) . '</a></p>';
        
        return $this->wrap_in_html_template( $body );
    }
    
    /**
     * Log email sent
     * 
     * @param string $email Email address
     * @param int    $result_id Result ID
     * @param string $type Email type
     */
    protected function log_email_sent( $email, $result_id, $type ) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'mq_email_log',
            array(
                'email' => $email,
                'result_id' => $result_id,
                'type' => $type,
                'sent_date' => current_time( 'mysql' )
            ),
            array( '%s', '%d', '%s', '%s' )
        );
    }
    
    /**
     * Log subscription
     * 
     * @param string $email Email address
     * @param string $provider Provider name
     */
    protected function log_subscription( $email, $provider ) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'mq_subscription_log',
            array(
                'email' => $email,
                'provider' => $provider,
                'subscribed_date' => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%s' )
        );
    }
}