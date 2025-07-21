<?php
/**
 * Session Hardening and Security
 * 
 * Implements secure session management with advanced protection
 * 
 * @package MoneyQuiz\Security\Authentication
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Authentication;

use Exception;

class SessionHardening {
    private $db;
    private $session_table;
    private $config;
    private $fingerprint_components = [];
    
    public function __construct($config = []) {
        $this->db = $GLOBALS['wpdb'];
        $this->session_table = $this->db->prefix . 'money_quiz_sessions';
        
        $this->config = wp_parse_args($config, [
            'session_lifetime' => 3600, // 1 hour
            'idle_timeout' => 1800, // 30 minutes
            'regenerate_interval' => 300, // 5 minutes
            'max_sessions_per_user' => 3,
            'enforce_ssl' => true,
            'secure_cookie' => true,
            'httponly_cookie' => true,
            'samesite_cookie' => 'Strict',
            'fingerprint_validation' => true,
            'ip_validation' => 'flexible', // strict, flexible, or none
            'concurrent_session_policy' => 'limit', // limit, kick_oldest, or allow
        ]);
        
        $this->init_fingerprint_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Session management
        add_action('init', [$this, 'init_session'], 1);
        add_action('wp_login', [$this, 'on_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'on_user_logout']);
        add_action('auth_cookie_valid', [$this, 'validate_session'], 10, 2);
        
        // Security headers
        add_action('send_headers', [$this, 'send_security_headers']);
        
        // Cleanup
        add_action('money_quiz_cleanup_sessions', [$this, 'cleanup_expired_sessions']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('money_quiz_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'money_quiz_cleanup_sessions');
        }
    }
    
    /**
     * Initialize fingerprint components
     */
    private function init_fingerprint_components() {
        $this->fingerprint_components = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'dnt' => $_SERVER['HTTP_DNT'] ?? '',
        ];
    }
    
    /**
     * Initialize session
     */
    public function init_session() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $session_id = $this->get_current_session_id();
        
        if (!$session_id) {
            // No valid session, create new one
            $this->create_session($user_id);
        } else {
            // Validate and update existing session
            if (!$this->validate_session_security($session_id, $user_id)) {
                // Invalid session, force logout
                wp_logout();
                wp_die('Session security validation failed. Please log in again.');
            }
            
            // Update session activity
            $this->update_session_activity($session_id);
            
            // Check if regeneration needed
            if ($this->should_regenerate_session($session_id)) {
                $this->regenerate_session($session_id, $user_id);
            }
        }
    }
    
    /**
     * Handle user login
     */
    public function on_user_login($user_login, $user) {
        // Check concurrent session policy
        $this->handle_concurrent_sessions($user->ID);
        
        // Create new secure session
        $session_id = $this->create_session($user->ID);
        
        // Log successful login
        $this->log_session_event($user->ID, 'login', [
            'session_id' => $session_id,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
    }
    
    /**
     * Handle user logout
     */
    public function on_user_logout() {
        $user_id = get_current_user_id();
        $session_id = $this->get_current_session_id();
        
        if ($session_id) {
            // Destroy session
            $this->destroy_session($session_id);
            
            // Log logout
            $this->log_session_event($user_id, 'logout', [
                'session_id' => $session_id
            ]);
        }
    }
    
    /**
     * Create new session
     */
    private function create_session($user_id) {
        $session_id = $this->generate_session_id();
        $fingerprint = $this->generate_fingerprint();
        $ip_address = $this->get_client_ip();
        
        // Store session
        $this->db->insert($this->session_table, [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'fingerprint' => $fingerprint,
            'created_at' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'last_regeneration' => current_time('mysql'),
            'is_active' => 1
        ]);
        
        // Set secure cookie
        $this->set_session_cookie($session_id, $user_id);
        
        return $session_id;
    }
    
    /**
     * Generate secure session ID
     */
    private function generate_session_id() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generate device fingerprint
     */
    private function generate_fingerprint() {
        $data = json_encode($this->fingerprint_components);
        return hash('sha256', $data);
    }
    
    /**
     * Set session cookie
     */
    private function set_session_cookie($session_id, $user_id) {
        $cookie_data = [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'timestamp' => time()
        ];
        
        $cookie_value = $this->encrypt_cookie_data($cookie_data);
        
        $cookie_options = [
            'expires' => time() + $this->config['session_lifetime'],
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => $this->config['secure_cookie'],
            'httponly' => $this->config['httponly_cookie'],
            'samesite' => $this->config['samesite_cookie']
        ];
        
        setcookie('money_quiz_session', $cookie_value, $cookie_options);
    }
    
    /**
     * Encrypt cookie data
     */
    private function encrypt_cookie_data($data) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $key,
            0,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt cookie data
     */
    private function decrypt_cookie_data($encrypted) {
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );
        
        return json_decode($decrypted, true);
    }
    
    /**
     * Get encryption key
     */
    private function get_encryption_key() {
        if (!defined('LOGGED_IN_KEY') || !defined('LOGGED_IN_SALT')) {
            throw new Exception('WordPress salts not defined');
        }
        
        return hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT . 'money_quiz_session');
    }
    
    /**
     * Get current session ID
     */
    private function get_current_session_id() {
        if (!isset($_COOKIE['money_quiz_session'])) {
            return null;
        }
        
        try {
            $cookie_data = $this->decrypt_cookie_data($_COOKIE['money_quiz_session']);
            return $cookie_data['session_id'] ?? null;
        } catch (Exception $e) {
            error_log('Session cookie decryption error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate session security
     */
    private function validate_session_security($session_id, $user_id) {
        $session = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->session_table} 
            WHERE session_id = %s AND user_id = %d AND is_active = 1",
            $session_id,
            $user_id
        ));
        
        if (!$session) {
            return false;
        }
        
        // Check session expiry
        if ($this->is_session_expired($session)) {
            $this->destroy_session($session_id);
            return false;
        }
        
        // Check idle timeout
        if ($this->is_session_idle($session)) {
            $this->destroy_session($session_id);
            return false;
        }
        
        // Validate fingerprint
        if ($this->config['fingerprint_validation']) {
            $current_fingerprint = $this->generate_fingerprint();
            if ($session->fingerprint !== $current_fingerprint) {
                $this->log_session_event($user_id, 'fingerprint_mismatch', [
                    'session_id' => $session_id,
                    'expected' => $session->fingerprint,
                    'actual' => $current_fingerprint
                ]);
                return false;
            }
        }
        
        // Validate IP
        if ($this->config['ip_validation'] !== 'none') {
            if (!$this->validate_ip($session->ip_address)) {
                $this->log_session_event($user_id, 'ip_mismatch', [
                    'session_id' => $session_id,
                    'expected' => $session->ip_address,
                    'actual' => $this->get_client_ip()
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if session expired
     */
    private function is_session_expired($session) {
        $created = strtotime($session->created_at);
        return (time() - $created) > $this->config['session_lifetime'];
    }
    
    /**
     * Check if session idle
     */
    private function is_session_idle($session) {
        $last_activity = strtotime($session->last_activity);
        return (time() - $last_activity) > $this->config['idle_timeout'];
    }
    
    /**
     * Validate IP address
     */
    private function validate_ip($session_ip) {
        $current_ip = $this->get_client_ip();
        
        if ($this->config['ip_validation'] === 'strict') {
            return $session_ip === $current_ip;
        } elseif ($this->config['ip_validation'] === 'flexible') {
            // Allow same subnet (first 3 octets)
            $session_parts = explode('.', $session_ip);
            $current_parts = explode('.', $current_ip);
            
            return $session_parts[0] === $current_parts[0] &&
                   $session_parts[1] === $current_parts[1] &&
                   $session_parts[2] === $current_parts[2];
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Update session activity
     */
    private function update_session_activity($session_id) {
        $this->db->update(
            $this->session_table,
            ['last_activity' => current_time('mysql')],
            ['session_id' => $session_id]
        );
    }
    
    /**
     * Check if session should be regenerated
     */
    private function should_regenerate_session($session_id) {
        $session = $this->db->get_row($this->db->prepare(
            "SELECT last_regeneration FROM {$this->session_table} WHERE session_id = %s",
            $session_id
        ));
        
        if (!$session) {
            return false;
        }
        
        $last_regeneration = strtotime($session->last_regeneration);
        return (time() - $last_regeneration) > $this->config['regenerate_interval'];
    }
    
    /**
     * Regenerate session ID
     */
    private function regenerate_session($old_session_id, $user_id) {
        $new_session_id = $this->generate_session_id();
        
        // Update database
        $this->db->update(
            $this->session_table,
            [
                'session_id' => $new_session_id,
                'last_regeneration' => current_time('mysql')
            ],
            ['session_id' => $old_session_id]
        );
        
        // Update cookie
        $this->set_session_cookie($new_session_id, $user_id);
        
        // Log regeneration
        $this->log_session_event($user_id, 'session_regenerated', [
            'old_session' => $old_session_id,
            'new_session' => $new_session_id
        ]);
    }
    
    /**
     * Handle concurrent sessions
     */
    private function handle_concurrent_sessions($user_id) {
        $active_sessions = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->session_table} 
            WHERE user_id = %d AND is_active = 1 
            ORDER BY created_at DESC",
            $user_id
        ));
        
        $session_count = count($active_sessions);
        
        if ($session_count >= $this->config['max_sessions_per_user']) {
            if ($this->config['concurrent_session_policy'] === 'limit') {
                // Prevent new login
                wp_die('Maximum concurrent sessions reached. Please log out from another device.');
            } elseif ($this->config['concurrent_session_policy'] === 'kick_oldest') {
                // Destroy oldest sessions
                $to_destroy = $session_count - $this->config['max_sessions_per_user'] + 1;
                
                for ($i = $session_count - 1; $i >= $session_count - $to_destroy; $i--) {
                    $this->destroy_session($active_sessions[$i]->session_id);
                }
            }
        }
    }
    
    /**
     * Destroy session
     */
    private function destroy_session($session_id) {
        $this->db->update(
            $this->session_table,
            ['is_active' => 0],
            ['session_id' => $session_id]
        );
        
        // Clear cookie if current session
        if ($session_id === $this->get_current_session_id()) {
            setcookie('money_quiz_session', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
    }
    
    /**
     * Send security headers
     */
    public function send_security_headers() {
        // Strict Transport Security
        if ($this->config['enforce_ssl'] && is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Additional security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Remove PHP version
        header_remove('X-Powered-By');
    }
    
    /**
     * Cleanup expired sessions
     */
    public function cleanup_expired_sessions() {
        $cutoff_time = date('Y-m-d H:i:s', time() - $this->config['session_lifetime']);
        
        $this->db->query($this->db->prepare(
            "UPDATE {$this->session_table} 
            SET is_active = 0 
            WHERE created_at < %s OR last_activity < %s",
            $cutoff_time,
            date('Y-m-d H:i:s', time() - $this->config['idle_timeout'])
        ));
        
        // Delete old inactive sessions (30 days)
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->session_table} 
            WHERE is_active = 0 AND created_at < %s",
            date('Y-m-d H:i:s', time() - (30 * 24 * 60 * 60))
        ));
    }
    
    /**
     * Log session events
     */
    private function log_session_event($user_id, $event, $data = []) {
        $this->db->insert(
            $this->db->prefix . 'money_quiz_session_logs',
            [
                'user_id' => $user_id,
                'event' => $event,
                'data' => json_encode($data),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Get active sessions for user
     */
    public function get_user_sessions($user_id) {
        return $this->db->get_results($this->db->prepare(
            "SELECT session_id, ip_address, user_agent, created_at, last_activity 
            FROM {$this->session_table} 
            WHERE user_id = %d AND is_active = 1 
            ORDER BY last_activity DESC",
            $user_id
        ));
    }
    
    /**
     * Terminate all user sessions
     */
    public function terminate_all_sessions($user_id) {
        $this->db->update(
            $this->session_table,
            ['is_active' => 0],
            ['user_id' => $user_id]
        );
        
        $this->log_session_event($user_id, 'all_sessions_terminated');
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sessions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            fingerprint varchar(64),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            last_regeneration datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY is_active (is_active),
            KEY last_activity (last_activity)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Session logs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_session_logs (
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
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Authentication\SessionHardening', 'create_tables']);