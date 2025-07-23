<?php
/**
 * Enhanced Nonce Manager with Constant-Time Comparison
 *
 * Prevents timing attacks by using constant-time string comparison.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Enhanced nonce manager with timing attack prevention.
 *
 * @since 7.0.0
 */
class EnhancedNonceManager extends NonceManager {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Constructor.
     *
     * @since 7.0.0
     *
     * @param string $prefix Nonce prefix.
     * @param Logger $logger Logger instance.
     */
    public function __construct(string $prefix, Logger $logger) {
        parent::__construct($prefix);
        $this->logger = $logger;
    }
    
    /**
     * Verify nonce with constant-time comparison.
     *
     * @since 7.0.0
     *
     * @param string $nonce  Nonce value.
     * @param string $action Nonce action.
     * @return bool True if valid.
     */
    public function verify(string $nonce, string $action): bool {
        $this->logger->debug('Verifying nonce with constant-time comparison', [
            'action' => $action,
            'nonce_length' => strlen($nonce)
        ]);

        // Get expected nonce values using WordPress internals
        $tick = wp_nonce_tick();
        $user = get_current_user_id();
        $token = wp_get_session_token();
        $prefixed_action = $this->prefix . $action;
        
        // Generate expected values for current and previous tick
        $expected_1 = $this->generate_nonce_value($tick, $prefixed_action, $user, $token);
        $expected_2 = $this->generate_nonce_value($tick - 1, $prefixed_action, $user, $token);
        
        // Use constant-time comparison
        $is_valid = $this->constant_time_compare($nonce, $expected_1) || 
                    $this->constant_time_compare($nonce, $expected_2);

        if (!$is_valid) {
            $this->logger->warning('Invalid nonce detected', [
                'action' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => $user
            ]);
        }

        return $is_valid;
    }
    
    /**
     * Generate nonce value matching WordPress core.
     *
     * @param int    $tick   Nonce tick.
     * @param string $action Action name.
     * @param int    $uid    User ID.
     * @param string $token  Session token.
     * @return string Nonce value.
     */
    private function generate_nonce_value(int $tick, string $action, int $uid, string $token): string {
        $hash = wp_hash($tick . '|' . $action . '|' . $uid . '|' . $token, 'nonce');
        return substr($hash, -12, 10);
    }
    
    /**
     * Constant-time string comparison to prevent timing attacks.
     *
     * @param string $known Known string.
     * @param string $user  User-provided string.
     * @return bool True if equal.
     */
    private function constant_time_compare(string $known, string $user): bool {
        // Use PHP's built-in constant-time comparison when available
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }
        
        // Fallback implementation for older PHP versions
        $known_len = strlen($known);
        $user_len = strlen($user);
        
        // XOR the lengths - will be 0 if equal
        $result = $known_len ^ $user_len;
        
        // Compare each character using bitwise operations
        for ($i = 0; $i < $known_len && $i < $user_len; $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }
        
        // Result will be 0 only if strings are identical
        return $result === 0;
    }
    
    /**
     * Verify AJAX request with enhanced security.
     *
     * @param string $action Action name.
     * @param string $query_arg Query argument.
     * @param bool   $die Whether to die on failure.
     * @return bool True if valid.
     */
    public function check_ajax_referer(string $action, string $query_arg = '_wpnonce', bool $die = true): bool {
        $nonce = $_REQUEST[$query_arg] ?? '';
        
        if (!$this->verify($nonce, $action)) {
            if ($die) {
                wp_die(__('Security check failed', 'money-quiz'), 403);
            }
            return false;
        }
        
        return true;
    }
}