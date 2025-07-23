<?php
/**
 * Encryption Key Manager
 *
 * Manages encryption keys securely.
 *
 * @package MoneyQuiz\Security\Encryption
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Encryption;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Key manager class.
 *
 * @since 7.0.0
 */
class KeyManager {
    
    /**
     * Key storage option name.
     *
     * @var string
     */
    private const KEY_OPTION = 'money_quiz_encryption_keys';
    
    /**
     * Key rotation interval (90 days).
     *
     * @var int
     */
    private const ROTATION_INTERVAL = 90 * DAY_IN_SECONDS;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Cached keys.
     *
     * @var array
     */
    private array $keys = [];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->load_keys();
    }
    
    /**
     * Get encryption key for context.
     *
     * This method implements automatic key management:
     * 1. Generates a new key if none exists for the context
     * 2. Automatically rotates keys after 90 days for security
     * 3. Maintains previous keys for decrypting old data
     *
     * @since 7.0.0
     *
     * @param string $context Key context for different encryption purposes.
     *                       Examples: 'default', 'user_data', 'api_keys', 'sensitive'.
     *                       Different contexts use different keys for security isolation.
     * 
     * @return string Base64-encoded 256-bit encryption key suitable for AES-256.
     * 
     * @example
     * ```php
     * $key_manager = new KeyManager($logger);
     * $key = $key_manager->get_key('user_data');
     * // Use $key with Encryptor for encrypting user data
     * ```
     */
    public function get_key(string $context = 'default'): string {
        // Check if key exists
        if (!isset($this->keys[$context])) {
            $this->generate_key($context);
        }
        
        // Check if key needs rotation
        // Keys are rotated automatically after 90 days to limit exposure
        // Old keys are retained to decrypt data encrypted with them
        if ($this->needs_rotation($context)) {
            $this->rotate_key($context);
        }
        
        return $this->keys[$context]['key'];
    }
    
    /**
     * Generate new key for context.
     *
     * @param string $context Key context.
     */
    private function generate_key(string $context): void {
        // Generate secure random key
        $key = $this->generate_secure_key();
        
        $this->keys[$context] = [
            'key' => $key,
            'created' => time(),
            'rotated' => null,
            'version' => 1
        ];
        
        $this->save_keys();
        
        $this->logger->info('Encryption key generated', [
            'context' => $context,
            'version' => 1
        ]);
    }
    
    /**
     * Generate secure random key.
     *
     * Creates a cryptographically secure 256-bit key by:
     * 1. Generating 32 random bytes using OpenSSL
     * 2. Mixing with WordPress salts for additional entropy
     * 3. Hashing to ensure uniform distribution
     *
     * @since 7.0.0
     * @access private
     *
     * @return string Base64-encoded 256-bit key.
     */
    private function generate_secure_key(): string {
        // Use WordPress salts as additional entropy
        // This ensures keys are unique per WordPress installation
        $salt = wp_salt('secure_auth');
        
        // Generate cryptographically secure random bytes
        // 32 bytes = 256 bits for AES-256 compatibility
        $random = openssl_random_pseudo_bytes(32);
        
        // Combine with salt and hash for additional entropy mixing
        // SHA-256 ensures uniform distribution of key material
        $key_material = hash('sha256', $random . $salt, true);
        
        return base64_encode($key_material);
    }
    
    /**
     * Check if key needs rotation.
     *
     * @param string $context Key context.
     * @return bool True if needs rotation.
     */
    private function needs_rotation(string $context): bool {
        if (!isset($this->keys[$context])) {
            return false;
        }
        
        $key_data = $this->keys[$context];
        $created = $key_data['created'];
        
        return (time() - $created) > self::ROTATION_INTERVAL;
    }
    
    /**
     * Rotate encryption key.
     *
     * Key rotation is a critical security practice that:
     * 1. Limits the amount of data encrypted with any single key
     * 2. Reduces impact if a key is compromised
     * 3. Maintains backward compatibility by keeping old keys
     * 4. Schedules re-encryption of existing data
     *
     * @since 7.0.0
     * @access private
     *
     * @param string $context The key context being rotated.
     * 
     * @return void
     */
    private function rotate_key(string $context): void {
        // Store old key for decryption
        $old_key = $this->keys[$context];
        
        // Generate new key
        $new_key = $this->generate_secure_key();
        
        $this->keys[$context] = [
            'key' => $new_key,
            'created' => time(),
            'rotated' => time(),
            'version' => ($old_key['version'] ?? 0) + 1,
            'previous' => $old_key
        ];
        
        $this->save_keys();
        
        $this->logger->warning('Encryption key rotated', [
            'context' => $context,
            'version' => $this->keys[$context]['version']
        ]);
        
        // Schedule re-encryption of existing data
        // Delayed by 5 minutes to avoid blocking current request
        // Re-encryption ensures all data uses the latest key
        wp_schedule_single_event(time() + 300, 'money_quiz_reencrypt_data', [$context]);
    }
    
    /**
     * Load keys from storage.
     */
    private function load_keys(): void {
        $stored = get_option(self::KEY_OPTION, null);
        
        if ($stored) {
            // Decrypt keys using WordPress salt
            $decrypted = $this->decrypt_stored_keys($stored);
            if ($decrypted) {
                $this->keys = $decrypted;
            }
        }
        
        // Ensure default key exists
        if (!isset($this->keys['default'])) {
            $this->generate_key('default');
        }
    }
    
    /**
     * Save keys to storage.
     */
    private function save_keys(): void {
        // Encrypt keys before storage
        $encrypted = $this->encrypt_keys_for_storage($this->keys);
        
        update_option(self::KEY_OPTION, $encrypted, false);
    }
    
    /**
     * Encrypt keys for storage.
     *
     * Implements defense-in-depth by encrypting the encryption keys themselves
     * before storing in the database. Uses WordPress salts as the master key,
     * ensuring keys are tied to the specific WordPress installation.
     *
     * @since 7.0.0
     * @access private
     *
     * @param array $keys Array of encryption keys to protect.
     * 
     * @return string Base64-encoded encrypted keys with IV prepended.
     *                Format: base64([16-byte IV][encrypted JSON])
     */
    private function encrypt_keys_for_storage(array $keys): string {
        // Derive master key from WordPress salts
        // Using two different salts provides 512 bits of entropy
        // Truncate to 32 bytes (256 bits) for AES-256
        $key = substr(hash('sha256', wp_salt('auth') . wp_salt('secure_auth')), 0, 32);
        
        // Generate random IV for each encryption operation
        // 16 bytes for AES-256-CBC block size
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(
            json_encode($keys),
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Prepend IV to ciphertext for use during decryption
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt stored keys.
     *
     * @param string $encrypted Encrypted keys.
     * @return array|null Decrypted keys.
     */
    private function decrypt_stored_keys(string $encrypted): ?array {
        $data = base64_decode($encrypted);
        if ($data === false) {
            return null;
        }
        
        $key = substr(hash('sha256', wp_salt('auth') . wp_salt('secure_auth')), 0, 32);
        
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            return null;
        }
        
        return json_decode($decrypted, true);
    }
    
    /**
     * Get key for decrypting old data.
     *
     * Retrieves historical keys by version number to decrypt data that was
     * encrypted with previous keys. This enables seamless key rotation
     * without losing access to existing encrypted data.
     *
     * @since 7.0.0
     *
     * @param string $context The key context to search.
     * @param int    $version The specific key version needed.
     * 
     * @return string|null The requested encryption key, or null if the version
     *                     doesn't exist or has been purged.
     * 
     * @example
     * ```php
     * // Decrypt data that was encrypted with version 2 key
     * $old_key = $key_manager->get_key_by_version('user_data', 2);
     * if ($old_key) {
     *     $decrypted = decrypt_with_key($encrypted_data, $old_key);
     * }
     * ```
     */
    public function get_key_by_version(string $context, int $version): ?string {
        if (!isset($this->keys[$context])) {
            return null;
        }
        
        $key_data = $this->keys[$context];
        
        // Current version
        if ($key_data['version'] === $version) {
            return $key_data['key'];
        }
        
        // Check previous versions
        // Walk the chain of previous keys to find the requested version
        // This maintains a complete history for decryption purposes
        $previous = $key_data['previous'] ?? null;
        while ($previous) {
            if ($previous['version'] === $version) {
                return $previous['key'];
            }
            $previous = $previous['previous'] ?? null;
        }
        
        return null;
    }
}