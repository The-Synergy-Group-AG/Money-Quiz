<?php
/**
 * Email Service
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Admin\SettingsManager;

/**
 * Service for handling email functionality
 */
class EmailService {
    
    /**
     * @var SettingsManager
     */
    private SettingsManager $settings_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_manager = new SettingsManager();
    }
    
    /**
     * Send quiz result email
     * 
     * @param string $to      Recipient email
     * @param array  $result  Quiz result data
     * @param array  $options Additional options
     * @return bool
     */
    public function send_quiz_result( string $to, array $result, array $options = [] ): bool {
        // Check if notifications are enabled
        if ( ! $this->settings_manager->are_notifications_enabled() ) {
            return false;
        }
        
        // Check if prospect emails are enabled
        $email_config = $this->settings_manager->get_email_config();
        if ( ! $email_config['send_to_prospect'] ) {
            return false;
        }
        
        $subject = $options['subject'] ?? $email_config['prospect_subject'];
        
        // Use custom template if available
        if ( ! empty( $email_config['prospect_template'] ) ) {
            $message = $this->parse_email_template( $email_config['prospect_template'], $result );
        } else {
            $message = $this->get_result_email_template( $result );
        }
        
        return $this->send( $to, $subject, $message, $options );
    }
    
    /**
     * Send admin notification
     * 
     * @param array $result   Quiz result data
     * @param array $prospect Prospect data
     * @return bool
     */
    public function send_admin_notification( array $result, array $prospect ): bool {
        // Check if notifications are enabled
        if ( ! $this->settings_manager->are_notifications_enabled() ) {
            return false;
        }
        
        // Check if admin emails are enabled
        $email_config = $this->settings_manager->get_email_config();
        if ( ! $email_config['send_to_admin'] ) {
            return false;
        }
        
        $admin_email = $email_config['admin_email'];
        $subject = $email_config['admin_subject'];
        
        // Use custom template if available
        if ( ! empty( $email_config['admin_template'] ) ) {
            $message = $this->parse_email_template( $email_config['admin_template'], $result, $prospect );
        } else {
            $message = $this->get_admin_notification_template( $result, $prospect );
        }
        
        return $this->send( $admin_email, $subject, $message );
    }
    
    /**
     * Send email
     * 
     * @param string $to      Recipient email
     * @param string $subject Email subject
     * @param string $message Email message
     * @param array  $options Additional options
     * @return bool
     */
    public function send( string $to, string $subject, string $message, array $options = [] ): bool {
        $headers = $this->build_headers( $options );
        
        // Allow filtering of email parameters
        $to = apply_filters( 'money_quiz_email_to', $to, $subject );
        $subject = apply_filters( 'money_quiz_email_subject', $subject, $to );
        $message = apply_filters( 'money_quiz_email_message', $message, $to, $subject );
        $headers = apply_filters( 'money_quiz_email_headers', $headers, $to, $subject );
        
        return wp_mail( $to, $subject, $message, $headers );
    }
    
    /**
     * Build email headers
     * 
     * @param array $options Email options
     * @return array
     */
    private function build_headers( array $options = [] ): array {
        $headers = [];
        
        // Get email config from settings
        $email_config = $this->settings_manager->get_email_config();
        
        // From header
        $from_name = $options['from_name'] ?? $email_config['from_name'];
        $from_email = $options['from_email'] ?? $email_config['from_email'];
        $headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
        
        // Reply-to header
        if ( ! empty( $options['reply_to'] ) ) {
            $headers[] = sprintf( 'Reply-To: %s', $options['reply_to'] );
        }
        
        // Content type
        $content_type = $options['content_type'] ?? 'text/html';
        $charset = $options['charset'] ?? 'UTF-8';
        $headers[] = sprintf( 'Content-Type: %s; charset=%s', $content_type, $charset );
        
        // CC and BCC
        if ( ! empty( $options['cc'] ) ) {
            $headers[] = sprintf( 'Cc: %s', $options['cc'] );
        }
        
        if ( ! empty( $options['bcc'] ) ) {
            $headers[] = sprintf( 'Bcc: %s', $options['bcc'] );
        }
        
        return $headers;
    }
    
    /**
     * Get result email template
     * 
     * @param array $result Quiz result data
     * @return string
     */
    private function get_result_email_template( array $result ): string {
        ob_start();
        ?>
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c3e50;"><?php _e( 'Your Money Quiz Results', 'money-quiz' ); ?></h2>
                
                <p><?php _e( 'Thank you for completing the Money Quiz!', 'money-quiz' ); ?></p>
                
                <?php if ( ! empty( $result['archetype'] ) ): ?>
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #2c3e50;">
                        <?php printf( __( 'Your Money Archetype: %s', 'money-quiz' ), esc_html( $result['archetype']->name ) ); ?>
                    </h3>
                    
                    <?php if ( ! empty( $result['archetype']->description ) ): ?>
                    <p><?php echo wp_kses_post( $result['archetype']->description ); ?></p>
                    <?php endif; ?>
                    
                    <p style="margin-bottom: 0;">
                        <strong><?php _e( 'Score:', 'money-quiz' ); ?></strong> 
                        <?php echo esc_html( $result['score'] ); ?>%
                    </p>
                </div>
                <?php endif; ?>
                
                <p><?php _e( 'We\'ll be in touch soon with personalized insights and recommendations based on your results.', 'money-quiz' ); ?></p>
                
                <hr style="border: 1px solid #e0e0e0; margin: 30px 0;">
                
                <p style="font-size: 12px; color: #666;">
                    <?php _e( 'This email was sent from', 'money-quiz' ); ?> 
                    <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get admin notification template
     * 
     * @param array $result   Quiz result data
     * @param array $prospect Prospect data
     * @return string
     */
    private function get_admin_notification_template( array $result, array $prospect ): string {
        ob_start();
        ?>
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c3e50;"><?php _e( 'New Quiz Completion', 'money-quiz' ); ?></h2>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><strong><?php _e( 'Email:', 'money-quiz' ); ?></strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><?php echo esc_html( $prospect['email'] ?? 'N/A' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><strong><?php _e( 'Name:', 'money-quiz' ); ?></strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><?php echo esc_html( $prospect['name'] ?? 'N/A' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><strong><?php _e( 'Archetype:', 'money-quiz' ); ?></strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><?php echo esc_html( $result['archetype']->name ?? 'N/A' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><strong><?php _e( 'Score:', 'money-quiz' ); ?></strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><?php echo esc_html( $result['score'] ?? 'N/A' ); ?>%</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><strong><?php _e( 'Completed:', 'money-quiz' ); ?></strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0;"><?php echo esc_html( current_time( 'mysql' ) ); ?></td>
                    </tr>
                </table>
                
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=money-quiz-results' ) ); ?>" 
                       style="background-color: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                        <?php _e( 'View in Admin', 'money-quiz' ); ?>
                    </a>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Parse email template with placeholders
     * 
     * @param string $template Template with placeholders
     * @param array  $result   Quiz result data
     * @param array  $prospect Prospect data (optional)
     * @return string
     */
    private function parse_email_template( string $template, array $result, array $prospect = [] ): string {
        $replacements = [
            '{name}' => $prospect['name'] ?? $result['name'] ?? '',
            '{email}' => $prospect['email'] ?? $result['email'] ?? '',
            '{archetype}' => $result['archetype']->name ?? '',
            '{score}' => $result['score'] ?? '',
            '{date}' => current_time( 'mysql' ),
            '{site_name}' => get_bloginfo( 'name' ),
            '{site_url}' => home_url(),
            '{results_link}' => add_query_arg( [
                'quiz_result' => $result['id'] ?? '',
                'key' => wp_hash( $result['id'] ?? '' . $result['email'] ?? '' ),
            ], home_url() ),
        ];
        
        // Allow filtering of replacements
        $replacements = apply_filters( 'money_quiz_email_template_replacements', $replacements, $result, $prospect );
        
        // Replace placeholders
        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }
}