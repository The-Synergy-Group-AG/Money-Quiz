<?php
/**
 * Worker 8: Credential Security - Remove Hardcoded Credentials
 * CVSS: 7.5 (High)
 * Focus: Migrate credentials to environment variables and wp-config.php
 */

// PATCH 1: Create configuration loader class
class MoneyQuizConfig {
    
    private static $config = null;
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        if (self::$config === null) {
            self::load_config();
        }
        
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }
    
    /**
     * Load configuration from environment
     */
    private static function load_config() {
        self::$config = array();
        
        // First priority: Environment variables
        $env_mappings = array(
            'MQ_BUSINESS_EMAIL' => 'business_insights_email',
            'MQ_SECRET_KEY' => 'special_secret_key',
            'MQ_LICENSE_SERVER' => 'license_server_url',
            'MQ_ITEM_REFERENCE' => 'item_reference',
            'MQ_SMTP_HOST' => 'smtp_host',
            'MQ_SMTP_USER' => 'smtp_user',
            'MQ_SMTP_PASS' => 'smtp_password',
            'MQ_SMTP_PORT' => 'smtp_port',
            'MQ_SMTP_SECURE' => 'smtp_secure'
        );
        
        foreach ($env_mappings as $env_key => $config_key) {
            $value = getenv($env_key);
            if ($value !== false) {
                self::$config[$config_key] = $value;
            }
        }
        
        // Second priority: WordPress constants (wp-config.php)
        $wp_constants = array(
            'MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL' => 'business_insights_email',
            'MONEYQUIZ_SPECIAL_SECRET_KEY' => 'special_secret_key',
            'MONEYQUIZ_LICENSE_SERVER_URL' => 'license_server_url',
            'MONEYQUIZ_ITEM_REFERENCE' => 'item_reference'
        );
        
        foreach ($wp_constants as $constant => $config_key) {
            if (defined($constant) && !isset(self::$config[$config_key])) {
                self::$config[$config_key] = constant($constant);
            }
        }
        
        // Third priority: Database options (encrypted)
        $db_options = get_option('moneyquiz_secure_config', array());
        if (!empty($db_options) && is_array($db_options)) {
            foreach ($db_options as $key => $value) {
                if (!isset(self::$config[$key])) {
                    self::$config[$key] = self::decrypt_value($value);
                }
            }
        }
        
        // Apply filters for customization
        self::$config = apply_filters('moneyquiz_config', self::$config);
    }
    
    /**
     * Encrypt sensitive value
     */
    private static function encrypt_value($value) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($value); // Fallback if OpenSSL not available
        }
        
        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);
        
        return base64_encode(openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv));
    }
    
    /**
     * Decrypt sensitive value
     */
    private static function decrypt_value($encrypted) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted); // Fallback if OpenSSL not available
        }
        
        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);
        
        return openssl_decrypt(base64_decode($encrypted), 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Save configuration to database (encrypted)
     */
    public static function save_to_database($config) {
        $encrypted = array();
        
        $sensitive_keys = array(
            'special_secret_key',
            'smtp_password',
            'api_keys'
        );
        
        foreach ($config as $key => $value) {
            if (in_array($key, $sensitive_keys)) {
                $encrypted[$key] = self::encrypt_value($value);
            } else {
                $encrypted[$key] = $value;
            }
        }
        
        update_option('moneyquiz_secure_config', $encrypted);
    }
}

// PATCH 2: Replace hardcoded credentials in moneyquiz.php
// OLD: define('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', 'andre@101businessinsights.info');
// NEW:
if (!defined('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL')) {
    define('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', MoneyQuizConfig::get('business_insights_email', ''));
}

// OLD: define('MONEYQUIZ_SPECIAL_SECRET_KEY', '5bcd52f5276855.46942741');
// NEW:
if (!defined('MONEYQUIZ_SPECIAL_SECRET_KEY')) {
    define('MONEYQUIZ_SPECIAL_SECRET_KEY', MoneyQuizConfig::get('special_secret_key', ''));
}

// OLD: define('MONEYQUIZ_LICENSE_SERVER_URL', 'https://www.101businessinsights.com');
// NEW:
if (!defined('MONEYQUIZ_LICENSE_SERVER_URL')) {
    define('MONEYQUIZ_LICENSE_SERVER_URL', MoneyQuizConfig::get('license_server_url', ''));
}

// PATCH 3: Admin settings page for secure configuration
class MoneyQuizSecureSettings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_settings_page() {
        add_submenu_page(
            'mq_questions',
            __('Secure Configuration', 'money-quiz'),
            __('Secure Config', 'money-quiz'),
            'manage_options',
            'mq_secure_config',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('moneyquiz_secure_settings', 'moneyquiz_secure_config');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Money Quiz Secure Configuration', 'money-quiz'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php esc_html_e('For maximum security, configure these values in your wp-config.php file or as environment variables instead of using this form.', 'money-quiz'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('moneyquiz_secure_settings'); ?>
                
                <h2><?php esc_html_e('API Configuration', 'money-quiz'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="business_email">
                                <?php esc_html_e('Business Insights Email', 'money-quiz'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="business_email" 
                                   name="moneyquiz_secure_config[business_insights_email]" 
                                   value="<?php echo esc_attr(MoneyQuizConfig::get('business_insights_email')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Or define MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL in wp-config.php', 'money-quiz'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="secret_key">
                                <?php esc_html_e('API Secret Key', 'money-quiz'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="secret_key" 
                                   name="moneyquiz_secure_config[special_secret_key]" 
                                   value="<?php echo esc_attr(MoneyQuizConfig::get('special_secret_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Or define MONEYQUIZ_SPECIAL_SECRET_KEY in wp-config.php', 'money-quiz'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="license_server">
                                <?php esc_html_e('License Server URL', 'money-quiz'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="license_server" 
                                   name="moneyquiz_secure_config[license_server_url]" 
                                   value="<?php echo esc_url(MoneyQuizConfig::get('license_server_url')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Or define MONEYQUIZ_LICENSE_SERVER_URL in wp-config.php', 'money-quiz'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Email Configuration', 'money-quiz'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="smtp_host">
                                <?php esc_html_e('SMTP Host', 'money-quiz'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="smtp_host" 
                                   name="moneyquiz_secure_config[smtp_host]" 
                                   value="<?php echo esc_attr(MoneyQuizConfig::get('smtp_host')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="smtp_user">
                                <?php esc_html_e('SMTP Username', 'money-quiz'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="smtp_user" 
                                   name="moneyquiz_secure_config[smtp_user]" 
                                   value="<?php echo esc_attr(MoneyQuizConfig::get('smtp_user')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="smtp_pass">
                                <?php esc_html_e('SMTP Password', 'money-quiz'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="smtp_pass" 
                                   name="moneyquiz_secure_config[smtp_password]" 
                                   value="<?php echo esc_attr(MoneyQuizConfig::get('smtp_password')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr />
            
            <h2><?php esc_html_e('wp-config.php Example', 'money-quiz'); ?></h2>
            <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto;">
// Money Quiz Configuration
define('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', 'your-email@example.com');
define('MONEYQUIZ_SPECIAL_SECRET_KEY', 'your-secret-key-here');
define('MONEYQUIZ_LICENSE_SERVER_URL', 'https://your-license-server.com');
define('MONEYQUIZ_ITEM_REFERENCE', 'MoneyQuiz Plugin Key');
            </pre>
            
            <h2><?php esc_html_e('Environment Variables (.env)', 'money-quiz'); ?></h2>
            <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto;">
# Money Quiz Configuration
MQ_BUSINESS_EMAIL=your-email@example.com
MQ_SECRET_KEY=your-secret-key-here
MQ_LICENSE_SERVER=https://your-license-server.com
MQ_ITEM_REFERENCE=MoneyQuiz Plugin Key

# SMTP Configuration
MQ_SMTP_HOST=smtp.example.com
MQ_SMTP_USER=smtp-username
MQ_SMTP_PASS=smtp-password
MQ_SMTP_PORT=587
MQ_SMTP_SECURE=tls
            </pre>
        </div>
        <?php
    }
}

// Initialize secure settings
new MoneyQuizSecureSettings();

// PATCH 4: Credential validation helper
class MoneyQuizCredentialValidator {
    
    /**
     * Check if all required credentials are configured
     */
    public static function validate() {
        $required = array(
            'business_insights_email' => __('Business Insights Email', 'money-quiz'),
            'special_secret_key' => __('API Secret Key', 'money-quiz'),
            'license_server_url' => __('License Server URL', 'money-quiz')
        );
        
        $missing = array();
        
        foreach ($required as $key => $label) {
            $value = MoneyQuizConfig::get($key);
            if (empty($value)) {
                $missing[] = $label;
            }
        }
        
        if (!empty($missing)) {
            add_action('admin_notices', function() use ($missing) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php esc_html_e('Money Quiz Configuration Required:', 'money-quiz'); ?></strong>
                        <?php 
                        echo esc_html(sprintf(
                            __('Please configure the following: %s', 'money-quiz'),
                            implode(', ', $missing)
                        ));
                        ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mq_secure_config')); ?>">
                            <?php esc_html_e('Configure Now', 'money-quiz'); ?>
                        </a>
                    </p>
                </div>
                <?php
            });
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Test API connection
     */
    public static function test_api_connection() {
        $license_server = MoneyQuizConfig::get('license_server_url');
        $secret_key = MoneyQuizConfig::get('special_secret_key');
        
        if (empty($license_server) || empty($secret_key)) {
            return array(
                'success' => false,
                'message' => __('Missing API credentials', 'money-quiz')
            );
        }
        
        $response = wp_remote_get($license_server . '/api/test', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            return array(
                'success' => true,
                'message' => __('API connection successful', 'money-quiz')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code: %d', 'money-quiz'), $code)
            );
        }
    }
}

// PATCH 5: Migration script for existing installations
function moneyquiz_migrate_credentials() {
    // Check if migration already done
    if (get_option('moneyquiz_credentials_migrated')) {
        return;
    }
    
    // Notify admin about credential migration
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Money Quiz Security Update:', 'money-quiz'); ?></strong>
                <?php esc_html_e('Hardcoded credentials have been removed for security. Please configure your credentials in wp-config.php or through the secure configuration page.', 'money-quiz'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mq_secure_config')); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e('Configure Now', 'money-quiz'); ?>
                </a>
            </p>
        </div>
        <?php
    });
    
    // Mark migration as complete
    update_option('moneyquiz_credentials_migrated', true);
}
add_action('admin_init', 'moneyquiz_migrate_credentials');