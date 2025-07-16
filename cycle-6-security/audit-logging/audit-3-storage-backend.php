<?php
/**
 * Audit Storage Backend
 * 
 * @package MoneyQuiz\Security\Audit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Audit;

/**
 * Database Audit Logger
 */
class DatabaseAuditLogger extends BaseAuditLogger {
    
    private $table;
    private $wpdb;
    private $retention_days = 90;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'money_quiz_audit_logs';
    }
    
    /**
     * Log event
     */
    public function log($event, $level = 'info', array $context = []) {
        if (!$this->shouldLog($level)) {
            return false;
        }
        
        $entry = $this->formatEntry($event, $level, $context);
        
        return $this->wpdb->insert(
            $this->table,
            [
                'timestamp' => $entry['timestamp'],
                'level' => $entry['level'],
                'event' => $entry['event'],
                'user_id' => $entry['user_id'],
                'ip_address' => $entry['ip'],
                'user_agent' => $entry['user_agent'],
                'request_uri' => $entry['request_uri'],
                'context' => json_encode($entry['context'])
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Query logs
     */
    public function query($args = []) {
        $defaults = [
            'level' => null,
            'event' => null,
            'user_id' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $values = [];
        
        if ($args['level']) {
            $where[] = 'level = %s';
            $values[] = $args['level'];
        }
        
        if ($args['event']) {
            $where[] = 'event LIKE %s';
            $values[] = '%' . $this->wpdb->esc_like($args['event']) . '%';
        }
        
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if ($args['date_from']) {
            $where[] = 'timestamp >= %s';
            $values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where[] = 'timestamp <= %s';
            $values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM {$this->table} WHERE {$where_clause} 
                  ORDER BY timestamp {$args['order']} 
                  LIMIT %d OFFSET %d";
        
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, $values)
        );
    }
    
    /**
     * Clean old logs
     */
    public function cleanup() {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$this->retention_days} days"));
        
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE timestamp < %s",
            $cutoff
        ));
    }
    
    /**
     * Create database table
     */
    public static function createTable() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'money_quiz_audit_logs';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            level VARCHAR(20) NOT NULL,
            event VARCHAR(100) NOT NULL,
            user_id BIGINT(20) UNSIGNED,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_uri TEXT,
            context LONGTEXT,
            PRIMARY KEY (id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_level (level),
            INDEX idx_event (event),
            INDEX idx_user_id (user_id)
        ) {$charset};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * File Audit Logger
 */
class FileAuditLogger extends BaseAuditLogger {
    
    private $log_dir;
    private $current_file;
    private $max_file_size = 10485760; // 10MB
    
    public function __construct($log_dir = null) {
        if (!$log_dir) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/money-quiz-logs/audit';
        }
        
        $this->log_dir = $log_dir;
        $this->ensureLogDirectory();
    }
    
    /**
     * Log event
     */
    public function log($event, $level = 'info', array $context = []) {
        if (!$this->shouldLog($level)) {
            return false;
        }
        
        $entry = $this->formatEntry($event, $level, $context);
        $log_line = $this->formatLogLine($entry);
        
        $file = $this->getCurrentLogFile();
        
        return file_put_contents($file, $log_line, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Format log line
     */
    private function formatLogLine($entry) {
        return sprintf(
            "[%s] %s.%s: %s %s\n",
            $entry['timestamp'],
            strtoupper($entry['level']),
            $entry['event'],
            json_encode($entry['context']),
            $entry['ip']
        );
    }
    
    /**
     * Get current log file
     */
    private function getCurrentLogFile() {
        $date = date('Y-m-d');
        $file = $this->log_dir . '/audit-' . $date . '.log';
        
        // Rotate if file too large
        if (file_exists($file) && filesize($file) > $this->max_file_size) {
            $this->rotateLogFile($file);
        }
        
        return $file;
    }
    
    /**
     * Rotate log file
     */
    private function rotateLogFile($file) {
        $i = 1;
        while (file_exists($file . '.' . $i)) {
            $i++;
        }
        
        rename($file, $file . '.' . $i);
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Add .htaccess to prevent direct access
            file_put_contents(
                $this->log_dir . '/.htaccess',
                'Deny from all'
            );
        }
    }
}