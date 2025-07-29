<?php
/**
 * Email Configuration Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle settings save
if ( isset( $_POST['save_email_settings'] ) ) {
    check_admin_referer( 'money_quiz_email_settings' );
    
    // Email provider settings
    update_option( 'mq_email_provider', sanitize_text_field( $_POST['email_provider'] ) );
    
    // SMTP settings
    if ( $_POST['email_provider'] === 'smtp' ) {
        update_option( 'mq_smtp_host', sanitize_text_field( $_POST['smtp_host'] ) );
        update_option( 'mq_smtp_port', intval( $_POST['smtp_port'] ) );
        update_option( 'mq_smtp_username', sanitize_text_field( $_POST['smtp_username'] ) );
        update_option( 'mq_smtp_password', sanitize_text_field( $_POST['smtp_password'] ) );
        update_option( 'mq_smtp_encryption', sanitize_text_field( $_POST['smtp_encryption'] ) );
    }
    
    // Email settings
    update_option( 'mq_email_from_name', sanitize_text_field( $_POST['email_from_name'] ) );
    update_option( 'mq_email_from_address', sanitize_email( $_POST['email_from_address'] ) );
    update_option( 'mq_email_reply_to', sanitize_email( $_POST['email_reply_to'] ) );
    
    // Email templates
    update_option( 'mq_welcome_email_subject', sanitize_text_field( $_POST['welcome_email_subject'] ) );
    update_option( 'mq_welcome_email_content', wp_kses_post( $_POST['welcome_email_content'] ) );
    update_option( 'mq_results_email_subject', sanitize_text_field( $_POST['results_email_subject'] ) );
    update_option( 'mq_results_email_content', wp_kses_post( $_POST['results_email_content'] ) );
    
    // Notification settings
    update_option( 'mq_admin_notifications', isset( $_POST['admin_notifications'] ) ? 1 : 0 );
    update_option( 'mq_notification_emails', sanitize_textarea_field( $_POST['notification_emails'] ) );
    
    echo '<div class="notice notice-success"><p>' . __( 'Email settings saved successfully.', 'money-quiz' ) . '</p></div>';
}

// Test email
if ( isset( $_POST['send_test_email'] ) ) {
    check_admin_referer( 'money_quiz_test_email' );
    
    $test_email = sanitize_email( $_POST['test_email_address'] );
    $subject = __( 'Money Quiz Test Email', 'money-quiz' );
    $message = __( 'This is a test email from Money Quiz to verify your email configuration is working correctly.', 'money-quiz' );
    
    $headers = [];
    $from_name = get_option( 'mq_email_from_name', get_bloginfo( 'name' ) );
    $from_email = get_option( 'mq_email_from_address', get_option( 'admin_email' ) );
    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    
    $sent = wp_mail( $test_email, $subject, $message, $headers );
    
    if ( $sent ) {
        echo '<div class="notice notice-success"><p>' . __( 'Test email sent successfully!', 'money-quiz' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . __( 'Failed to send test email. Please check your configuration.', 'money-quiz' ) . '</p></div>';
    }
}

// Get current settings
$email_provider = get_option( 'mq_email_provider', 'wp_mail' );
$smtp_host = get_option( 'mq_smtp_host', '' );
$smtp_port = get_option( 'mq_smtp_port', 587 );
$smtp_username = get_option( 'mq_smtp_username', '' );
$smtp_password = get_option( 'mq_smtp_password', '' );
$smtp_encryption = get_option( 'mq_smtp_encryption', 'tls' );

$email_from_name = get_option( 'mq_email_from_name', get_bloginfo( 'name' ) );
$email_from_address = get_option( 'mq_email_from_address', get_option( 'admin_email' ) );
$email_reply_to = get_option( 'mq_email_reply_to', get_option( 'admin_email' ) );

$welcome_email_subject = get_option( 'mq_welcome_email_subject', __( 'Welcome! Here are your quiz results', 'money-quiz' ) );
$welcome_email_content = get_option( 'mq_welcome_email_content', '' );
$results_email_subject = get_option( 'mq_results_email_subject', __( 'Your {quiz_name} Results', 'money-quiz' ) );
$results_email_content = get_option( 'mq_results_email_content', '' );

$admin_notifications = get_option( 'mq_admin_notifications', 1 );
$notification_emails = get_option( 'mq_notification_emails', get_option( 'admin_email' ) );

// Email providers
$email_providers = [
    'wp_mail' => __( 'WordPress Default (wp_mail)', 'money-quiz' ),
    'smtp' => __( 'SMTP Server', 'money-quiz' ),
    'sendgrid' => __( 'SendGrid', 'money-quiz' ),
    'mailgun' => __( 'Mailgun', 'money-quiz' ),
    'mailchimp' => __( 'Mailchimp Transactional', 'money-quiz' ),
    'aws_ses' => __( 'Amazon SES', 'money-quiz' )
];
?>

<div class="wrap mq-email-settings">
    
    <form method="post" class="settings-form">
        <?php wp_nonce_field( 'money_quiz_email_settings' ); ?>
        
        <!-- Email Service Provider -->
        <div class="mq-card">
            <h2><?php _e( '📮 Email Service Provider', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_provider"><?php _e( 'Email Provider', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="email_provider" id="email_provider" onchange="toggleProviderSettings()">
                            <?php foreach ( $email_providers as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $email_provider, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e( 'Choose how emails should be sent', 'money-quiz' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <!-- SMTP Settings -->
            <div id="smtp-settings" style="<?php echo $email_provider === 'smtp' ? '' : 'display:none;'; ?>">
                <h3><?php _e( 'SMTP Configuration', 'money-quiz' ); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="smtp_host"><?php _e( 'SMTP Host', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="smtp_host" id="smtp_host" class="regular-text" 
                                   value="<?php echo esc_attr( $smtp_host ); ?>" placeholder="smtp.gmail.com" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="smtp_port"><?php _e( 'SMTP Port', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="smtp_port" id="smtp_port" class="small-text" 
                                   value="<?php echo esc_attr( $smtp_port ); ?>" />
                            <p class="description"><?php _e( 'Common ports: 25, 465 (SSL), 587 (TLS)', 'money-quiz' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="smtp_encryption"><?php _e( 'Encryption', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <select name="smtp_encryption" id="smtp_encryption">
                                <option value="none" <?php selected( $smtp_encryption, 'none' ); ?>><?php _e( 'None', 'money-quiz' ); ?></option>
                                <option value="ssl" <?php selected( $smtp_encryption, 'ssl' ); ?>>SSL</option>
                                <option value="tls" <?php selected( $smtp_encryption, 'tls' ); ?>>TLS</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="smtp_username"><?php _e( 'SMTP Username', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="smtp_username" id="smtp_username" class="regular-text" 
                                   value="<?php echo esc_attr( $smtp_username ); ?>" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="smtp_password"><?php _e( 'SMTP Password', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="password" name="smtp_password" id="smtp_password" class="regular-text" 
                                   value="<?php echo esc_attr( $smtp_password ); ?>" />
                            <p class="description"><?php _e( 'Your password is stored securely', 'money-quiz' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Other provider settings would go here -->
            <div id="provider-notice" style="<?php echo $email_provider === 'wp_mail' || $email_provider === 'smtp' ? 'display:none;' : ''; ?>">
                <p class="notice notice-info" style="padding: 10px;">
                    <?php _e( 'Additional configuration required. Please install the respective plugin for your chosen email provider.', 'money-quiz' ); ?>
                </p>
            </div>
        </div>
        
        <!-- Email Sender Settings -->
        <div class="mq-card">
            <h2><?php _e( '✉️ Email Sender Settings', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_from_name"><?php _e( 'From Name', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="email_from_name" id="email_from_name" class="regular-text" 
                               value="<?php echo esc_attr( $email_from_name ); ?>" />
                        <p class="description"><?php _e( 'The name that appears in the "From" field', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_from_address"><?php _e( 'From Email', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="email_from_address" id="email_from_address" class="regular-text" 
                               value="<?php echo esc_attr( $email_from_address ); ?>" />
                        <p class="description"><?php _e( 'The email address emails are sent from', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_reply_to"><?php _e( 'Reply-To Email', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="email_reply_to" id="email_reply_to" class="regular-text" 
                               value="<?php echo esc_attr( $email_reply_to ); ?>" />
                        <p class="description"><?php _e( 'Where replies should be sent', 'money-quiz' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Email Templates -->
        <div class="mq-card">
            <h2><?php _e( '📝 Email Templates', 'money-quiz' ); ?></h2>
            
            <h3><?php _e( 'Welcome Email', 'money-quiz' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="welcome_email_subject"><?php _e( 'Subject Line', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="welcome_email_subject" id="welcome_email_subject" class="large-text" 
                               value="<?php echo esc_attr( $welcome_email_subject ); ?>" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="welcome_email_content"><?php _e( 'Email Content', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor( $welcome_email_content, 'welcome_email_content', [
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true
                        ] );
                        ?>
                        <p class="description">
                            <?php _e( 'Available variables: {name}, {email}, {quiz_name}, {company_name}', 'money-quiz' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e( 'Quiz Results Email', 'money-quiz' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="results_email_subject"><?php _e( 'Subject Line', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="results_email_subject" id="results_email_subject" class="large-text" 
                               value="<?php echo esc_attr( $results_email_subject ); ?>" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="results_email_content"><?php _e( 'Email Content', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor( $results_email_content, 'results_email_content', [
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true
                        ] );
                        ?>
                        <p class="description">
                            <?php _e( 'Available variables: {name}, {quiz_name}, {archetype}, {score}, {results_link}', 'money-quiz' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Admin Notifications -->
        <div class="mq-card">
            <h2><?php _e( '🔔 Admin Notifications', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Enable Notifications', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="admin_notifications" value="1" 
                                   <?php checked( $admin_notifications, 1 ); ?> />
                            <?php _e( 'Send email notifications when someone completes a quiz', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_emails"><?php _e( 'Notification Recipients', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <textarea name="notification_emails" id="notification_emails" rows="3" class="large-text"><?php echo esc_textarea( $notification_emails ); ?></textarea>
                        <p class="description"><?php _e( 'Enter email addresses, one per line', 'money-quiz' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="save_email_settings" class="button button-primary" 
                   value="<?php _e( 'Save Email Settings', 'money-quiz' ); ?>" />
        </p>
    </form>
    
    <!-- Test Email -->
    <div class="mq-card">
        <h2><?php _e( '🧪 Test Email Configuration', 'money-quiz' ); ?></h2>
        
        <form method="post">
            <?php wp_nonce_field( 'money_quiz_test_email' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email_address"><?php _e( 'Send Test Email To', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="test_email_address" id="test_email_address" class="regular-text" 
                               value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
                        <input type="submit" name="send_test_email" class="button" 
                               value="<?php _e( 'Send Test Email', 'money-quiz' ); ?>" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
</div>

<script>
function toggleProviderSettings() {
    var provider = document.getElementById('email_provider').value;
    
    // Hide all provider settings
    document.getElementById('smtp-settings').style.display = 'none';
    document.getElementById('provider-notice').style.display = 'none';
    
    // Show relevant settings
    if (provider === 'smtp') {
        document.getElementById('smtp-settings').style.display = 'block';
    } else if (provider !== 'wp_mail') {
        document.getElementById('provider-notice').style.display = 'block';
    }
}
</script>

<style>
.mq-email-settings .form-table th {
    width: 200px;
}

#smtp-settings {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

#smtp-settings h3 {
    margin-top: 0;
}
</style>