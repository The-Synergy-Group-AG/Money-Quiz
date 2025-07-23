<?php
/**
 * Data Encryptor
 *
 * Handles encryption and decryption of sensitive data.
 *
 * @package MoneyQuiz\Security\Encryption
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Encryption;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Contracts\EncryptionInterface;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Encryptor class.
 *
 * @since 7.0.0
 */
class Encryptor implements EncryptionInterface {
    
    /**
     * Encryption method.
     *
     * @var string
     */
    private const CIPHER_METHOD = 'AES-256-GCM';
    
    /**
     * Tag length for GCM.
     *
     * @var int
     */
    private const TAG_LENGTH = 16;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Key manager.
     *
     * @var KeyManager
     */
    private KeyManager $key_manager;
    
    /**
     * Constructor.
     *
     * @param Logger     $logger      Logger instance.
     * @param KeyManager $key_manager Key manager.
     */
    public function __construct(Logger $logger, KeyManager $key_manager) {
        $this->logger = $logger;
        $this->key_manager = $key_manager;
    }
    
    /**
     * Encrypt data using AES-256-GCM.
     *
     * This method provides authenticated encryption using AES-256 in GCM mode, which provides
     * both confidentiality and authenticity. The encrypted data includes:
     * - Initialization Vector (IV): Random data for encryption uniqueness
     * - Authentication Tag: Ensures data hasn't been tampered with
     * - Ciphertext: The encrypted data
     * 
     * The result is versioned (v1:) to support future encryption algorithm changes.
     *
     * @since 7.0.0
     *
     * @param string $data    The plaintext data to encrypt. Can be any string including JSON.
     * @param string $context The encryption context (e.g., 'user_data', 'api_keys'). 
     *                        Used for key selection and as additional authenticated data.
     * 
     * @return string Base64-encoded encrypted data with version prefix (e.g., "v1:abc123...").
     * 
     * @throws \Exception If encryption fails due to invalid key, insufficient entropy, or OpenSSL error.
     *
     * @example
     * ```php
     * $encryptor = new Encryptor($logger, $keyManager);
     * $encrypted = $encryptor->encrypt('sensitive data', 'user_data');
     * // Returns: "v1:bGtqaGZkc2FmZHNhZmRzYWZkc2FmZHNh..."
     * ```
     */
    public function encrypt(string $data, string $context = 'default'): string {
        try {
            // Retrieve the encryption key for the specified context
            // The key manager handles key rotation and versioning
            $key = $this->key_manager->get_key($context);
            
            // Generate a cryptographically secure random initialization vector
            // IV ensures that encrypting the same data twice produces different ciphertexts
            $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            // Perform authenticated encryption using AES-256-GCM
            // GCM mode provides both encryption and authentication in a single operation
            $tag = ''; // Will be populated by OpenSSL with the authentication tag
            $ciphertext = openssl_encrypt(
                $data,
                self::CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,    // Return raw binary data (not base64)
                $iv,
                $tag,                // Output parameter for authentication tag
                $context,            // Additional authenticated data (AAD)
                self::TAG_LENGTH     // Authentication tag length (16 bytes)
            );
            
            // Check for encryption failure
            // This could happen if the key is invalid or OpenSSL encounters an error
            if ($ciphertext === false) {
                throw new \Exception('Encryption failed');
            }
            
            // Combine all components into a single binary string
            // Format: [IV][TAG][CIPHERTEXT]
            // This allows us to extract each component during decryption
            $encrypted = $iv . $tag . $ciphertext;
            
            // Encode as base64 for safe transport/storage and add version prefix
            // Version prefix allows future algorithm changes without breaking existing data
            $result = 'v1:' . base64_encode($encrypted);
            
            // Log encryption operation (without sensitive data)
            $this->logger->debug('Data encrypted', [
                'context' => $context,
                'data_length' => strlen($data),
                'encrypted_length' => strlen($result)
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            // Log the error and re-throw
            // Never log the actual data being encrypted
            $this->logger->error('Encryption failed', [
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Decrypt data.
     *
     * Decrypts data encrypted with the encrypt() method, with comprehensive
     * edge case handling for corrupted or tampered data.
     *
     * @since 7.0.0
     *
     * @param string $encrypted_data Encrypted data.
     * @param string $context        Decryption context.
     * @return string Decrypted data.
     * @throws \Exception If decryption fails.
     */
    public function decrypt(string $encrypted_data, string $context = 'default'): string {
        try {
            // Edge case: Handle empty input
            if (empty($encrypted_data)) {
                throw new \Exception('Empty encrypted data provided');
            }
            
            // Edge case: Validate input length (minimum: version + IV + tag + 1 byte)
            if (strlen($encrypted_data) < 50) {
                throw new \Exception('Encrypted data too short to be valid');
            }
            
            // Check version and handle future versions
            if (strpos($encrypted_data, 'v1:') !== 0) {
                // Check for newer versions
                if (preg_match('/^v(\d+):/', $encrypted_data, $matches)) {
                    $version = (int)$matches[1];
                    throw new \Exception("Unsupported encryption version: v{$version}");
                }
                throw new \Exception('Invalid encryption format - missing version prefix');
            }
            
            // Remove version prefix and decode
            $base64_data = substr($encrypted_data, 3);
            
            // Edge case: Validate base64 before decoding
            if (!preg_match('/^[a-zA-Z0-9\/+]*={0,2}$/', $base64_data)) {
                throw new \Exception('Invalid base64 characters detected');
            }
            
            $encrypted = base64_decode($base64_data, true); // Strict mode
            if ($encrypted === false) {
                throw new \Exception('Invalid base64 encoding');
            }
            
            // Edge case: Validate decoded data length
            $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $min_length = $iv_length + self::TAG_LENGTH + 1; // At least 1 byte of ciphertext
            if (strlen($encrypted) < $min_length) {
                throw new \Exception('Decoded data too short to contain valid components');
            }
            
            // Get key
            $key = $this->key_manager->get_key($context);
            
            // Extract components with validation
            $iv = substr($encrypted, 0, $iv_length);
            $tag = substr($encrypted, $iv_length, self::TAG_LENGTH);
            $ciphertext = substr($encrypted, $iv_length + self::TAG_LENGTH);
            
            // Edge case: Validate components
            if (strlen($iv) !== $iv_length) {
                throw new \Exception('Invalid IV length');
            }
            if (strlen($tag) !== self::TAG_LENGTH) {
                throw new \Exception('Invalid authentication tag length');
            }
            if (empty($ciphertext)) {
                throw new \Exception('No ciphertext found');
            }
            
            // Attempt decryption with current key
            $decrypted = @openssl_decrypt(
                $ciphertext,
                self::CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $context
            );
            
            // Edge case: Try previous key versions if current fails
            if ($decrypted === false) {
                // Extract key version hint if available
                $key_version = $this->extract_key_version_hint($encrypted_data);
                if ($key_version !== null) {
                    $old_key = $this->key_manager->get_key_by_version($context, $key_version);
                    if ($old_key) {
                        $decrypted = @openssl_decrypt(
                            $ciphertext,
                            self::CIPHER_METHOD,
                            $old_key,
                            OPENSSL_RAW_DATA,
                            $iv,
                            $tag,
                            $context
                        );
                    }
                }
                
                if ($decrypted === false) {
                    // Check if this is a authentication failure (tampering) vs other error
                    $openssl_error = openssl_error_string();
                    if (strpos($openssl_error, 'authentication') !== false) {
                        throw new \Exception('Authentication tag verification failed - data may be tampered');
                    }
                    throw new \Exception('Decryption failed: ' . $openssl_error);
                }
            }
            
            return $decrypted;
            
        } catch (\Exception $e) {
            $this->logger->error('Decryption failed', [
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Encrypt array or object.
     *
     * @param mixed  $data    Data to encrypt.
     * @param string $context Encryption context.
     * @return string Encrypted data.
     */
    public function encrypt_data($data, string $context = 'default'): string {
        // Edge case: Handle circular references and invalid data
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            throw new \Exception('Failed to encode data as JSON');
        }
        return $this->encrypt($json, $context);
    }
    
    /**
     * Decrypt to array or object.
     *
     * @param string $encrypted_data Encrypted data.
     * @param string $context        Decryption context.
     * @param bool   $assoc          Return associative array.
     * @return mixed Decrypted data.
     */
    public function decrypt_data(string $encrypted_data, string $context = 'default', bool $assoc = true) {
        $json = $this->decrypt($encrypted_data, $context);
        
        // Edge case: Validate JSON before decoding
        if (!$this->is_valid_json($json)) {
            throw new \Exception('Decrypted data is not valid JSON');
        }
        
        $decoded = json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
        if ($decoded === null && $json !== 'null') {
            throw new \Exception('Failed to decode decrypted JSON data');
        }
        
        return $decoded;
    }
    
    /**
     * Hash data (one-way).
     *
     * @param string $data Data to hash.
     * @param string $salt Optional salt.
     * @return string Hashed data.
     */
    public function hash(string $data, string $salt = ''): string {
        $key = $this->key_manager->get_key('hashing');
        return hash_hmac('sha256', $data . $salt, $key);
    }
    
    /**
     * Verify hash.
     *
     * @param string $data Data to verify.
     * @param string $hash Hash to compare.
     * @param string $salt Optional salt.
     * @return bool True if matches.
     */
    public function verify_hash(string $data, string $hash, string $salt = ''): bool {
        $computed = $this->hash($data, $salt);
        return hash_equals($computed, $hash);
    }
    
    /**
     * Generate secure random token.
     *
     * @param int $length Token length.
     * @return string Random token.
     */
    public function generate_token(int $length = 32): string {
        // Edge case: Validate length
        if ($length < 1 || $length > 1024) {
            throw new \InvalidArgumentException('Token length must be between 1 and 1024');
        }
        
        // Use cryptographically secure random with entropy check
        $crypto_strong = false;
        $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);
        
        if (!$crypto_strong) {
            // Fallback to random_bytes if OpenSSL not cryptographically strong
            $bytes = random_bytes($length);
        }
        
        return bin2hex($bytes);
    }
    
    /**
     * Extract key version hint from encrypted data.
     *
     * @param string $encrypted_data Encrypted data.
     * @return int|null Key version or null.
     */
    private function extract_key_version_hint(string $encrypted_data): ?int {
        // Future enhancement: embed key version in encrypted data
        // For now, return null
        return null;
    }
    
    /**
     * Validate JSON string.
     *
     * @param string $json JSON string to validate.
     * @return bool True if valid JSON.
     */
    private function is_valid_json(string $json): bool {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
}