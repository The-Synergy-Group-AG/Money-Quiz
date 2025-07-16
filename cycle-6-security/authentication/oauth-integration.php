<?php
/**
 * OAuth2 and SAML Integration
 * 
 * Provides OAuth2 and SAML authentication support
 * 
 * @package MoneyQuiz\Security\Authentication
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Authentication;

use League\OAuth2\Client\Provider\GenericProvider;
use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Settings as SamlSettings;
use Exception;

class OAuthIntegration {
    private $db;
    private $providers = [];
    private $saml_settings = [];
    private $config;
    
    public function __construct($config = []) {
        $this->db = $GLOBALS['wpdb'];
        
        $this->config = wp_parse_args($config, [
            'enable_oauth' => true,
            'enable_saml' => true,
            'auto_create_users' => true,
            'sync_user_data' => true,
            'default_role' => 'quiz_taker',
            'session_timeout' => 3600,
            'secure_cookies' => true
        ]);
        
        $this->init_providers();
        $this->init_hooks();
    }
    
    /**
     * Initialize OAuth providers
     */
    private function init_providers() {
        // Google OAuth2
        $this->providers['google'] = [
            'name' => 'Google',
            'enabled' => get_option('money_quiz_oauth_google_enabled', false),
            'config' => [
                'clientId' => get_option('money_quiz_oauth_google_client_id'),
                'clientSecret' => get_option('money_quiz_oauth_google_client_secret'),
                'redirectUri' => $this->get_callback_url('google'),
                'urlAuthorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'urlAccessToken' => 'https://oauth2.googleapis.com/token',
                'urlResourceOwnerDetails' => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scopes' => ['openid', 'email', 'profile']
            ]
        ];
        
        // Microsoft OAuth2
        $this->providers['microsoft'] = [
            'name' => 'Microsoft',
            'enabled' => get_option('money_quiz_oauth_microsoft_enabled', false),
            'config' => [
                'clientId' => get_option('money_quiz_oauth_microsoft_client_id'),
                'clientSecret' => get_option('money_quiz_oauth_microsoft_client_secret'),
                'redirectUri' => $this->get_callback_url('microsoft'),
                'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
                'scopes' => ['openid', 'email', 'profile', 'User.Read']
            ]
        ];
        
        // Facebook OAuth2
        $this->providers['facebook'] = [
            'name' => 'Facebook',
            'enabled' => get_option('money_quiz_oauth_facebook_enabled', false),
            'config' => [
                'clientId' => get_option('money_quiz_oauth_facebook_app_id'),
                'clientSecret' => get_option('money_quiz_oauth_facebook_app_secret'),
                'redirectUri' => $this->get_callback_url('facebook'),
                'urlAuthorize' => 'https://www.facebook.com/v12.0/dialog/oauth',
                'urlAccessToken' => 'https://graph.facebook.com/v12.0/oauth/access_token',
                'urlResourceOwnerDetails' => 'https://graph.facebook.com/v12.0/me?fields=id,email,name',
                'scopes' => ['email', 'public_profile']
            ]
        ];
        
        // LinkedIn OAuth2
        $this->providers['linkedin'] = [
            'name' => 'LinkedIn',
            'enabled' => get_option('money_quiz_oauth_linkedin_enabled', false),
            'config' => [
                'clientId' => get_option('money_quiz_oauth_linkedin_client_id'),
                'clientSecret' => get_option('money_quiz_oauth_linkedin_client_secret'),
                'redirectUri' => $this->get_callback_url('linkedin'),
                'urlAuthorize' => 'https://www.linkedin.com/oauth/v2/authorization',
                'urlAccessToken' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'urlResourceOwnerDetails' => 'https://api.linkedin.com/v2/me',
                'scopes' => ['r_liteprofile', 'r_emailaddress']
            ]
        ];
        
        // Custom OAuth2 provider
        $this->providers['custom'] = [
            'name' => get_option('money_quiz_oauth_custom_name', 'Custom SSO'),
            'enabled' => get_option('money_quiz_oauth_custom_enabled', false),
            'config' => [
                'clientId' => get_option('money_quiz_oauth_custom_client_id'),
                'clientSecret' => get_option('money_quiz_oauth_custom_client_secret'),
                'redirectUri' => $this->get_callback_url('custom'),
                'urlAuthorize' => get_option('money_quiz_oauth_custom_authorize_url'),
                'urlAccessToken' => get_option('money_quiz_oauth_custom_token_url'),
                'urlResourceOwnerDetails' => get_option('money_quiz_oauth_custom_userinfo_url'),
                'scopes' => explode(' ', get_option('money_quiz_oauth_custom_scopes', 'openid email profile'))
            ]
        ];
        
        // SAML settings
        $this->saml_settings = [
            'sp' => [
                'entityId' => get_option('money_quiz_saml_sp_entity_id', site_url()),
                'assertionConsumerService' => [
                    'url' => $this->get_callback_url('saml'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'
                ],
                'singleLogoutService' => [
                    'url' => $this->get_logout_url('saml'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'x509cert' => get_option('money_quiz_saml_sp_cert'),
                'privateKey' => get_option('money_quiz_saml_sp_key')
            ],
            'idp' => [
                'entityId' => get_option('money_quiz_saml_idp_entity_id'),
                'singleSignOnService' => [
                    'url' => get_option('money_quiz_saml_idp_sso_url'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                ],
                'singleLogoutService' => [
                    'url' => get_option('money_quiz_saml_idp_slo_url'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                ],
                'x509cert' => get_option('money_quiz_saml_idp_cert')
            ],
            'security' => [
                'nameIdEncrypted' => false,
                'authnRequestsSigned' => true,
                'logoutRequestSigned' => true,
                'logoutResponseSigned' => true,
                'signMetadata' => true,
                'wantMessagesSigned' => true,
                'wantAssertionsSigned' => true,
                'wantNameId' => true,
                'wantAssertionsEncrypted' => false,
                'wantNameIdEncrypted' => false,
                'requestedAuthnContext' => true,
                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'
            ]
        ];
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // OAuth endpoints
        add_action('init', [$this, 'register_endpoints']);
        add_action('template_redirect', [$this, 'handle_oauth_callback']);
        
        // Login form modifications
        add_action('login_form', [$this, 'add_sso_buttons']);
        add_filter('login_message', [$this, 'add_sso_message']);
        
        // User profile
        add_action('show_user_profile', [$this, 'show_linked_accounts']);
        add_action('edit_user_profile', [$this, 'show_linked_accounts']);
        
        // AJAX handlers
        add_action('wp_ajax_unlink_oauth_account', [$this, 'handle_unlink_account']);
        add_action('wp_ajax_nopriv_oauth_state_check', [$this, 'check_oauth_state']);
    }
    
    /**
     * Register OAuth endpoints
     */
    public function register_endpoints() {
        add_rewrite_rule(
            '^oauth/([^/]+)/callback/?$',
            'index.php?oauth_provider=$matches[1]&oauth_callback=1',
            'top'
        );
        
        add_rewrite_rule(
            '^saml/acs/?$',
            'index.php?saml_acs=1',
            'top'
        );
        
        add_rewrite_rule(
            '^saml/sls/?$',
            'index.php?saml_sls=1',
            'top'
        );
    }
    
    /**
     * Get callback URL for provider
     */
    private function get_callback_url($provider) {
        return site_url('/oauth/' . $provider . '/callback/');
    }
    
    /**
     * Get logout URL for provider
     */
    private function get_logout_url($provider) {
        return site_url('/oauth/' . $provider . '/logout/');
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        $provider = get_query_var('oauth_provider');
        $is_callback = get_query_var('oauth_callback');
        
        if (!$provider || !$is_callback) {
            return;
        }
        
        try {
            if ($provider === 'saml') {
                $this->handle_saml_callback();
            } else {
                $this->handle_oauth2_callback($provider);
            }
        } catch (Exception $e) {
            $this->handle_auth_error($e->getMessage());
        }
    }
    
    /**
     * Handle OAuth2 callback
     */
    private function handle_oauth2_callback($provider_key) {
        if (!isset($this->providers[$provider_key]) || !$this->providers[$provider_key]['enabled']) {
            throw new Exception('Provider not available');
        }
        
        // Verify state
        if (!isset($_GET['state']) || !$this->verify_oauth_state($_GET['state'])) {
            throw new Exception('Invalid state parameter');
        }
        
        // Check for errors
        if (isset($_GET['error'])) {
            throw new Exception('OAuth error: ' . sanitize_text_field($_GET['error']));
        }
        
        // Get access token
        $provider = new GenericProvider($this->providers[$provider_key]['config']);
        
        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);
            
            // Get user details
            $resource_owner = $provider->getResourceOwner($token);
            $user_data = $resource_owner->toArray();
            
            // Process authentication
            $this->process_oauth_login($provider_key, $user_data, $token->getToken());
            
        } catch (Exception $e) {
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle SAML callback
     */
    private function handle_saml_callback() {
        if (!$this->config['enable_saml']) {
            throw new Exception('SAML authentication is not enabled');
        }
        
        try {
            $auth = new SamlAuth($this->saml_settings);
            $auth->processResponse();
            
            if (!$auth->isAuthenticated()) {
                $errors = $auth->getErrors();
                throw new Exception('SAML authentication failed: ' . implode(', ', $errors));
            }
            
            $attributes = $auth->getAttributes();
            $nameid = $auth->getNameId();
            
            $user_data = [
                'email' => $nameid,
                'name' => $attributes['displayName'][0] ?? '',
                'first_name' => $attributes['givenName'][0] ?? '',
                'last_name' => $attributes['surname'][0] ?? '',
                'saml_session_index' => $auth->getSessionIndex(),
                'saml_nameid' => $nameid,
                'saml_nameid_format' => $auth->getNameIdFormat()
            ];
            
            $this->process_oauth_login('saml', $user_data, $auth->getSessionIndex());
            
        } catch (Exception $e) {
            throw new Exception('SAML error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process OAuth login
     */
    private function process_oauth_login($provider, $user_data, $access_token) {
        // Normalize user data
        $normalized = $this->normalize_user_data($provider, $user_data);
        
        // Check if user exists
        $user = $this->find_user_by_oauth($provider, $normalized['provider_id']);
        
        if (!$user && !empty($normalized['email'])) {
            $user = get_user_by('email', $normalized['email']);
        }
        
        if (!$user) {
            if ($this->config['auto_create_users']) {
                $user = $this->create_oauth_user($provider, $normalized);
            } else {
                throw new Exception('No matching user account found');
            }
        } else {
            // Link OAuth account if not already linked
            $this->link_oauth_account($user->ID, $provider, $normalized['provider_id'], $access_token);
            
            // Sync user data if enabled
            if ($this->config['sync_user_data']) {
                $this->sync_user_data($user->ID, $normalized);
            }
        }
        
        // Log the user in
        $this->perform_login($user->ID, $provider);
        
        // Redirect to intended destination
        $redirect_to = get_transient('oauth_redirect_' . $this->get_state_key()) ?: home_url();
        delete_transient('oauth_redirect_' . $this->get_state_key());
        
        wp_safe_redirect($redirect_to);
        exit;
    }
    
    /**
     * Normalize user data from different providers
     */
    private function normalize_user_data($provider, $data) {
        $normalized = [
            'provider_id' => '',
            'email' => '',
            'name' => '',
            'first_name' => '',
            'last_name' => '',
            'avatar' => ''
        ];
        
        switch ($provider) {
            case 'google':
                $normalized['provider_id'] = $data['sub'] ?? '';
                $normalized['email'] = $data['email'] ?? '';
                $normalized['name'] = $data['name'] ?? '';
                $normalized['first_name'] = $data['given_name'] ?? '';
                $normalized['last_name'] = $data['family_name'] ?? '';
                $normalized['avatar'] = $data['picture'] ?? '';
                break;
                
            case 'microsoft':
                $normalized['provider_id'] = $data['id'] ?? '';
                $normalized['email'] = $data['userPrincipalName'] ?? $data['mail'] ?? '';
                $normalized['name'] = $data['displayName'] ?? '';
                $normalized['first_name'] = $data['givenName'] ?? '';
                $normalized['last_name'] = $data['surname'] ?? '';
                break;
                
            case 'facebook':
                $normalized['provider_id'] = $data['id'] ?? '';
                $normalized['email'] = $data['email'] ?? '';
                $normalized['name'] = $data['name'] ?? '';
                $parts = explode(' ', $normalized['name']);
                $normalized['first_name'] = $parts[0] ?? '';
                $normalized['last_name'] = $parts[1] ?? '';
                break;
                
            case 'linkedin':
                $normalized['provider_id'] = $data['id'] ?? '';
                $normalized['email'] = $data['emailAddress'] ?? '';
                $normalized['name'] = $data['localizedFirstName'] . ' ' . $data['localizedLastName'];
                $normalized['first_name'] = $data['localizedFirstName'] ?? '';
                $normalized['last_name'] = $data['localizedLastName'] ?? '';
                break;
                
            case 'saml':
                $normalized['provider_id'] = $data['saml_nameid'] ?? '';
                $normalized['email'] = $data['email'] ?? '';
                $normalized['name'] = $data['name'] ?? '';
                $normalized['first_name'] = $data['first_name'] ?? '';
                $normalized['last_name'] = $data['last_name'] ?? '';
                break;
                
            case 'custom':
                // Allow custom mapping via filter
                $normalized = apply_filters('money_quiz_oauth_normalize_custom', $normalized, $data);
                break;
        }
        
        return apply_filters('money_quiz_oauth_normalize_data', $normalized, $provider, $data);
    }
    
    /**
     * Find user by OAuth provider
     */
    private function find_user_by_oauth($provider, $provider_id) {
        $user_id = $this->db->get_var($this->db->prepare(
            "SELECT user_id FROM {$this->db->prefix}money_quiz_oauth_accounts 
            WHERE provider = %s AND provider_id = %s",
            $provider,
            $provider_id
        ));
        
        return $user_id ? get_user_by('id', $user_id) : false;
    }
    
    /**
     * Create OAuth user
     */
    private function create_oauth_user($provider, $user_data) {
        // Generate username
        $username = $this->generate_unique_username($user_data);
        
        // Create user
        $user_id = wp_create_user(
            $username,
            wp_generate_password(),
            $user_data['email']
        );
        
        if (is_wp_error($user_id)) {
            throw new Exception('Failed to create user: ' . $user_id->get_error_message());
        }
        
        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $user_data['first_name'],
            'last_name' => $user_data['last_name'],
            'display_name' => $user_data['name']
        ]);
        
        // Set default role
        $user = new \WP_User($user_id);
        $user->set_role($this->config['default_role']);
        
        // Link OAuth account
        $this->link_oauth_account($user_id, $provider, $user_data['provider_id'], '');
        
        // Set avatar if available
        if (!empty($user_data['avatar'])) {
            update_user_meta($user_id, 'oauth_avatar', $user_data['avatar']);
        }
        
        // Log account creation
        $this->log_oauth_event($user_id, 'account_created', [
            'provider' => $provider,
            'email' => $user_data['email']
        ]);
        
        return get_user_by('id', $user_id);
    }
    
    /**
     * Generate unique username
     */
    private function generate_unique_username($user_data) {
        $base = '';
        
        if (!empty($user_data['email'])) {
            $base = strstr($user_data['email'], '@', true);
        } elseif (!empty($user_data['first_name'])) {
            $base = strtolower($user_data['first_name']);
        } else {
            $base = 'user';
        }
        
        $base = sanitize_user($base);
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Link OAuth account
     */
    private function link_oauth_account($user_id, $provider, $provider_id, $access_token) {
        $existing = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->db->prefix}money_quiz_oauth_accounts 
            WHERE user_id = %d AND provider = %s",
            $user_id,
            $provider
        ));
        
        if ($existing) {
            // Update existing link
            $this->db->update(
                $this->db->prefix . 'money_quiz_oauth_accounts',
                [
                    'provider_id' => $provider_id,
                    'access_token' => $this->encrypt_token($access_token),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing]
            );
        } else {
            // Create new link
            $this->db->insert(
                $this->db->prefix . 'money_quiz_oauth_accounts',
                [
                    'user_id' => $user_id,
                    'provider' => $provider,
                    'provider_id' => $provider_id,
                    'access_token' => $this->encrypt_token($access_token),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            );
        }
    }
    
    /**
     * Sync user data
     */
    private function sync_user_data($user_id, $user_data) {
        $updates = [];
        
        if (!empty($user_data['first_name'])) {
            $updates['first_name'] = $user_data['first_name'];
        }
        
        if (!empty($user_data['last_name'])) {
            $updates['last_name'] = $user_data['last_name'];
        }
        
        if (!empty($user_data['name'])) {
            $updates['display_name'] = $user_data['name'];
        }
        
        if (!empty($updates)) {
            $updates['ID'] = $user_id;
            wp_update_user($updates);
        }
        
        if (!empty($user_data['avatar'])) {
            update_user_meta($user_id, 'oauth_avatar', $user_data['avatar']);
        }
    }
    
    /**
     * Perform login
     */
    private function perform_login($user_id, $provider) {
        // Set auth cookies
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, $this->config['secure_cookies']);
        
        // Log successful login
        $this->log_oauth_event($user_id, 'login_success', [
            'provider' => $provider,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Fire login action
        do_action('wp_login', get_user_by('id', $user_id)->user_login, get_user_by('id', $user_id));
    }
    
    /**
     * Generate OAuth state
     */
    public function generate_oauth_state() {
        $state = wp_generate_password(32, false);
        set_transient('oauth_state_' . $state, true, 600); // 10 minutes
        return $state;
    }
    
    /**
     * Verify OAuth state
     */
    private function verify_oauth_state($state) {
        $valid = get_transient('oauth_state_' . $state);
        if ($valid) {
            delete_transient('oauth_state_' . $state);
            return true;
        }
        return false;
    }
    
    /**
     * Get state key for current request
     */
    private function get_state_key() {
        return substr(md5($_GET['state'] ?? ''), 0, 10);
    }
    
    /**
     * Add SSO buttons to login form
     */
    public function add_sso_buttons() {
        $enabled_providers = array_filter($this->providers, function($provider) {
            return $provider['enabled'];
        });
        
        if (empty($enabled_providers)) {
            return;
        }
        
        echo '<div class="money-quiz-sso-buttons">';
        echo '<p class="sso-divider">Or login with</p>';
        
        foreach ($enabled_providers as $key => $provider) {
            if (!$provider['enabled']) continue;
            
            $auth_url = $this->get_authorization_url($key);
            $icon_class = 'sso-icon-' . $key;
            
            echo sprintf(
                '<a href="%s" class="sso-button sso-%s">
                    <span class="%s"></span>
                    <span>%s</span>
                </a>',
                esc_url($auth_url),
                esc_attr($key),
                esc_attr($icon_class),
                esc_html($provider['name'])
            );
        }
        
        echo '</div>';
        
        // Add CSS
        $this->output_sso_styles();
    }
    
    /**
     * Get authorization URL
     */
    private function get_authorization_url($provider_key) {
        if ($provider_key === 'saml') {
            return $this->get_saml_login_url();
        }
        
        $provider = new GenericProvider($this->providers[$provider_key]['config']);
        $state = $this->generate_oauth_state();
        
        // Store redirect URL
        $redirect_to = $_GET['redirect_to'] ?? '';
        if ($redirect_to) {
            set_transient('oauth_redirect_' . substr(md5($state), 0, 10), $redirect_to, 600);
        }
        
        return $provider->getAuthorizationUrl([
            'state' => $state,
            'scope' => $this->providers[$provider_key]['config']['scopes']
        ]);
    }
    
    /**
     * Get SAML login URL
     */
    private function get_saml_login_url() {
        try {
            $auth = new SamlAuth($this->saml_settings);
            return $auth->login(null, [], false, false, true);
        } catch (Exception $e) {
            error_log('SAML login URL error: ' . $e->getMessage());
            return '#';
        }
    }
    
    /**
     * Output SSO styles
     */
    private function output_sso_styles() {
        ?>
        <style>
            .money-quiz-sso-buttons {
                margin-top: 20px;
                text-align: center;
            }
            
            .sso-divider {
                margin: 15px 0;
                color: #666;
                font-size: 14px;
            }
            
            .sso-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                margin: 5px 0;
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
                color: #333;
                text-decoration: none;
                transition: all 0.3s;
            }
            
            .sso-button:hover {
                background: #f5f5f5;
                border-color: #999;
            }
            
            .sso-google { border-color: #4285f4; color: #4285f4; }
            .sso-microsoft { border-color: #0078d4; color: #0078d4; }
            .sso-facebook { border-color: #1877f2; color: #1877f2; }
            .sso-linkedin { border-color: #0077b5; color: #0077b5; }
            
            .sso-button span:first-child {
                margin-right: 10px;
                font-size: 18px;
            }
        </style>
        <?php
    }
    
    /**
     * Show linked accounts in user profile
     */
    public function show_linked_accounts($user) {
        $linked_accounts = $this->db->get_results($this->db->prepare(
            "SELECT provider, created_at FROM {$this->db->prefix}money_quiz_oauth_accounts 
            WHERE user_id = %d",
            $user->ID
        ));
        
        ?>
        <h3>Linked Social Accounts</h3>
        <table class="form-table">
            <tr>
                <th>Connected Accounts</th>
                <td>
                    <?php if (empty($linked_accounts)): ?>
                        <p>No linked accounts</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($linked_accounts as $account): ?>
                                <li>
                                    <?php echo esc_html($this->providers[$account->provider]['name'] ?? $account->provider); ?>
                                    (linked <?php echo esc_html(date('M j, Y', strtotime($account->created_at))); ?>)
                                    <?php if (get_current_user_id() === $user->ID): ?>
                                        <a href="#" class="unlink-oauth-account" data-provider="<?php echo esc_attr($account->provider); ?>">Unlink</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <script>
            jQuery(document).ready(function($) {
                $('.unlink-oauth-account').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm('Are you sure you want to unlink this account?')) {
                        return;
                    }
                    
                    var provider = $(this).data('provider');
                    var $link = $(this);
                    
                    $.post(ajaxurl, {
                        action: 'unlink_oauth_account',
                        provider: provider,
                        _wpnonce: '<?php echo wp_create_nonce('unlink_oauth_account'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $link.parent().fadeOut();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Handle unlink account AJAX
     */
    public function handle_unlink_account() {
        check_ajax_referer('unlink_oauth_account');
        
        $provider = sanitize_text_field($_POST['provider']);
        $user_id = get_current_user_id();
        
        $this->db->delete(
            $this->db->prefix . 'money_quiz_oauth_accounts',
            [
                'user_id' => $user_id,
                'provider' => $provider
            ]
        );
        
        $this->log_oauth_event($user_id, 'account_unlinked', ['provider' => $provider]);
        
        wp_send_json_success();
    }
    
    /**
     * Handle authentication error
     */
    private function handle_auth_error($message) {
        $redirect = wp_login_url();
        $redirect = add_query_arg('oauth_error', urlencode($message), $redirect);
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Add SSO error message
     */
    public function add_sso_message($message) {
        if (isset($_GET['oauth_error'])) {
            $error = sanitize_text_field($_GET['oauth_error']);
            $message .= '<div class="message error"><p>Authentication error: ' . esc_html($error) . '</p></div>';
        }
        return $message;
    }
    
    /**
     * Encrypt token
     */
    private function encrypt_token($token) {
        if (empty($token)) {
            return '';
        }
        
        $key = hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT . 'oauth_token');
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt token
     */
    private function decrypt_token($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        $key = hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT . 'oauth_token');
        $data = base64_decode($encrypted);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Log OAuth events
     */
    private function log_oauth_event($user_id, $event, $data = []) {
        $this->db->insert(
            $this->db->prefix . 'money_quiz_oauth_logs',
            [
                'user_id' => $user_id,
                'event' => $event,
                'data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // OAuth accounts table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_oauth_accounts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            provider varchar(50) NOT NULL,
            provider_id varchar(255) NOT NULL,
            access_token text,
            refresh_token text,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_provider (user_id, provider),
            KEY provider_id (provider, provider_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // OAuth logs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_oauth_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            event varchar(50) NOT NULL,
            data text,
            ip_address varchar(45),
            user_agent text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY event (event),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}

// Initialize on plugin activation
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Authentication\OAuthIntegration', 'create_tables']);