<?php
/**
 * Secure Key Management System
 * 
 * Handles encryption key storage, rotation, and lifecycle
 * 
 * @package MoneyQuiz\Security\Encryption
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Encryption;

use Exception;

class KeyManagement {
    private $db;
    private $config;
    private $key_store;
    private $hsm_client = null;
    
    // Key types
    const KEY_TYPE_MASTER = 'master';
    const KEY_TYPE_DATA = 'data';
    const KEY_TYPE_SIGNING = 'signing';
    const KEY_TYPE_API = 'api';
    const KEY_TYPE_SESSION = 'session';
    
    // Key states
    const KEY_STATE_ACTIVE = 'active';
    const KEY_STATE_ROTATING = 'rotating';
    const KEY_STATE_DEPRECATED = 'deprecated';
    const KEY_STATE_REVOKED = 'revoked';
    
    public function __construct($config = []) {
        $this->db = $GLOBALS['wpdb'];
        
        $this->config = wp_parse_args($config, [
            'key_rotation_days' => 90,
            'key_derivation_iterations' => 100000,
            'key_storage' => 'database', // database, file, hsm, vault
            'hsm_enabled' => false,
            'vault_enabled' => false,
            'key_escrow' => false,
            'split_knowledge' => false,
            'secure_deletion' => true,
            'audit_access' => true
        ]);
        
        $this->init_key_store();
        $this->init_hooks();
        $this->verify_key_infrastructure();
    }
    
    /**
     * Initialize key store
     */
    private function init_key_store() {
        switch ($this->config['key_storage']) {
            case 'file':
                $this->key_store = new FileKeyStore();
                break;
            case 'hsm':
                $this->key_store = new HSMKeyStore($this->config['hsm_config'] ?? []);
                break;
            case 'vault':
                $this->key_store = new VaultKeyStore($this->config['vault_config'] ?? []);
                break;
            default:
                $this->key_store = new DatabaseKeyStore();
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Key rotation
        add_action('money_quiz_rotate_keys', [$this, 'rotate_keys']);
        add_action('money_quiz_check_key_expiry', [$this, 'check_key_expiry']);
        
        // Schedule tasks
        if (!wp_next_scheduled('money_quiz_rotate_keys')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_rotate_keys');
        }
        
        if (!wp_next_scheduled('money_quiz_check_key_expiry')) {
            wp_schedule_event(time(), 'hourly', 'money_quiz_check_key_expiry');
        }
        
        // Admin interface
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_money_quiz_rotate_key', [$this, 'ajax_rotate_key']);
        add_action('wp_ajax_money_quiz_generate_key', [$this, 'ajax_generate_key']);
    }
    
    /**
     * Verify key infrastructure
     */
    private function verify_key_infrastructure() {
        // Check master key
        if (!$this->get_master_key()) {
            $this->initialize_master_key();
        }
        
        // Verify key store connectivity
        if (!$this->key_store->test_connection()) {
            throw new Exception('Key store connection failed');
        }
        
        // Check for expired keys
        $this->check_key_expiry();
    }
    
    /**
     * Generate new key
     */
    public function generate_key($type = self::KEY_TYPE_DATA, $options = []) {
        $options = wp_parse_args($options, [
            'length' => 32,
            'algorithm' => 'AES-256',
            'purpose' => '',
            'expires_in' => $this->config['key_rotation_days'] * DAY_IN_SECONDS
        ]);
        
        // Generate random key
        $key_material = openssl_random_pseudo_bytes($options['length']);
        
        // Derive key using HKDF
        $salt = openssl_random_pseudo_bytes(16);
        $info = json_encode([
            'type' => $type,
            'purpose' => $options['purpose'],
            'algorithm' => $options['algorithm'],
            'timestamp' => time()
        ]);
        
        $derived_key = hash_hkdf('sha256', $key_material, $options['length'], $info, $salt);
        
        // Generate key ID
        $key_id = $this->generate_key_id($type);
        
        // Prepare metadata
        $metadata = [
            'id' => $key_id,
            'type' => $type,
            'algorithm' => $options['algorithm'],
            'length' => $options['length'],
            'purpose' => $options['purpose'],
            'created_at' => time(),
            'expires_at' => time() + $options['expires_in'],
            'state' => self::KEY_STATE_ACTIVE,
            'version' => 1,
            'fingerprint' => $this->calculate_key_fingerprint($derived_key),
            'usage_count' => 0,
            'last_used' => null
        ];
        
        // Encrypt key for storage
        $encrypted_key = $this->encrypt_key_for_storage($derived_key, $metadata);
        
        // Store key
        $stored = $this->key_store->store_key($key_id, $encrypted_key, $metadata);
        
        if (!$stored) {
            throw new Exception('Failed to store key');
        }
        
        // Log key generation
        $this->log_key_operation('generate', $key_id, $type);
        
        // Return key info (not the actual key)
        return [
            'id' => $key_id,
            'type' => $type,
            'fingerprint' => $metadata['fingerprint'],
            'created_at' => $metadata['created_at'],
            'expires_at' => $metadata['expires_at']
        ];
    }
    
    /**
     * Retrieve key
     */
    public function get_key($key_id, $purpose = '') {
        // Check cache first
        $cache_key = 'key_' . $key_id;
        $cached = wp_cache_get($cache_key, 'money_quiz_keys');
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Retrieve from store
        $encrypted_key = $this->key_store->retrieve_key($key_id);
        
        if (!$encrypted_key) {
            throw new Exception('Key not found: ' . $key_id);
        }
        
        // Get metadata
        $metadata = $this->key_store->get_key_metadata($key_id);
        
        // Check key state
        if ($metadata['state'] === self::KEY_STATE_REVOKED) {
            throw new Exception('Key has been revoked: ' . $key_id);
        }
        
        // Check expiry
        if ($metadata['expires_at'] < time()) {
            throw new Exception('Key has expired: ' . $key_id);
        }
        
        // Decrypt key
        $key = $this->decrypt_key_from_storage($encrypted_key, $metadata);
        
        // Verify fingerprint
        if ($this->calculate_key_fingerprint($key) !== $metadata['fingerprint']) {
            throw new Exception('Key integrity check failed');
        }
        
        // Update usage stats
        $this->key_store->update_key_usage($key_id);
        
        // Log access
        if ($this->config['audit_access']) {
            $this->log_key_operation('access', $key_id, $metadata['type'], ['purpose' => $purpose]);
        }
        
        // Cache for performance
        wp_cache_set($cache_key, $key, 'money_quiz_keys', 300);
        
        return $key;
    }
    
    /**
     * Rotate key
     */
    public function rotate_key($key_id, $options = []) {
        $options = wp_parse_args($options, [
            'immediate' => false,
            'overlap_period' => DAY_IN_SECONDS * 7, // 1 week overlap
            'reencrypt_data' => true
        ]);
        
        // Get current key metadata
        $current_metadata = $this->key_store->get_key_metadata($key_id);
        
        if (!$current_metadata) {
            throw new Exception('Key not found for rotation: ' . $key_id);
        }
        
        // Set current key to rotating state
        $this->key_store->update_key_state($key_id, self::KEY_STATE_ROTATING);
        
        // Generate new key
        $new_key_info = $this->generate_key($current_metadata['type'], [
            'length' => $current_metadata['length'],
            'algorithm' => $current_metadata['algorithm'],
            'purpose' => $current_metadata['purpose']
        ]);
        
        // Link old and new keys
        $this->key_store->link_keys($key_id, $new_key_info['id']);
        
        // Schedule re-encryption if needed
        if ($options['reencrypt_data']) {
            $this->schedule_reencryption($key_id, $new_key_info['id']);
        }
        
        // Handle transition
        if ($options['immediate']) {
            // Immediate rotation
            $this->key_store->update_key_state($key_id, self::KEY_STATE_DEPRECATED);
            $this->complete_key_rotation($key_id, $new_key_info['id']);
        } else {
            // Gradual rotation with overlap
            wp_schedule_single_event(
                time() + $options['overlap_period'],
                'money_quiz_complete_key_rotation',
                [$key_id, $new_key_info['id']]
            );
        }
        
        // Log rotation
        $this->log_key_operation('rotate', $key_id, $current_metadata['type'], [
            'new_key_id' => $new_key_info['id'],
            'immediate' => $options['immediate']
        ]);
        
        // Notify administrators
        $this->notify_key_rotation($key_id, $new_key_info['id']);
        
        return $new_key_info;
    }
    
    /**
     * Revoke key
     */
    public function revoke_key($key_id, $reason = '') {
        // Update state
        $this->key_store->update_key_state($key_id, self::KEY_STATE_REVOKED);
        
        // Clear from cache
        wp_cache_delete('key_' . $key_id, 'money_quiz_keys');
        
        // Log revocation
        $this->log_key_operation('revoke', $key_id, '', ['reason' => $reason]);
        
        // Notify administrators
        $this->notify_key_revocation($key_id, $reason);
        
        // Trigger re-encryption if this was an active key
        $metadata = $this->key_store->get_key_metadata($key_id);
        if ($metadata['state'] === self::KEY_STATE_ACTIVE) {
            $this->trigger_emergency_reencryption($key_id);
        }
    }
    
    /**
     * Initialize master key
     */
    private function initialize_master_key() {
        // Generate master key
        $master_key = openssl_random_pseudo_bytes(32);
        
        // Split key if enabled
        if ($this->config['split_knowledge']) {
            $shares = $this->split_key($master_key, 3, 2); // 3 shares, 2 required
            $this->distribute_key_shares($shares);
        }
        
        // Store master key
        $this->store_master_key($master_key);
        
        // Generate KEK (Key Encryption Key)
        $kek = hash_hkdf('sha256', $master_key, 32, 'KEK', 'money-quiz');
        $this->store_kek($kek);
        
        // Log initialization
        $this->log_key_operation('initialize_master', 'master', self::KEY_TYPE_MASTER);
    }
    
    /**
     * Get master key
     */
    private function get_master_key() {
        // Try environment variable
        if (defined('MONEY_QUIZ_MASTER_KEY')) {
            return base64_decode(MONEY_QUIZ_MASTER_KEY);
        }
        
        // Try key store
        return $this->key_store->retrieve_master_key();
    }
    
    /**
     * Store master key
     */
    private function store_master_key($key) {
        // Prefer environment variable
        if (defined('WP_CONFIG_PATH')) {
            $this->add_to_wp_config('MONEY_QUIZ_MASTER_KEY', base64_encode($key));
        } else {
            // Fallback to secure storage
            $this->key_store->store_master_key($key);
        }
    }
    
    /**
     * Encrypt key for storage
     */
    private function encrypt_key_for_storage($key, $metadata) {
        $kek = $this->get_kek();
        
        // Generate IV
        $iv = openssl_random_pseudo_bytes(16);
        
        // Encrypt key
        $encrypted = openssl_encrypt(
            $key,
            'AES-256-GCM',
            $kek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            json_encode($metadata)
        );
        
        return base64_encode(json_encode([
            'encrypted' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ]));
    }
    
    /**
     * Decrypt key from storage
     */
    private function decrypt_key_from_storage($encrypted_data, $metadata) {
        $kek = $this->get_kek();
        
        $data = json_decode(base64_decode($encrypted_data), true);
        
        $decrypted = openssl_decrypt(
            base64_decode($data['encrypted']),
            'AES-256-GCM',
            $kek,
            OPENSSL_RAW_DATA,
            base64_decode($data['iv']),
            base64_decode($data['tag']),
            json_encode($metadata)
        );
        
        if ($decrypted === false) {
            throw new Exception('Key decryption failed');
        }
        
        return $decrypted;
    }
    
    /**
     * Get KEK (Key Encryption Key)
     */
    private function get_kek() {
        $cache_key = 'kek';
        $cached = wp_cache_get($cache_key, 'money_quiz_keys');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $master_key = $this->get_master_key();
        $kek = hash_hkdf('sha256', $master_key, 32, 'KEK', 'money-quiz');
        
        wp_cache_set($cache_key, $kek, 'money_quiz_keys', 300);
        
        return $kek;
    }
    
    /**
     * Generate key ID
     */
    private function generate_key_id($type) {
        return sprintf(
            '%s_%s_%s',
            $type,
            date('Ymd'),
            bin2hex(random_bytes(8))
        );
    }
    
    /**
     * Calculate key fingerprint
     */
    private function calculate_key_fingerprint($key) {
        return hash('sha256', $key);
    }
    
    /**
     * Split key using Shamir's Secret Sharing
     */
    private function split_key($key, $total_shares, $threshold) {
        // This is a simplified implementation
        // In production, use a proper secret sharing library
        $shares = [];
        
        for ($i = 0; $i < $total_shares; $i++) {
            $share = [
                'index' => $i + 1,
                'data' => base64_encode($key), // Simplified - should use proper SSS
                'threshold' => $threshold,
                'total' => $total_shares
            ];
            $shares[] = $share;
        }
        
        return $shares;
    }
    
    /**
     * Distribute key shares
     */
    private function distribute_key_shares($shares) {
        // Send shares to different administrators
        $admins = get_users(['role' => 'administrator']);
        
        foreach ($shares as $index => $share) {
            if (isset($admins[$index])) {
                $this->send_key_share($admins[$index], $share);
            }
        }
    }
    
    /**
     * Schedule re-encryption
     */
    private function schedule_reencryption($old_key_id, $new_key_id) {
        // Create batch job
        $batch_id = wp_generate_password(12, false);
        
        $this->db->insert(
            $this->db->prefix . 'money_quiz_reencryption_jobs',
            [
                'batch_id' => $batch_id,
                'old_key_id' => $old_key_id,
                'new_key_id' => $new_key_id,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]
        );
        
        // Schedule processing
        wp_schedule_single_event(
            time() + 60,
            'money_quiz_process_reencryption',
            [$batch_id]
        );
    }
    
    /**
     * Check key expiry
     */
    public function check_key_expiry() {
        $keys = $this->key_store->get_expiring_keys(DAY_IN_SECONDS * 30); // 30 days
        
        foreach ($keys as $key) {
            $days_until_expiry = ($key['expires_at'] - time()) / DAY_IN_SECONDS;
            
            if ($days_until_expiry <= 7) {
                // Urgent: rotate immediately
                $this->rotate_key($key['id'], ['immediate' => true]);
            } elseif ($days_until_expiry <= 30) {
                // Warning: schedule rotation
                wp_schedule_single_event(
                    $key['expires_at'] - (DAY_IN_SECONDS * 7),
                    'money_quiz_rotate_key',
                    [$key['id']]
                );
                
                // Notify administrators
                $this->notify_key_expiry_warning($key['id'], $days_until_expiry);
            }
        }
    }
    
    /**
     * Complete key rotation
     */
    private function complete_key_rotation($old_key_id, $new_key_id) {
        // Mark old key as deprecated
        $this->key_store->update_key_state($old_key_id, self::KEY_STATE_DEPRECATED);
        
        // Update all references
        $this->update_key_references($old_key_id, $new_key_id);
        
        // Schedule old key deletion
        wp_schedule_single_event(
            time() + (DAY_IN_SECONDS * 90), // Keep for 90 days
            'money_quiz_delete_deprecated_key',
            [$old_key_id]
        );
        
        // Log completion
        $this->log_key_operation('rotation_complete', $old_key_id, '', [
            'new_key_id' => $new_key_id
        ]);
    }
    
    /**
     * Trigger emergency re-encryption
     */
    private function trigger_emergency_reencryption($revoked_key_id) {
        // Get all data encrypted with this key
        $affected_data = $this->find_data_encrypted_with_key($revoked_key_id);
        
        // Generate emergency key
        $emergency_key = $this->generate_key(self::KEY_TYPE_DATA, [
            'purpose' => 'emergency_replacement',
            'expires_in' => DAY_IN_SECONDS * 7 // Short-lived
        ]);
        
        // Start immediate re-encryption
        $this->emergency_reencrypt_data($affected_data, $revoked_key_id, $emergency_key['id']);
        
        // Notify administrators
        $this->notify_emergency_reencryption($revoked_key_id, count($affected_data));
    }
    
    /**
     * Log key operation
     */
    private function log_key_operation($operation, $key_id, $key_type = '', $additional_data = []) {
        $log_entry = array_merge([
            'operation' => $operation,
            'key_id' => $key_id,
            'key_type' => $key_type,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ], $additional_data);
        
        $this->db->insert(
            $this->db->prefix . 'money_quiz_key_logs',
            [
                'operation' => $operation,
                'key_id' => $key_id,
                'key_type' => $key_type,
                'user_id' => get_current_user_id(),
                'data' => json_encode($additional_data),
                'timestamp' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        );
    }
    
    /**
     * Notify key rotation
     */
    private function notify_key_rotation($old_key_id, $new_key_id) {
        $admins = get_users(['role' => 'administrator']);
        
        foreach ($admins as $admin) {
            wp_mail(
                $admin->user_email,
                '[Money Quiz] Key Rotation Notification',
                sprintf(
                    "A key rotation has been initiated:\n\n" .
                    "Old Key ID: %s\n" .
                    "New Key ID: %s\n" .
                    "Time: %s\n\n" .
                    "This is an automated security process.",
                    $old_key_id,
                    $new_key_id,
                    current_time('mysql')
                )
            );
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'money-quiz',
            'Key Management',
            'Key Management',
            'manage_options',
            'money-quiz-keys',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $keys = $this->key_store->list_keys();
        
        include __DIR__ . '/views/key-management.php';
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Key logs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_key_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation varchar(50) NOT NULL,
            key_id varchar(100),
            key_type varchar(50),
            user_id bigint(20),
            data text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY operation (operation),
            KEY key_id (key_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Re-encryption jobs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_reencryption_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            batch_id varchar(50) NOT NULL,
            old_key_id varchar(100) NOT NULL,
            new_key_id varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            progress int DEFAULT 0,
            total_records int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime,
            completed_at datetime,
            error_message text,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}

/**
 * Database Key Store Implementation
 */
class DatabaseKeyStore {
    private $db;
    private $table;
    
    public function __construct() {
        $this->db = $GLOBALS['wpdb'];
        $this->table = $this->db->prefix . 'money_quiz_keys';
    }
    
    public function test_connection() {
        return $this->db->get_var("SELECT 1") == 1;
    }
    
    public function store_key($key_id, $encrypted_key, $metadata) {
        return $this->db->insert(
            $this->table,
            [
                'key_id' => $key_id,
                'encrypted_key' => $encrypted_key,
                'metadata' => json_encode($metadata),
                'created_at' => current_time('mysql'),
                'state' => $metadata['state']
            ]
        );
    }
    
    public function retrieve_key($key_id) {
        return $this->db->get_var($this->db->prepare(
            "SELECT encrypted_key FROM {$this->table} WHERE key_id = %s",
            $key_id
        ));
    }
    
    public function get_key_metadata($key_id) {
        $metadata = $this->db->get_var($this->db->prepare(
            "SELECT metadata FROM {$this->table} WHERE key_id = %s",
            $key_id
        ));
        
        return $metadata ? json_decode($metadata, true) : null;
    }
    
    public function update_key_state($key_id, $state) {
        return $this->db->update(
            $this->table,
            ['state' => $state],
            ['key_id' => $key_id]
        );
    }
    
    public function update_key_usage($key_id) {
        $this->db->query($this->db->prepare(
            "UPDATE {$this->table} 
            SET usage_count = usage_count + 1, 
                last_used = NOW() 
            WHERE key_id = %s",
            $key_id
        ));
    }
    
    public function get_expiring_keys($window) {
        $expiry_time = time() + $window;
        
        return $this->db->get_results($this->db->prepare(
            "SELECT key_id, metadata 
            FROM {$this->table} 
            WHERE state = %s 
            AND JSON_EXTRACT(metadata, '$.expires_at') < %d",
            KeyManagement::KEY_STATE_ACTIVE,
            $expiry_time
        ), ARRAY_A);
    }
    
    public function list_keys() {
        return $this->db->get_results(
            "SELECT key_id, metadata, state, created_at, usage_count, last_used 
            FROM {$this->table} 
            ORDER BY created_at DESC",
            ARRAY_A
        );
    }
    
    public function link_keys($old_key_id, $new_key_id) {
        $this->db->insert(
            $this->db->prefix . 'money_quiz_key_links',
            [
                'old_key_id' => $old_key_id,
                'new_key_id' => $new_key_id,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    public function store_master_key($key) {
        // Use WordPress options with encryption
        $encrypted = base64_encode(openssl_encrypt(
            $key,
            'AES-256-CBC',
            hash('sha256', AUTH_KEY . AUTH_SALT),
            0,
            substr(hash('sha256', SECURE_AUTH_KEY), 0, 16)
        ));
        
        update_option('money_quiz_master_key_encrypted', $encrypted);
    }
    
    public function retrieve_master_key() {
        $encrypted = get_option('money_quiz_master_key_encrypted');
        
        if (!$encrypted) {
            return false;
        }
        
        return openssl_decrypt(
            base64_decode($encrypted),
            'AES-256-CBC',
            hash('sha256', AUTH_KEY . AUTH_SALT),
            0,
            substr(hash('sha256', SECURE_AUTH_KEY), 0, 16)
        );
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Keys table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_keys (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            key_id varchar(100) NOT NULL,
            encrypted_key text NOT NULL,
            metadata text NOT NULL,
            state varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            usage_count int DEFAULT 0,
            last_used datetime,
            PRIMARY KEY (id),
            UNIQUE KEY key_id (key_id),
            KEY state (state),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Key links table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_key_links (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            old_key_id varchar(100) NOT NULL,
            new_key_id varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY old_key_id (old_key_id),
            KEY new_key_id (new_key_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}

// Initialize on plugin activation
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Encryption\KeyManagement', 'create_tables']);
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Encryption\DatabaseKeyStore', 'create_tables']);