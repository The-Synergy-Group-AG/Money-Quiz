<?php
/**
 * Rate Limiting Storage Backends
 * 
 * @package MoneyQuiz\Security\RateLimit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\RateLimit;

/**
 * Storage Interface
 */
interface StorageInterface {
    public function get($key);
    public function set($key, $value, $ttl = 0);
    public function delete($key);
    public function clear();
}

/**
 * Transient Storage
 */
class TransientStorage implements StorageInterface {
    
    /**
     * Get value
     */
    public function get($key) {
        return get_transient($key);
    }
    
    /**
     * Set value
     */
    public function set($key, $value, $ttl = 0) {
        return set_transient($key, $value, $ttl);
    }
    
    /**
     * Delete value
     */
    public function delete($key) {
        return delete_transient($key);
    }
    
    /**
     * Clear all rate limit transients
     */
    public function clear() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_rate_limit_%' 
            OR option_name LIKE '_transient_timeout_rate_limit_%'"
        );
    }
}

/**
 * Database Storage
 */
class DatabaseStorage implements StorageInterface {
    
    private $table;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'money_quiz_rate_limits';
    }
    
    /**
     * Get value
     */
    public function get($key) {
        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT data FROM {$this->table} 
            WHERE `key` = %s AND expires > %d",
            $key,
            time()
        ));
        
        return $result ? maybe_unserialize($result) : false;
    }
    
    /**
     * Set value
     */
    public function set($key, $value, $ttl = 0) {
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        return $this->wpdb->replace(
            $this->table,
            [
                'key' => $key,
                'data' => maybe_serialize($value),
                'expires' => $expires,
                'created' => time()
            ],
            ['%s', '%s', '%d', '%d']
        );
    }
    
    /**
     * Delete value
     */
    public function delete($key) {
        return $this->wpdb->delete(
            $this->table,
            ['key' => $key],
            ['%s']
        );
    }
    
    /**
     * Clear expired entries
     */
    public function clear() {
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE expires > 0 AND expires < %d",
            time()
        ));
    }
    
    /**
     * Create database table
     */
    public static function createTable() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'money_quiz_rate_limits';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `key` VARCHAR(64) PRIMARY KEY,
            data TEXT,
            expires INT UNSIGNED,
            created INT UNSIGNED,
            INDEX idx_expires (expires)
        ) {$charset};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * Memory Storage (for testing)
 */
class MemoryStorage implements StorageInterface {
    
    private static $data = [];
    
    public function get($key) {
        if (!isset(self::$data[$key])) {
            return false;
        }
        
        $item = self::$data[$key];
        
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            unset(self::$data[$key]);
            return false;
        }
        
        return $item['value'];
    }
    
    public function set($key, $value, $ttl = 0) {
        self::$data[$key] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0
        ];
        
        return true;
    }
    
    public function delete($key) {
        unset(self::$data[$key]);
        return true;
    }
    
    public function clear() {
        self::$data = [];
        return true;
    }
}