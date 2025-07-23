<?php
/**
 * Session Manager
 *
 * Manages secure session handling for anonymous users.
 *
 * @package MoneyQuiz\Core\Session
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Session;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Session manager class.
 *
 * @since 7.0.0
 */
class SessionManager {
    
    /**
     * Session cookie name.
     *
     * @var string
     */
    private const COOKIE_NAME = 'money_quiz_session';
    
    /**
     * Session duration (24 hours).
     *
     * @var int
     */
    private const SESSION_DURATION = 86400;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Current session data.
     *
     * @var array|null
     */
    private ?array $session_data = null;
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->init_session();
    }
    
    /**
     * Initialize session handling.
     *
     * @return void
     */
    private function init_session(): void {
        // Hook into WordPress init
        add_action('init', [$this, 'start_session']);
        add_action('wp_logout', [$this, 'destroy_session']);
        
        // Clean up expired sessions periodically
        if (!wp_next_scheduled('money_quiz_cleanup_sessions')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_cleanup_sessions');
        }
        add_action('money_quiz_cleanup_sessions', [$this, 'cleanup_expired_sessions']);
    }
    
    /**
     * Start or resume session.
     *
     * @return void
     */
    public function start_session(): void {
        if (is_user_logged_in()) {
            // For logged-in users, use user ID as session identifier
            $this->session_data = [
                'id' => 'user_' . get_current_user_id(),
                'type' => 'authenticated',
                'user_id' => get_current_user_id()
            ];
            return;
        }
        
        // For anonymous users, check for existing session
        $session_token = isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field($_COOKIE[self::COOKIE_NAME]) : null;
        
        if ($session_token && $this->validate_session_token($session_token)) {
            // Resume existing session
            $this->session_data = $this->get_session_data($session_token);
            $this->logger->debug('Session resumed', ['token' => substr($session_token, 0, 8) . '...']);
        } else {
            // Create new session
            $this->create_new_session();
        }
    }
    
    /**
     * Create new session.
     *
     * @return string Session token.
     */
    public function create_new_session(): string {
        $session_token = $this->generate_secure_token();
        
        $this->session_data = [
            'id' => $session_token,
            'type' => 'anonymous',
            'created_at' => time(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'data' => []
        ];
        
        // Store session data
        $this->save_session_data($session_token, $this->session_data);
        
        // Set secure cookie
        $this->set_session_cookie($session_token);
        
        $this->logger->info('New session created', ['token' => substr($session_token, 0, 8) . '...']);
        
        return $session_token;
    }
    
    /**
     * Get current session ID.
     *
     * @return string|null Session ID.
     */
    public function get_session_id(): ?string {
        return $this->session_data['id'] ?? null;
    }
    
    /**
     * Get session data.
     *
     * @param string $key Optional specific key.
     * @return mixed Session data or specific value.
     */
    public function get(string $key = null) {
        if ($key === null) {
            return $this->session_data;
        }
        
        return $this->session_data['data'][$key] ?? null;
    }
    
    /**
     * Set session data.
     *
     * @param string $key   Data key.
     * @param mixed  $value Data value.
     * @return void
     */
    public function set(string $key, $value): void {
        if (!$this->session_data) {
            $this->start_session();
        }
        
        $this->session_data['data'][$key] = $value;
        
        if ($this->session_data['type'] === 'anonymous') {
            $this->save_session_data($this->session_data['id'], $this->session_data);
        }
    }
    
    /**
     * Validate attempt access.
     *
     * @param int $attempt_id Attempt ID.
     * @return bool True if access allowed.
     */
    public function can_access_attempt(int $attempt_id): bool {
        $allowed_attempts = $this->get('allowed_attempts') ?? [];
        return in_array($attempt_id, $allowed_attempts, true);
    }
    
    /**
     * Grant attempt access.
     *
     * @param int $attempt_id Attempt ID.
     * @return void
     */
    public function grant_attempt_access(int $attempt_id): void {
        $allowed_attempts = $this->get('allowed_attempts') ?? [];
        if (!in_array($attempt_id, $allowed_attempts, true)) {
            $allowed_attempts[] = $attempt_id;
            $this->set('allowed_attempts', $allowed_attempts);
        }
    }
    
    /**
     * Destroy current session.
     *
     * @return void
     */
    public function destroy_session(): void {
        if ($this->session_data && $this->session_data['type'] === 'anonymous') {
            delete_transient('money_quiz_session_' . $this->session_data['id']);
            setcookie(self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        
        $this->session_data = null;
        $this->logger->info('Session destroyed');
    }
    
    /**
     * Generate secure token.
     *
     * @return string Secure token.
     */
    private function generate_secure_token(): string {
        return wp_hash(uniqid('mq_', true) . wp_rand() . microtime(true));
    }
    
    /**
     * Validate session token.
     *
     * @param string $token Session token.
     * @return bool True if valid.
     */
    private function validate_session_token(string $token): bool {
        // Check token format
        if (!preg_match('/^[a-f0-9]{32,}$/', $token)) {
            return false;
        }
        
        // Check if session exists
        return get_transient('money_quiz_session_' . $token) !== false;
    }
    
    /**
     * Get session data from storage.
     *
     * @param string $token Session token.
     * @return array|null Session data.
     */
    private function get_session_data(string $token): ?array {
        $data = get_transient('money_quiz_session_' . $token);
        return $data ? json_decode($data, true) : null;
    }
    
    /**
     * Save session data to storage.
     *
     * @param string $token Session token.
     * @param array  $data  Session data.
     * @return void
     */
    private function save_session_data(string $token, array $data): void {
        set_transient(
            'money_quiz_session_' . $token,
            json_encode($data),
            self::SESSION_DURATION
        );
    }
    
    /**
     * Set session cookie.
     *
     * @param string $token Session token.
     * @return void
     */
    private function set_session_cookie(string $token): void {
        setcookie(
            self::COOKIE_NAME,
            $token,
            time() + self::SESSION_DURATION,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // httponly
        );
    }
    
    /**
     * Get client IP address.
     *
     * @return string Client IP.
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR', 'HTTP_X_REAL_IP'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Clean up expired sessions.
     *
     * @return void
     */
    public function cleanup_expired_sessions(): void {
        global $wpdb;
        
        // Delete expired transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
            '_transient_timeout_money_quiz_session_%',
            $wpdb->esc_like('_transient_timeout_money_quiz_session_') . '%'
        ));
        
        $this->logger->info('Expired sessions cleaned up');
    }
}