<?php
/**
 * Audit Core Logger
 * 
 * @package MoneyQuiz\Security\Audit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Audit;

/**
 * Audit Log Interface
 */
interface AuditLogInterface {
    public function log($event, $level = 'info', array $context = []);
    public function emergency($event, array $context = []);
    public function alert($event, array $context = []);
    public function critical($event, array $context = []);
    public function error($event, array $context = []);
    public function warning($event, array $context = []);
    public function notice($event, array $context = []);
    public function info($event, array $context = []);
    public function debug($event, array $context = []);
}

/**
 * Log Levels
 */
class LogLevel {
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    /**
     * Get all levels
     */
    public static function getAllLevels() {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG
        ];
    }
    
    /**
     * Get level priority
     */
    public static function getPriority($level) {
        $priorities = [
            self::EMERGENCY => 800,
            self::ALERT => 700,
            self::CRITICAL => 600,
            self::ERROR => 500,
            self::WARNING => 400,
            self::NOTICE => 300,
            self::INFO => 200,
            self::DEBUG => 100
        ];
        
        return $priorities[$level] ?? 0;
    }
}

/**
 * Base Audit Logger
 */
abstract class BaseAuditLogger implements AuditLogInterface {
    
    protected $min_level = LogLevel::INFO;
    protected $context_defaults = [];
    
    /**
     * Set minimum log level
     */
    public function setMinLevel($level) {
        if (in_array($level, LogLevel::getAllLevels())) {
            $this->min_level = $level;
        }
    }
    
    /**
     * Check if should log
     */
    protected function shouldLog($level) {
        return LogLevel::getPriority($level) >= LogLevel::getPriority($this->min_level);
    }
    
    /**
     * Format log entry
     */
    protected function formatEntry($event, $level, array $context) {
        return [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'context' => array_merge($this->context_defaults, $context)
        ];
    }
    
    /**
     * Get client IP
     */
    protected function getClientIp() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Log levels implementation
     */
    public function emergency($event, array $context = []) {
        return $this->log($event, LogLevel::EMERGENCY, $context);
    }
    
    public function alert($event, array $context = []) {
        return $this->log($event, LogLevel::ALERT, $context);
    }
    
    public function critical($event, array $context = []) {
        return $this->log($event, LogLevel::CRITICAL, $context);
    }
    
    public function error($event, array $context = []) {
        return $this->log($event, LogLevel::ERROR, $context);
    }
    
    public function warning($event, array $context = []) {
        return $this->log($event, LogLevel::WARNING, $context);
    }
    
    public function notice($event, array $context = []) {
        return $this->log($event, LogLevel::NOTICE, $context);
    }
    
    public function info($event, array $context = []) {
        return $this->log($event, LogLevel::INFO, $context);
    }
    
    public function debug($event, array $context = []) {
        return $this->log($event, LogLevel::DEBUG, $context);
    }
}