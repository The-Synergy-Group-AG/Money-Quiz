<?php
/**
 * CSRF Token Storage Backend
 * 
 * @package MoneyQuiz\Security\CSRF
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\CSRF;

/**
 * Session-based Storage
 */
class CsrfSessionStorage implements CsrfStorageInterface, CsrfConstants {
    
    /**
     * Initialize session if needed
     */
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Store token data
     */
    public function store($token, array $data) {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        
        $_SESSION[self::SESSION_KEY][$token] = $data;
    }
    
    /**
     * Retrieve token data
     */
    public function retrieve($token) {
        return $_SESSION[self::SESSION_KEY][$token] ?? null;
    }
    
    /**
     * Remove token
     */
    public function remove($token) {
        unset($_SESSION[self::SESSION_KEY][$token]);
    }
    
    /**
     * Clean expired tokens
     */
    public function cleanup() {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }
        
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $token => $data) {
            if ($data['expires'] < $now) {
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
    }
}

/**
 * Database Storage
 */
class CsrfDatabaseStorage implements CsrfStorageInterface, CsrfConstants {
    
    private $db;
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'money_quiz_csrf_tokens';
    }
    
    /**
     * Store token data
     */
    public function store($token, array $data) {
        $this->db->insert(
            $this->table,
            [
                'token' => $token,
                'action' => $data['action'],
                'expires' => $data['expires'],
                'ip' => $data['ip'],
                'user_agent' => $data['user_agent'],
                'created' => $data['created']
            ]
        );
    }
    
    /**
     * Retrieve token data
     */
    public function retrieve($token) {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table} WHERE token = %s",
            $token
        ), ARRAY_A);
        
        return $row ?: null;
    }
    
    /**
     * Remove token
     */
    public function remove($token) {
        $this->db->delete($this->table, ['token' => $token]);
    }
    
    /**
     * Clean expired tokens
     */
    public function cleanup() {
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->table} WHERE expires < %d",
            time()
        ));
    }
    
    /**
     * Create database table
     */
    public static function createTable() {
        global $wpdb;
        $table = $wpdb->prefix . 'money_quiz_csrf_tokens';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            token VARCHAR(64) PRIMARY KEY,
            action VARCHAR(100),
            expires INT UNSIGNED,
            ip VARCHAR(45),
            user_agent TEXT,
            created INT UNSIGNED,
            INDEX idx_expires (expires)
        ) {$charset};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}