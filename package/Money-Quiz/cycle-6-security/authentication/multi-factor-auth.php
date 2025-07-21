<?php
/**
 * Multi-Factor Authentication Implementation
 * 
 * Provides TOTP and SMS-based MFA for enhanced security
 * 
 * @package MoneyQuiz\Security\Authentication
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Authentication;

use RobThree\Auth\TwoFactorAuth;
use Exception;

class MultiFactorAuth {
    private $tfa;
    private $db;
    private $sms_provider;
    private $config;
    
    public function __construct($config = []) {
        $this->config = wp_parse_args($config, [
            'issuer' => get_bloginfo('name'),
            'digits' => 6,
            'period' => 30,
            'algorithm' => 'sha256',
            'qr_size' => 200
        ]);
        
        $this->tfa = new TwoFactorAuth(
            $this->config['issuer'],
            $this->config['digits'],
            $this->config['period'],
            $this->config['algorithm']
        );
        
        $this->db = $GLOBALS['wpdb'];
    }
    
    /**
     * Enable MFA for user
     */
    public function enable_mfa($user_id, $method = 'totp') {
        try {
            if ($method === 'totp') {
                return $this->setup_totp($user_id);
            } elseif ($method === 'sms') {
                return $this->setup_sms($user_id);
            }
            
            throw new Exception('Invalid MFA method');
        } catch (Exception $e) {
            error_log('MFA enable error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Setup TOTP authentication
     */
    private function setup_totp($user_id) {
        // Generate secret
        $secret = $this->tfa->createSecret();
        
        // Store encrypted secret
        $encrypted_secret = $this->encrypt_secret($secret);
        update_user_meta($user_id, '_mfa_totp_secret', $encrypted_secret);
        update_user_meta($user_id, '_mfa_method', 'totp');
        update_user_meta($user_id, '_mfa_enabled', true);
        
        // Generate QR code
        $user = get_user_by('id', $user_id);
        $qr_code = $this->tfa->getQRCodeImageAsDataUri(
            $user->user_email,
            $secret,
            $this->config['qr_size']
        );
        
        // Generate backup codes
        $backup_codes = $this->generate_backup_codes($user_id);
        
        return [
            'secret' => $secret,
            'qr_code' => $qr_code,
            'backup_codes' => $backup_codes,
            'manual_entry' => [
                'issuer' => $this->config['issuer'],
                'account' => $user->user_email,
                'secret' => $secret
            ]
        ];
    }
    
    /**
     * Setup SMS authentication
     */
    private function setup_sms($user_id) {
        $phone = get_user_meta($user_id, 'phone_number', true);
        
        if (empty($phone)) {
            throw new Exception('Phone number required for SMS authentication');
        }
        
        // Validate phone number
        if (!$this->validate_phone_number($phone)) {
            throw new Exception('Invalid phone number format');
        }
        
        // Send verification code
        $code = $this->generate_sms_code();
        $this->send_sms_code($user_id, $code);
        
        update_user_meta($user_id, '_mfa_method', 'sms');
        update_user_meta($user_id, '_mfa_phone', $this->encrypt_data($phone));
        
        return [
            'phone' => $this->mask_phone_number($phone),
            'code_sent' => true
        ];
    }
    
    /**
     * Verify MFA code
     */
    public function verify_code($user_id, $code) {
        $method = get_user_meta($user_id, '_mfa_method', true);
        
        if ($method === 'totp') {
            return $this->verify_totp($user_id, $code);
        } elseif ($method === 'sms') {
            return $this->verify_sms($user_id, $code);
        }
        
        // Check backup codes as fallback
        return $this->verify_backup_code($user_id, $code);
    }
    
    /**
     * Verify TOTP code
     */
    private function verify_totp($user_id, $code) {
        $encrypted_secret = get_user_meta($user_id, '_mfa_totp_secret', true);
        
        if (empty($encrypted_secret)) {
            return false;
        }
        
        $secret = $this->decrypt_secret($encrypted_secret);
        
        // Verify with time window for clock drift
        $valid = $this->tfa->verifyCode($secret, $code, 2);
        
        if ($valid) {
            // Update last used time
            update_user_meta($user_id, '_mfa_last_used', time());
            
            // Log successful authentication
            $this->log_mfa_event($user_id, 'totp_success');
        } else {
            // Log failed attempt
            $this->log_mfa_event($user_id, 'totp_failed');
            
            // Check for brute force
            $this->check_brute_force($user_id);
        }
        
        return $valid;
    }
    
    /**
     * Verify SMS code
     */
    private function verify_sms($user_id, $code) {
        $stored_code = get_transient('mfa_sms_code_' . $user_id);
        $attempts = get_transient('mfa_sms_attempts_' . $user_id) ?: 0;
        
        if ($attempts >= 3) {
            $this->log_mfa_event($user_id, 'sms_blocked');
            return false;
        }
        
        if ($stored_code && hash_equals($stored_code, $code)) {
            delete_transient('mfa_sms_code_' . $user_id);
            delete_transient('mfa_sms_attempts_' . $user_id);
            
            update_user_meta($user_id, '_mfa_last_used', time());
            $this->log_mfa_event($user_id, 'sms_success');
            
            return true;
        }
        
        // Increment attempts
        set_transient('mfa_sms_attempts_' . $user_id, $attempts + 1, 300);
        $this->log_mfa_event($user_id, 'sms_failed');
        
        return false;
    }
    
    /**
     * Generate backup codes
     */
    private function generate_backup_codes($user_id, $count = 10) {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $code = $this->generate_secure_code(8);
            $codes[] = $code;
        }
        
        // Store hashed codes
        $hashed_codes = array_map(function($code) {
            return wp_hash_password($code);
        }, $codes);
        
        update_user_meta($user_id, '_mfa_backup_codes', $hashed_codes);
        
        return $codes;
    }
    
    /**
     * Verify backup code
     */
    private function verify_backup_code($user_id, $code) {
        $hashed_codes = get_user_meta($user_id, '_mfa_backup_codes', true);
        
        if (!is_array($hashed_codes)) {
            return false;
        }
        
        foreach ($hashed_codes as $index => $hashed) {
            if (wp_check_password($code, $hashed)) {
                // Remove used code
                unset($hashed_codes[$index]);
                update_user_meta($user_id, '_mfa_backup_codes', array_values($hashed_codes));
                
                $this->log_mfa_event($user_id, 'backup_code_used');
                
                // Notify user
                $this->notify_backup_code_used($user_id, count($hashed_codes));
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send SMS code
     */
    private function send_sms_code($user_id, $code) {
        $phone = $this->decrypt_data(get_user_meta($user_id, '_mfa_phone', true));
        
        // Store code temporarily (5 minutes)
        set_transient('mfa_sms_code_' . $user_id, $code, 300);
        
        // Send via SMS provider
        if ($this->sms_provider) {
            try {
                $this->sms_provider->send($phone, sprintf(
                    'Your %s verification code is: %s',
                    get_bloginfo('name'),
                    $code
                ));
            } catch (Exception $e) {
                error_log('SMS send error: ' . $e->getMessage());
                throw new Exception('Failed to send SMS code');
            }
        }
        
        return true;
    }
    
    /**
     * Generate secure code
     */
    private function generate_secure_code($length = 6) {
        $characters = '0123456789';
        if ($length > 6) {
            $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        $code = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }
        
        return $code;
    }
    
    /**
     * Generate SMS code
     */
    private function generate_sms_code() {
        return $this->generate_secure_code(6);
    }
    
    /**
     * Encrypt sensitive data
     */
    private function encrypt_secret($data) {
        return $this->encrypt_data($data);
    }
    
    /**
     * Decrypt sensitive data
     */
    private function decrypt_secret($data) {
        return $this->decrypt_data($data);
    }
    
    /**
     * Generic encryption method
     */
    private function encrypt_data($data) {
        if (!defined('LOGGED_IN_KEY') || !defined('LOGGED_IN_SALT')) {
            throw new Exception('WordPress salts not defined');
        }
        
        $key = hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Generic decryption method
     */
    private function decrypt_data($data) {
        if (!defined('LOGGED_IN_KEY') || !defined('LOGGED_IN_SALT')) {
            throw new Exception('WordPress salts not defined');
        }
        
        $key = hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT);
        $data = base64_decode($data);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Validate phone number
     */
    private function validate_phone_number($phone) {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check length (10-15 digits)
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    /**
     * Mask phone number for display
     */
    private function mask_phone_number($phone) {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $length = strlen($cleaned);
        
        if ($length >= 10) {
            return substr($cleaned, 0, 3) . str_repeat('*', $length - 6) . substr($cleaned, -3);
        }
        
        return str_repeat('*', $length);
    }
    
    /**
     * Check for brute force attempts
     */
    private function check_brute_force($user_id) {
        $attempts = get_transient('mfa_failed_attempts_' . $user_id) ?: 0;
        $attempts++;
        
        set_transient('mfa_failed_attempts_' . $user_id, $attempts, 900); // 15 minutes
        
        if ($attempts >= 5) {
            // Lock account temporarily
            update_user_meta($user_id, '_mfa_locked_until', time() + 900);
            
            // Notify user and admin
            $this->notify_account_locked($user_id);
            
            // Log security event
            $this->log_mfa_event($user_id, 'account_locked', [
                'attempts' => $attempts,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
        }
    }
    
    /**
     * Log MFA events
     */
    private function log_mfa_event($user_id, $event, $data = []) {
        $log_data = array_merge([
            'user_id' => $user_id,
            'event' => $event,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ], $data);
        
        // Store in database
        $this->db->insert(
            $this->db->prefix . 'mfa_logs',
            $log_data
        );
        
        // Also log to file for monitoring
        error_log(sprintf(
            '[MFA] %s - User: %d, IP: %s',
            $event,
            $user_id,
            $_SERVER['REMOTE_ADDR']
        ));
    }
    
    /**
     * Notify user of backup code usage
     */
    private function notify_backup_code_used($user_id, $remaining) {
        $user = get_user_by('id', $user_id);
        
        $subject = sprintf('[%s] Backup code used', get_bloginfo('name'));
        $message = sprintf(
            "A backup code was just used to access your account.\n\n" .
            "Remaining backup codes: %d\n\n" .
            "If this wasn't you, please secure your account immediately.\n\n" .
            "Time: %s\n" .
            "IP Address: %s",
            $remaining,
            current_time('mysql'),
            $_SERVER['REMOTE_ADDR']
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Notify account locked
     */
    private function notify_account_locked($user_id) {
        $user = get_user_by('id', $user_id);
        
        $subject = sprintf('[%s] Account temporarily locked', get_bloginfo('name'));
        $message = sprintf(
            "Your account has been temporarily locked due to multiple failed MFA attempts.\n\n" .
            "The lock will be lifted in 15 minutes.\n\n" .
            "If this wasn't you, please contact support immediately.\n\n" .
            "Time: %s\n" .
            "IP Address: %s",
            current_time('mysql'),
            $_SERVER['REMOTE_ADDR']
        );
        
        wp_mail($user->user_email, $subject, $message);
        
        // Also notify admin
        wp_mail(get_option('admin_email'), $subject, $message);
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mfa_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            event varchar(50) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip varchar(45),
            user_agent text,
            data text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY event (event),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize on plugin activation
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Authentication\MultiFactorAuth', 'create_tables']);