<?php
/**
 * Encryption Interface
 *
 * Defines the contract for encryption services.
 *
 * @package MoneyQuiz\Security\Contracts
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Contracts;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Encryption interface.
 *
 * This interface defines the contract that all encryption implementations
 * must follow, allowing for easy swapping of encryption algorithms and
 * improving testability through dependency injection.
 *
 * @since 7.0.0
 */
interface EncryptionInterface {
    
    /**
     * Encrypt data.
     *
     * @param string $data    Data to encrypt.
     * @param string $context Encryption context.
     * @return string Encrypted data.
     * @throws \Exception If encryption fails.
     */
    public function encrypt(string $data, string $context = 'default'): string;
    
    /**
     * Decrypt data.
     *
     * @param string $encrypted_data Encrypted data.
     * @param string $context        Decryption context.
     * @return string Decrypted data.
     * @throws \Exception If decryption fails.
     */
    public function decrypt(string $encrypted_data, string $context = 'default'): string;
    
    /**
     * Encrypt structured data.
     *
     * @param mixed  $data    Data to encrypt.
     * @param string $context Encryption context.
     * @return string Encrypted data.
     */
    public function encrypt_data($data, string $context = 'default'): string;
    
    /**
     * Decrypt to structured data.
     *
     * @param string $encrypted_data Encrypted data.
     * @param string $context        Decryption context.
     * @param bool   $assoc          Return associative array.
     * @return mixed Decrypted data.
     */
    public function decrypt_data(string $encrypted_data, string $context = 'default', bool $assoc = true);
    
    /**
     * Generate secure hash.
     *
     * @param string $data Data to hash.
     * @param string $salt Optional salt.
     * @return string Hashed data.
     */
    public function hash(string $data, string $salt = ''): string;
    
    /**
     * Verify hash.
     *
     * @param string $data Data to verify.
     * @param string $hash Hash to compare.
     * @param string $salt Optional salt.
     * @return bool True if matches.
     */
    public function verify_hash(string $data, string $hash, string $salt = ''): bool;
    
    /**
     * Generate secure random token.
     *
     * @param int $length Token length.
     * @return string Random token.
     */
    public function generate_token(int $length = 32): string;
}