<?php
declare(strict_types=1);

namespace MoneyQuiz\Security;

/**
 * Security event logger for tracking security-related events
 */
class SecurityLogger
{
    private const LOG_TABLE = 'money_quiz_security_logs';
    private const LOG_OPTION = 'money_quiz_security_log_enabled';
    
    /**
     * Log levels
     */
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_CRITICAL = 'critical';
    
    /**
     * Event types
     */
    public const EVENT_RATE_LIMIT = 'rate_limit';
    public const EVENT_CAPTCHA_FAIL = 'captcha_fail';
    public const EVENT_AUTH_FAIL = 'auth_fail';
    public const EVENT_VALIDATION_FAIL = 'validation_fail';
    public const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    
    private \wpdb $db;
    
    public function __construct(\wpdb $db)
    {
        $this->db = $db;
    }
    
    /**
     * Log a security event
     */
    public function log(string $event, string $level, array $context = []): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }
        
        $data = [
            'event_type' => $event,
            'level' => $level,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'context' => json_encode($context),
            'created_at' => current_time('mysql', true)
        ];
        
        // Log to database
        $this->logToDatabase($data);
        
        // Also log critical events to error log
        if ($level === self::LEVEL_CRITICAL) {
            $this->logToErrorLog($event, $context);
        }
    }
    
    /**
     * Log rate limit exceeded event
     */
    public function logRateLimitExceeded(string $action, string $identifier, int $limit): void
    {
        $this->log(self::EVENT_RATE_LIMIT, self::LEVEL_WARNING, [
            'action' => $action,
            'identifier' => $identifier,
            'limit' => $limit
        ]);
    }
    
    /**
     * Log CAPTCHA failure
     */
    public function logCaptchaFailure(string $reason, array $details = []): void
    {
        $this->log(self::EVENT_CAPTCHA_FAIL, self::LEVEL_WARNING, array_merge([
            'reason' => $reason
        ], $details));
    }
    
    /**
     * Log authentication failure
     */
    public function logAuthFailure(string $action, ?int $userId = null, string $reason = ''): void
    {
        $this->log(self::EVENT_AUTH_FAIL, self::LEVEL_WARNING, [
            'action' => $action,
            'user_id' => $userId,
            'reason' => $reason
        ]);
    }
    
    /**
     * Log validation failure
     */
    public function logValidationFailure(string $type, array $errors): void
    {
        $this->log(self::EVENT_VALIDATION_FAIL, self::LEVEL_INFO, [
            'validation_type' => $type,
            'errors' => $errors
        ]);
    }
    
    /**
     * Get recent security events
     */
    public function getRecentEvents(int $limit = 100, ?string $eventType = null): array
    {
        $table = $this->db->prefix . self::LOG_TABLE;
        
        $query = "SELECT * FROM {$table}";
        $params = [];
        
        if ($eventType) {
            $query .= " WHERE event_type = %s";
            $params[] = $eventType;
        }
        
        $query .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        $results = $this->db->get_results(
            $params ? $this->db->prepare($query, ...$params) : $query,
            ARRAY_A
        );
        
        // Decode context JSON
        foreach ($results as &$row) {
            $row['context'] = json_decode($row['context'], true) ?: [];
        }
        
        return $results ?: [];
    }
    
    /**
     * Clean old logs
     */
    public function cleanOldLogs(int $daysToKeep = 30): int
    {
        $table = $this->db->prefix . self::LOG_TABLE;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        return $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );
    }
    
    /**
     * Check if logging is enabled
     */
    private function isLoggingEnabled(): bool
    {
        return (bool) get_option(self::LOG_OPTION, true);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check for proxy headers
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return $ip ?: 'unknown';
    }
    
    /**
     * Log to database
     */
    private function logToDatabase(array $data): void
    {
        $table = $this->db->prefix . self::LOG_TABLE;
        
        $this->db->insert($table, $data, [
            '%s', // event_type
            '%s', // level
            '%d', // user_id
            '%s', // ip_address
            '%s', // user_agent
            '%s', // request_uri
            '%s', // context
            '%s'  // created_at
        ]);
    }
    
    /**
     * Log to WordPress error log
     */
    private function logToErrorLog(string $event, array $context): void
    {
        $message = sprintf(
            '[Money Quiz Security] %s - %s',
            $event,
            json_encode($context)
        );
        
        error_log($message);
    }
    
    /**
     * Create database table for logs
     */
    public static function createTable(\wpdb $db): void
    {
        $table = $db->prefix . self::LOG_TABLE;
        $charset_collate = $db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            level varchar(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            request_uri text,
            context longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY level (level),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}