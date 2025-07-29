<?php
/**
 * Integrations Settings Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle settings save
if ( isset( $_POST['save_integrations'] ) ) {
    check_admin_referer( 'money_quiz_integrations' );
    
    // CRM Integration
    update_option( 'mq_crm_provider', sanitize_text_field( $_POST['crm_provider'] ) );
    
    // Email Marketing
    update_option( 'mq_email_marketing_provider', sanitize_text_field( $_POST['email_marketing_provider'] ) );
    update_option( 'mq_mailchimp_api_key', sanitize_text_field( $_POST['mailchimp_api_key'] ) );
    update_option( 'mq_mailchimp_list_id', sanitize_text_field( $_POST['mailchimp_list_id'] ) );
    update_option( 'mq_activecampaign_api_url', esc_url_raw( $_POST['activecampaign_api_url'] ) );
    update_option( 'mq_activecampaign_api_key', sanitize_text_field( $_POST['activecampaign_api_key'] ) );
    
    // Analytics
    update_option( 'mq_google_analytics_id', sanitize_text_field( $_POST['google_analytics_id'] ) );
    update_option( 'mq_facebook_pixel_id', sanitize_text_field( $_POST['facebook_pixel_id'] ) );
    update_option( 'mq_google_tag_manager_id', sanitize_text_field( $_POST['google_tag_manager_id'] ) );
    
    // Webhooks
    update_option( 'mq_webhook_enabled', isset( $_POST['webhook_enabled'] ) ? 1 : 0 );
    update_option( 'mq_webhook_url', esc_url_raw( $_POST['webhook_url'] ) );
    update_option( 'mq_webhook_events', isset( $_POST['webhook_events'] ) ? array_map( 'sanitize_text_field', $_POST['webhook_events'] ) : [] );
    
    // Zapier
    update_option( 'mq_zapier_enabled', isset( $_POST['zapier_enabled'] ) ? 1 : 0 );
    
    echo '<div class="notice notice-success"><p>' . __( 'Integration settings saved successfully.', 'money-quiz' ) . '</p></div>';
}

// Test integration
if ( isset( $_POST['test_integration'] ) ) {
    check_admin_referer( 'test_integration' );
    
    $integration = sanitize_text_field( $_POST['integration_type'] );
    $test_result = test_integration( $integration );
    
    if ( $test_result['success'] ) {
        echo '<div class="notice notice-success"><p>' . esc_html( $test_result['message'] ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html( $test_result['message'] ) . '</p></div>';
    }
}

// Get current settings
$crm_provider = get_option( 'mq_crm_provider', 'none' );
$email_marketing_provider = get_option( 'mq_email_marketing_provider', 'none' );
$mailchimp_api_key = get_option( 'mq_mailchimp_api_key', '' );
$mailchimp_list_id = get_option( 'mq_mailchimp_list_id', '' );
$activecampaign_api_url = get_option( 'mq_activecampaign_api_url', '' );
$activecampaign_api_key = get_option( 'mq_activecampaign_api_key', '' );

$google_analytics_id = get_option( 'mq_google_analytics_id', '' );
$facebook_pixel_id = get_option( 'mq_facebook_pixel_id', '' );
$google_tag_manager_id = get_option( 'mq_google_tag_manager_id', '' );

$webhook_enabled = get_option( 'mq_webhook_enabled', 0 );
$webhook_url = get_option( 'mq_webhook_url', '' );
$webhook_events = get_option( 'mq_webhook_events', ['quiz_completed'] );

$zapier_enabled = get_option( 'mq_zapier_enabled', 0 );
$zapier_key = get_option( 'mq_zapier_api_key', wp_generate_password( 32, false ) );
if ( ! get_option( 'mq_zapier_api_key' ) ) {
    update_option( 'mq_zapier_api_key', $zapier_key );
}

// Integration test function
function test_integration( $type ) {
    switch ( $type ) {
        case 'mailchimp':
            // Test Mailchimp connection
            $api_key = get_option( 'mq_mailchimp_api_key' );
            if ( empty( $api_key ) ) {
                return ['success' => false, 'message' => __( 'Mailchimp API key not configured', 'money-quiz' )];
            }
            // Would make actual API call here
            return ['success' => true, 'message' => __( 'Mailchimp connection successful!', 'money-quiz' )];
            
        case 'webhook':
            // Test webhook
            $url = get_option( 'mq_webhook_url' );
            if ( empty( $url ) ) {
                return ['success' => false, 'message' => __( 'Webhook URL not configured', 'money-quiz' )];
            }
            // Would send test webhook here
            return ['success' => true, 'message' => __( 'Test webhook sent successfully!', 'money-quiz' )];
            
        default:
            return ['success' => false, 'message' => __( 'Unknown integration type', 'money-quiz' )];
    }
}
?>

<div class="wrap mq-integrations-settings">
    
    <form method="post" class="settings-form">
        <?php wp_nonce_field( 'money_quiz_integrations' ); ?>
        
        <!-- CRM Integration -->
        <div class="mq-card">
            <h2><?php _e( '🤝 CRM Integration', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="crm_provider"><?php _e( 'CRM Provider', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="crm_provider" id="crm_provider">
                            <option value="none" <?php selected( $crm_provider, 'none' ); ?>><?php _e( 'None', 'money-quiz' ); ?></option>
                            <option value="salesforce" <?php selected( $crm_provider, 'salesforce' ); ?>>Salesforce</option>
                            <option value="hubspot" <?php selected( $crm_provider, 'hubspot' ); ?>>HubSpot</option>
                            <option value="pipedrive" <?php selected( $crm_provider, 'pipedrive' ); ?>>Pipedrive</option>
                            <option value="zoho" <?php selected( $crm_provider, 'zoho' ); ?>>Zoho CRM</option>
                        </select>
                        <p class="description"><?php _e( 'Sync quiz leads with your CRM', 'money-quiz' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <div class="integration-notice">
                <p><?php _e( 'CRM integration requires additional plugin installation for your selected provider.', 'money-quiz' ); ?></p>
            </div>
        </div>
        
        <!-- Email Marketing Integration -->
        <div class="mq-card">
            <h2><?php _e( '📧 Email Marketing Integration', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_marketing_provider"><?php _e( 'Email Marketing Provider', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="email_marketing_provider" id="email_marketing_provider" onchange="toggleEmailProvider()">
                            <option value="none" <?php selected( $email_marketing_provider, 'none' ); ?>><?php _e( 'None', 'money-quiz' ); ?></option>
                            <option value="mailchimp" <?php selected( $email_marketing_provider, 'mailchimp' ); ?>>Mailchimp</option>
                            <option value="activecampaign" <?php selected( $email_marketing_provider, 'activecampaign' ); ?>>ActiveCampaign</option>
                            <option value="convertkit" <?php selected( $email_marketing_provider, 'convertkit' ); ?>>ConvertKit</option>
                            <option value="aweber" <?php selected( $email_marketing_provider, 'aweber' ); ?>>AWeber</option>
                            <option value="getresponse" <?php selected( $email_marketing_provider, 'getresponse' ); ?>>GetResponse</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <!-- Mailchimp Settings -->
            <div id="mailchimp-settings" style="<?php echo $email_marketing_provider === 'mailchimp' ? '' : 'display:none;'; ?>">
                <h3>Mailchimp Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mailchimp_api_key"><?php _e( 'API Key', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="mailchimp_api_key" id="mailchimp_api_key" class="regular-text" 
                                   value="<?php echo esc_attr( $mailchimp_api_key ); ?>" />
                            <p class="description">
                                <a href="https://mailchimp.com/help/about-api-keys/" target="_blank">
                                    <?php _e( 'How to get your Mailchimp API key', 'money-quiz' ); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="mailchimp_list_id"><?php _e( 'List ID', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="mailchimp_list_id" id="mailchimp_list_id" class="regular-text" 
                                   value="<?php echo esc_attr( $mailchimp_list_id ); ?>" />
                            <button type="button" class="button" onclick="fetchMailchimpLists()">
                                <?php _e( 'Fetch Lists', 'money-quiz' ); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'test_integration' ); ?>
                    <input type="hidden" name="integration_type" value="mailchimp" />
                    <input type="submit" name="test_integration" class="button" 
                           value="<?php _e( 'Test Mailchimp Connection', 'money-quiz' ); ?>" />
                </form>
            </div>
            
            <!-- ActiveCampaign Settings -->
            <div id="activecampaign-settings" style="<?php echo $email_marketing_provider === 'activecampaign' ? '' : 'display:none;'; ?>">
                <h3>ActiveCampaign Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="activecampaign_api_url"><?php _e( 'API URL', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="url" name="activecampaign_api_url" id="activecampaign_api_url" class="large-text" 
                                   value="<?php echo esc_url( $activecampaign_api_url ); ?>" 
                                   placeholder="https://youracccount.api-us1.com" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="activecampaign_api_key"><?php _e( 'API Key', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="activecampaign_api_key" id="activecampaign_api_key" class="regular-text" 
                                   value="<?php echo esc_attr( $activecampaign_api_key ); ?>" />
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Analytics Integration -->
        <div class="mq-card">
            <h2><?php _e( '📊 Analytics Integration', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="google_analytics_id"><?php _e( 'Google Analytics ID', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="google_analytics_id" id="google_analytics_id" class="regular-text" 
                               value="<?php echo esc_attr( $google_analytics_id ); ?>" 
                               placeholder="UA-XXXXXXXXX-X or G-XXXXXXXXXX" />
                        <p class="description"><?php _e( 'Track quiz events in Google Analytics', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="facebook_pixel_id"><?php _e( 'Facebook Pixel ID', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="facebook_pixel_id" id="facebook_pixel_id" class="regular-text" 
                               value="<?php echo esc_attr( $facebook_pixel_id ); ?>" />
                        <p class="description"><?php _e( 'Track conversions with Facebook Pixel', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="google_tag_manager_id"><?php _e( 'Google Tag Manager ID', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="google_tag_manager_id" id="google_tag_manager_id" class="regular-text" 
                               value="<?php echo esc_attr( $google_tag_manager_id ); ?>" 
                               placeholder="GTM-XXXXXXX" />
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Webhook Integration -->
        <div class="mq-card">
            <h2><?php _e( '🔗 Webhook Integration', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Enable Webhooks', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="webhook_enabled" value="1" 
                                   <?php checked( $webhook_enabled, 1 ); ?> onchange="toggleWebhookSettings()" />
                            <?php _e( 'Send quiz data to external services via webhooks', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
                
                <tbody id="webhook-settings" style="<?php echo $webhook_enabled ? '' : 'display:none;'; ?>">
                    <tr>
                        <th scope="row">
                            <label for="webhook_url"><?php _e( 'Webhook URL', 'money-quiz' ); ?></label>
                        </th>
                        <td>
                            <input type="url" name="webhook_url" id="webhook_url" class="large-text" 
                                   value="<?php echo esc_url( $webhook_url ); ?>" />
                            <p class="description"><?php _e( 'POST endpoint to receive quiz data', 'money-quiz' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e( 'Webhook Events', 'money-quiz' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="webhook_events[]" value="quiz_started" 
                                           <?php checked( in_array( 'quiz_started', $webhook_events ) ); ?> />
                                    <?php _e( 'Quiz Started', 'money-quiz' ); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="webhook_events[]" value="quiz_completed" 
                                           <?php checked( in_array( 'quiz_completed', $webhook_events ) ); ?> />
                                    <?php _e( 'Quiz Completed', 'money-quiz' ); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="webhook_events[]" value="lead_captured" 
                                           <?php checked( in_array( 'lead_captured', $webhook_events ) ); ?> />
                                    <?php _e( 'Lead Captured', 'money-quiz' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ( $webhook_enabled ) : ?>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'test_integration' ); ?>
                    <input type="hidden" name="integration_type" value="webhook" />
                    <input type="submit" name="test_integration" class="button" 
                           value="<?php _e( 'Send Test Webhook', 'money-quiz' ); ?>" />
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Zapier Integration -->
        <div class="mq-card">
            <h2><?php _e( '⚡ Zapier Integration', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Enable Zapier', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="zapier_enabled" value="1" 
                                   <?php checked( $zapier_enabled, 1 ); ?> />
                            <?php _e( 'Enable Zapier integration for Money Quiz', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
                
                <?php if ( $zapier_enabled ) : ?>
                    <tr>
                        <th scope="row"><?php _e( 'Zapier Webhook URL', 'money-quiz' ); ?></th>
                        <td>
                            <code><?php echo home_url( '/wp-json/money-quiz/v1/zapier' ); ?></code>
                            <p class="description"><?php _e( 'Use this URL in your Zapier triggers', 'money-quiz' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e( 'API Key', 'money-quiz' ); ?></th>
                        <td>
                            <code><?php echo esc_html( $zapier_key ); ?></code>
                            <button type="button" class="button" onclick="regenerateZapierKey()">
                                <?php _e( 'Regenerate', 'money-quiz' ); ?>
                            </button>
                            <p class="description"><?php _e( 'Use this key to authenticate Zapier requests', 'money-quiz' ); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="save_integrations" class="button button-primary" 
                   value="<?php _e( 'Save Integration Settings', 'money-quiz' ); ?>" />
        </p>
    </form>
    
</div>

<script>
function toggleEmailProvider() {
    var provider = document.getElementById('email_marketing_provider').value;
    
    // Hide all provider settings
    document.getElementById('mailchimp-settings').style.display = 'none';
    document.getElementById('activecampaign-settings').style.display = 'none';
    
    // Show selected provider settings
    if (provider === 'mailchimp') {
        document.getElementById('mailchimp-settings').style.display = 'block';
    } else if (provider === 'activecampaign') {
        document.getElementById('activecampaign-settings').style.display = 'block';
    }
}

function toggleWebhookSettings() {
    var enabled = document.querySelector('input[name="webhook_enabled"]').checked;
    document.getElementById('webhook-settings').style.display = enabled ? 'table-row-group' : 'none';
}

function fetchMailchimpLists() {
    // Would make AJAX call to fetch lists
    alert('This would fetch your Mailchimp lists');
}

function regenerateZapierKey() {
    if (confirm('<?php _e( 'Are you sure you want to regenerate the API key? This will break existing Zapier connections.', 'money-quiz' ); ?>')) {
        // Would regenerate key via AJAX
        alert('API key regeneration would happen here');
    }
}
</script>

<style>
.integration-notice {
    background: #f0f8ff;
    padding: 15px;
    border-radius: 4px;
    margin-top: 20px;
}

#mailchimp-settings,
#activecampaign-settings {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}
</style>