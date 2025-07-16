<?php
/**
 * Data Encryption at Rest
 * 
 * Implements encryption for sensitive data storage
 * 
 * @package MoneyQuiz\Security\Encryption
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Encryption;

use Exception;

class DataEncryption {
    private $db;
    private $encryption_method = 'AES-256-GCM';
    private $key_derivation_iterations = 100000;
    private $config;
    
    // Fields that should be encrypted
    private $encrypted_fields = [
        'quiz_results' => ['personal_data', 'answers', 'ip_address'],
        'user_profiles' => ['phone', 'address', 'social_security'],
        'payment_info' => ['card_number', 'cvv', 'billing_address'],
        'api_keys' => ['key_value', 'secret'],
        'audit_logs' => ['sensitive_data']
    ];
    
    public function __construct($config = []) {
        $this->db = $GLOBALS['wpdb'];
        
        $this->config = wp_parse_args($config, [
            'auto_encrypt' => true,
            'key_rotation_days' => 90,
            'backup_encryption' => true,
            'field_level_encryption' => true,
            'transparent_encryption' => false,
            'compression' => true,
            'audit_decryption' => true
        ]);
        
        $this->init_hooks();
        $this->verify_encryption_setup();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Auto-encrypt on save
        if ($this->config['auto_encrypt']) {
            add_filter('pre_update_option', [$this, 'encrypt_option'], 10, 3);
            add_filter('pre_add_user_meta', [$this, 'encrypt_user_meta'], 10, 3);
            add_filter('pre_update_user_meta', [$this, 'encrypt_user_meta'], 10, 3);
        }
        
        // Auto-decrypt on read
        add_filter('option_value', [$this, 'decrypt_option'], 10, 2);
        add_filter('get_user_metadata', [$this, 'decrypt_user_meta'], 10, 4);
        
        // Database query filters
        add_filter('query', [$this, 'handle_database_encryption']);
        
        // Key rotation schedule
        add_action('money_quiz_rotate_encryption_keys', [$this, 'rotate_encryption_keys']);
        
        if (!wp_next_scheduled('money_quiz_rotate_encryption_keys')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_rotate_encryption_keys');
        }
    }
    
    /**
     * Verify encryption setup
     */
    private function verify_encryption_setup() {
        // Check if encryption keys exist
        if (!$this->get_master_key()) {
            $this->generate_master_key();
        }
        
        // Verify OpenSSL support
        if (!extension_loaded('openssl')) {
            throw new Exception('OpenSSL extension is required for encryption');
        }
        
        // Check supported ciphers
        $ciphers = openssl_get_cipher_methods();
        if (!in_array(strtolower($this->encryption_method), array_map('strtolower', $ciphers))) {
            throw new Exception('Encryption method not supported: ' . $this->encryption_method);
        }
    }
    
    /**
     * Encrypt data
     */
    public function encrypt($data, $context = '') {
        if (empty($data)) {
            return $data;
        }
        
        try {
            // Serialize if needed
            if (!is_string($data)) {
                $data = serialize($data);
            }
            
            // Compress if enabled
            if ($this->config['compression']) {
                $data = gzcompress($data, 9);
            }
            
            // Get encryption key
            $key = $this->get_encryption_key($context);
            
            // Generate IV
            $iv_length = openssl_cipher_iv_length($this->encryption_method);
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            // Generate tag for AEAD
            $tag = '';
            
            // Encrypt
            $encrypted = openssl_encrypt(
                $data,
                $this->encryption_method,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $context
            );
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }
            
            // Combine components
            $payload = [
                'method' => $this->encryption_method,
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'data' => base64_encode($encrypted),
                'context' => $context,
                'compressed' => $this->config['compression'],
                'timestamp' => time(),
                'key_version' => $this->get_current_key_version()
            ];
            
            // Sign payload
            $payload['signature'] = $this->sign_payload($payload);
            
            // Encode
            $encoded = base64_encode(json_encode($payload));
            
            // Log encryption event
            if ($this->config['audit_decryption']) {
                $this->log_encryption_event('encrypt', $context, strlen($data), strlen($encoded));
            }
            
            return $encoded;
            
        } catch (Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            
            // Fail open or closed based on config
            if ($this->config['fail_closed'] ?? true) {
                throw $e;
            }
            
            return $data;
        }
    }
    
    /**
     * Decrypt data
     */
    public function decrypt($encrypted_data, $expected_context = '') {
        if (empty($encrypted_data)) {
            return $encrypted_data;
        }
        
        // Check if data is encrypted
        if (!$this->is_encrypted($encrypted_data)) {
            return $encrypted_data;
        }
        
        try {
            // Decode payload
            $payload = json_decode(base64_decode($encrypted_data), true);
            
            if (!$payload) {
                throw new Exception('Invalid encrypted data format');
            }
            
            // Verify signature
            if (!$this->verify_payload_signature($payload)) {
                throw new Exception('Signature verification failed');
            }
            
            // Verify context if provided
            if ($expected_context && $payload['context'] !== $expected_context) {
                throw new Exception('Context mismatch');
            }
            
            // Get decryption key based on version
            $key = $this->get_encryption_key($payload['context'], $payload['key_version'] ?? null);
            
            // Decode components
            $iv = base64_decode($payload['iv']);
            $tag = base64_decode($payload['tag']);
            $encrypted = base64_decode($payload['data']);
            
            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted,
                $payload['method'],
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $payload['context']
            );
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            // Decompress if needed
            if ($payload['compressed'] ?? false) {
                $decrypted = gzuncompress($decrypted);
            }
            
            // Unserialize if needed
            $unserialized = @unserialize($decrypted);
            if ($unserialized !== false) {
                $decrypted = $unserialized;
            }
            
            // Log decryption event
            if ($this->config['audit_decryption']) {
                $this->log_encryption_event('decrypt', $payload['context'], strlen($encrypted_data), strlen($decrypted));
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            
            // Fail open or closed based on config
            if ($this->config['fail_closed'] ?? true) {
                throw $e;
            }
            
            return null;
        }
    }
    
    /**
     * Check if data is encrypted
     */
    public function is_encrypted($data) {
        if (!is_string($data)) {
            return false;
        }
        
        // Try to decode
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }
        
        // Try to parse JSON
        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return false;
        }
        
        // Check required fields
        $required = ['method', 'iv', 'tag', 'data', 'signature'];
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Encrypt specific fields in array
     */
    public function encrypt_fields($data, $fields_to_encrypt, $context = '') {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($fields_to_encrypt as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->encrypt($data[$field], $context . '.' . $field);
            }
        }
        
        return $data;
    }
    
    /**
     * Decrypt specific fields in array
     */
    public function decrypt_fields($data, $fields_to_decrypt, $context = '') {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($fields_to_decrypt as $field) {
            if (isset($data[$field]) && $this->is_encrypted($data[$field])) {
                $data[$field] = $this->decrypt($data[$field], $context . '.' . $field);
            }
        }
        
        return $data;
    }
    
    /**
     * Get master key
     */
    private function get_master_key() {
        // Try environment variable first
        if (defined('MONEY_QUIZ_MASTER_KEY')) {
            return base64_decode(MONEY_QUIZ_MASTER_KEY);
        }
        
        // Try wp-config constant
        if (defined('MONEY_QUIZ_ENCRYPTION_KEY')) {
            return base64_decode(MONEY_QUIZ_ENCRYPTION_KEY);
        }
        
        // Fall back to database (less secure)
        $key = get_option('money_quiz_master_key');
        if ($key) {
            return base64_decode($key);
        }
        
        return false;
    }
    
    /**
     * Generate master key
     */
    private function generate_master_key() {
        $key = openssl_random_pseudo_bytes(32);
        $encoded = base64_encode($key);
        
        // Store in database (should be moved to wp-config.php)
        update_option('money_quiz_master_key', $encoded);
        
        // Log key generation
        $this->log_encryption_event('master_key_generated', 'system');
        
        // Show admin notice
        add_action('admin_notices', function() use ($encoded) {
            ?>
            <div class="notice notice-warning">
                <p><strong>Money Quiz Security:</strong> A master encryption key has been generated. 
                For better security, please add this to your wp-config.php file:</p>
                <code>define('MONEY_QUIZ_MASTER_KEY', '<?php echo esc_html($encoded); ?>');</code>
            </div>
            <?php
        });
        
        return $key;
    }
    
    /**
     * Get encryption key
     */
    private function get_encryption_key($context = '', $version = null) {
        $master_key = $this->get_master_key();
        
        if (!$master_key) {
            throw new Exception('Master key not available');
        }
        
        // Get salt for key derivation
        $salt = $this->get_key_salt($version);
        
        // Derive key using PBKDF2
        $key = hash_pbkdf2(
            'sha256',
            $master_key,
            $salt . $context,
            $this->key_derivation_iterations,
            32,
            true
        );
        
        return $key;
    }
    
    /**
     * Get key salt
     */
    private function get_key_salt($version = null) {
        if ($version === null) {
            $version = $this->get_current_key_version();
        }
        
        $salt = get_option('money_quiz_key_salt_v' . $version);
        
        if (!$salt) {
            $salt = openssl_random_pseudo_bytes(16);
            update_option('money_quiz_key_salt_v' . $version, base64_encode($salt));
        } else {
            $salt = base64_decode($salt);
        }
        
        return $salt;
    }
    
    /**
     * Get current key version
     */
    private function get_current_key_version() {
        return get_option('money_quiz_key_version', 1);
    }
    
    /**
     * Sign payload
     */
    private function sign_payload($payload) {
        $data = json_encode([
            'method' => $payload['method'],
            'iv' => $payload['iv'],
            'tag' => $payload['tag'],
            'data' => $payload['data'],
            'context' => $payload['context'],
            'timestamp' => $payload['timestamp']
        ]);
        
        return hash_hmac('sha256', $data, $this->get_master_key());
    }
    
    /**
     * Verify payload signature
     */
    private function verify_payload_signature($payload) {
        $expected = $this->sign_payload($payload);
        return hash_equals($expected, $payload['signature']);
    }
    
    /**
     * Rotate encryption keys
     */
    public function rotate_encryption_keys() {
        // Check if rotation is due
        $last_rotation = get_option('money_quiz_last_key_rotation', 0);
        $days_since_rotation = (time() - $last_rotation) / (60 * 60 * 24);
        
        if ($days_since_rotation < $this->config['key_rotation_days']) {
            return;
        }
        
        // Start rotation process
        $this->log_encryption_event('key_rotation_started', 'system');
        
        try {
            // Increment key version
            $old_version = $this->get_current_key_version();
            $new_version = $old_version + 1;
            update_option('money_quiz_key_version', $new_version);
            
            // Generate new salt
            $this->get_key_salt($new_version);
            
            // Re-encrypt critical data
            $this->reencrypt_data($old_version, $new_version);
            
            // Update last rotation time
            update_option('money_quiz_last_key_rotation', time());
            
            // Log completion
            $this->log_encryption_event('key_rotation_completed', 'system', [
                'old_version' => $old_version,
                'new_version' => $new_version
            ]);
            
            // Notify admin
            $this->notify_key_rotation($new_version);
            
        } catch (Exception $e) {
            error_log('Key rotation failed: ' . $e->getMessage());
            $this->log_encryption_event('key_rotation_failed', 'system', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Re-encrypt data with new key
     */
    private function reencrypt_data($old_version, $new_version) {
        // Re-encrypt sensitive options
        $sensitive_options = [
            'money_quiz_api_keys',
            'money_quiz_payment_settings',
            'money_quiz_smtp_password'
        ];
        
        foreach ($sensitive_options as $option) {
            $value = get_option($option);
            if ($value && $this->is_encrypted($value)) {
                $decrypted = $this->decrypt($value);
                $reencrypted = $this->encrypt($decrypted, 'option.' . $option);
                update_option($option, $reencrypted);
            }
        }
        
        // Re-encrypt user meta
        $users = get_users(['fields' => 'ids']);
        foreach ($users as $user_id) {
            $this->reencrypt_user_data($user_id, $old_version, $new_version);
        }
        
        // Re-encrypt database records
        $this->reencrypt_database_records($old_version, $new_version);
    }
    
    /**
     * Handle database encryption
     */
    public function handle_database_encryption($query) {
        if (!$this->config['transparent_encryption']) {
            return $query;
        }
        
        // Parse query to detect operations on encrypted fields
        foreach ($this->encrypted_fields as $table => $fields) {
            $table_name = $this->db->prefix . 'money_quiz_' . $table;
            
            // Handle INSERT/UPDATE
            if (preg_match("/INSERT INTO `?$table_name`?|UPDATE `?$table_name`? SET/i", $query)) {
                $query = $this->encrypt_query_values($query, $table, $fields);
            }
            
            // Handle SELECT (prepare for decryption)
            if (preg_match("/SELECT .* FROM `?$table_name`?/i", $query)) {
                // Mark query for post-processing
                add_filter('query_results', function($results) use ($table, $fields) {
                    return $this->decrypt_query_results($results, $table, $fields);
                }, 10, 1);
            }
        }
        
        return $query;
    }
    
    /**
     * Encrypt query values
     */
    private function encrypt_query_values($query, $table, $fields) {
        foreach ($fields as $field) {
            // Simple regex to find field = 'value' patterns
            $pattern = "/($field\s*=\s*)'([^']+)'/i";
            $query = preg_replace_callback($pattern, function($matches) use ($table, $field) {
                $encrypted = $this->encrypt($matches[2], $table . '.' . $field);
                return $matches[1] . "'" . esc_sql($encrypted) . "'";
            }, $query);
        }
        
        return $query;
    }
    
    /**
     * Decrypt query results
     */
    private function decrypt_query_results($results, $table, $fields) {
        if (!is_array($results)) {
            return $results;
        }
        
        foreach ($results as &$row) {
            if (is_object($row)) {
                foreach ($fields as $field) {
                    if (isset($row->$field) && $this->is_encrypted($row->$field)) {
                        $row->$field = $this->decrypt($row->$field, $table . '.' . $field);
                    }
                }
            } elseif (is_array($row)) {
                foreach ($fields as $field) {
                    if (isset($row[$field]) && $this->is_encrypted($row[$field])) {
                        $row[$field] = $this->decrypt($row[$field], $table . '.' . $field);
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Encrypt option
     */
    public function encrypt_option($value, $option, $old_value) {
        $sensitive_options = apply_filters('money_quiz_sensitive_options', [
            'money_quiz_api_keys',
            'money_quiz_payment_settings',
            'money_quiz_smtp_password'
        ]);
        
        if (in_array($option, $sensitive_options)) {
            return $this->encrypt($value, 'option.' . $option);
        }
        
        return $value;
    }
    
    /**
     * Decrypt option
     */
    public function decrypt_option($value, $option) {
        if ($this->is_encrypted($value)) {
            return $this->decrypt($value, 'option.' . $option);
        }
        
        return $value;
    }
    
    /**
     * Encrypt user meta
     */
    public function encrypt_user_meta($value, $object_id, $meta_key) {
        $sensitive_meta = apply_filters('money_quiz_sensitive_user_meta', [
            '_money_quiz_api_key',
            '_money_quiz_phone',
            '_money_quiz_address',
            '_money_quiz_payment_method'
        ]);
        
        if (in_array($meta_key, $sensitive_meta)) {
            return $this->encrypt($value, 'user_meta.' . $meta_key);
        }
        
        return $value;
    }
    
    /**
     * Decrypt user meta
     */
    public function decrypt_user_meta($value, $object_id, $meta_key, $single) {
        if ($single && $this->is_encrypted($value)) {
            return $this->decrypt($value, 'user_meta.' . $meta_key);
        }
        
        return $value;
    }
    
    /**
     * Log encryption event
     */
    private function log_encryption_event($action, $context, $data = []) {
        $log_entry = [
            'action' => $action,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'data' => json_encode($data)
        ];
        
        $this->db->insert(
            $this->db->prefix . 'money_quiz_encryption_logs',
            $log_entry
        );
    }
    
    /**
     * Notify admin of key rotation
     */
    private function notify_key_rotation($new_version) {
        $admin_email = get_option('admin_email');
        $subject = '[Money Quiz] Encryption Key Rotation Completed';
        $message = sprintf(
            "The encryption keys for Money Quiz have been successfully rotated.\n\n" .
            "New key version: %d\n" .
            "Rotation time: %s\n\n" .
            "This is an automated security measure. No action is required.",
            $new_version,
            current_time('mysql')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Reencrypt user data
     */
    private function reencrypt_user_data($user_id, $old_version, $new_version) {
        $sensitive_meta = apply_filters('money_quiz_sensitive_user_meta', [
            '_money_quiz_api_key',
            '_money_quiz_phone',
            '_money_quiz_address',
            '_money_quiz_payment_method'
        ]);
        
        foreach ($sensitive_meta as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            if ($value && $this->is_encrypted($value)) {
                // Decrypt with old key
                $decrypted = $this->decrypt($value);
                
                // Re-encrypt with new key
                $reencrypted = $this->encrypt($decrypted, 'user_meta.' . $meta_key);
                
                // Update meta
                update_user_meta($user_id, $meta_key, $reencrypted);
            }
        }
    }
    
    /**
     * Re-encrypt database records
     */
    private function reencrypt_database_records($old_version, $new_version) {
        foreach ($this->encrypted_fields as $table => $fields) {
            $table_name = $this->db->prefix . 'money_quiz_' . $table;
            
            // Check if table exists
            if ($this->db->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                continue;
            }
            
            // Get all records
            $records = $this->db->get_results("SELECT * FROM $table_name", ARRAY_A);
            
            foreach ($records as $record) {
                $updates = [];
                
                foreach ($fields as $field) {
                    if (isset($record[$field]) && $this->is_encrypted($record[$field])) {
                        // Decrypt with old key version
                        $decrypted = $this->decrypt($record[$field]);
                        
                        // Re-encrypt with new key version
                        $reencrypted = $this->encrypt($decrypted, $table . '.' . $field);
                        
                        $updates[$field] = $reencrypted;
                    }
                }
                
                if (!empty($updates)) {
                    $this->db->update(
                        $table_name,
                        $updates,
                        ['id' => $record['id']]
                    );
                }
            }
        }
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_encryption_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            context varchar(100),
            user_id bigint(20),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            data text,
            PRIMARY KEY (id),
            KEY action (action),
            KEY context (context),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize on plugin activation
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Encryption\DataEncryption', 'create_tables']);