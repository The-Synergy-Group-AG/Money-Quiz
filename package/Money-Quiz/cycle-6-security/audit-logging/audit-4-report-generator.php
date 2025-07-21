<?php
/**
 * Audit Report Generator
 * 
 * @package MoneyQuiz\Security\Audit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Audit;

/**
 * Report Generator
 */
class AuditReportGenerator {
    
    private $logger;
    
    public function __construct(DatabaseAuditLogger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Generate summary report
     */
    public function generateSummary($date_from, $date_to) {
        $logs = $this->logger->query([
            'date_from' => $date_from,
            'date_to' => $date_to,
            'limit' => 10000
        ]);
        
        return [
            'period' => [
                'from' => $date_from,
                'to' => $date_to
            ],
            'total_events' => count($logs),
            'events_by_level' => $this->groupByLevel($logs),
            'events_by_type' => $this->groupByEvent($logs),
            'top_users' => $this->getTopUsers($logs),
            'security_events' => $this->getSecurityEvents($logs),
            'error_summary' => $this->getErrorSummary($logs)
        ];
    }
    
    /**
     * Generate compliance report
     */
    public function generateComplianceReport($date_from, $date_to) {
        $logs = $this->logger->query([
            'date_from' => $date_from,
            'date_to' => $date_to,
            'limit' => 10000
        ]);
        
        return [
            'authentication_events' => $this->getAuthEvents($logs),
            'data_access_events' => $this->getDataAccessEvents($logs),
            'configuration_changes' => $this->getConfigChanges($logs),
            'security_incidents' => $this->getSecurityIncidents($logs),
            'user_activity' => $this->getUserActivity($logs)
        ];
    }
    
    /**
     * Group events by level
     */
    private function groupByLevel($logs) {
        $levels = [];
        
        foreach ($logs as $log) {
            $level = $log->level;
            if (!isset($levels[$level])) {
                $levels[$level] = 0;
            }
            $levels[$level]++;
        }
        
        return $levels;
    }
    
    /**
     * Group events by type
     */
    private function groupByEvent($logs) {
        $events = [];
        
        foreach ($logs as $log) {
            $event = $log->event;
            if (!isset($events[$event])) {
                $events[$event] = 0;
            }
            $events[$event]++;
        }
        
        arsort($events);
        return array_slice($events, 0, 20);
    }
    
    /**
     * Get top users by activity
     */
    private function getTopUsers($logs) {
        $users = [];
        
        foreach ($logs as $log) {
            if ($log->user_id) {
                if (!isset($users[$log->user_id])) {
                    $users[$log->user_id] = 0;
                }
                $users[$log->user_id]++;
            }
        }
        
        arsort($users);
        $top_users = array_slice($users, 0, 10, true);
        
        // Get user details
        $result = [];
        foreach ($top_users as $user_id => $count) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $result[] = [
                    'user_id' => $user_id,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'event_count' => $count
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get security events
     */
    private function getSecurityEvents($logs) {
        $security_events = array_filter($logs, function($log) {
            return strpos($log->event, 'security.') === 0 ||
                   strpos($log->event, 'auth.') === 0;
        });
        
        return [
            'total' => count($security_events),
            'failed_logins' => $this->countEvents($security_events, EventType::LOGIN_FAILED),
            'threats_detected' => $this->countEvents($security_events, EventType::SECURITY_THREAT),
            'rate_limits' => $this->countEvents($security_events, EventType::RATE_LIMIT_EXCEEDED)
        ];
    }
    
    /**
     * Get error summary
     */
    private function getErrorSummary($logs) {
        $errors = array_filter($logs, function($log) {
            return in_array($log->level, [
                LogLevel::ERROR,
                LogLevel::CRITICAL,
                LogLevel::ALERT,
                LogLevel::EMERGENCY
            ]);
        });
        
        $summary = [];
        foreach ($errors as $error) {
            $key = $error->event;
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'count' => 0,
                    'last_occurrence' => null,
                    'level' => $error->level
                ];
            }
            $summary[$key]['count']++;
            $summary[$key]['last_occurrence'] = $error->timestamp;
        }
        
        return $summary;
    }
    
    /**
     * Count specific events
     */
    private function countEvents($logs, $event_type) {
        return count(array_filter($logs, function($log) use ($event_type) {
            return $log->event === $event_type;
        }));
    }
    
    /**
     * Export report
     */
    public function exportReport($report, $format = 'json') {
        switch ($format) {
            case 'csv':
                return $this->exportCsv($report);
            case 'pdf':
                return $this->exportPdf($report);
            default:
                return json_encode($report, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Get authentication events
     */
    private function getAuthEvents($logs) {
        return array_filter($logs, function($log) {
            return strpos($log->event, 'auth.') === 0;
        });
    }
    
    /**
     * Get data access events
     */
    private function getDataAccessEvents($logs) {
        return array_filter($logs, function($log) {
            return strpos($log->event, 'data.') === 0;
        });
    }
    
    /**
     * Get configuration changes
     */
    private function getConfigChanges($logs) {
        return array_filter($logs, function($log) {
            return $log->event === EventType::CONFIG_CHANGED;
        });
    }
    
    /**
     * Get security incidents
     */
    private function getSecurityIncidents($logs) {
        return array_filter($logs, function($log) {
            return in_array($log->level, [LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY]) &&
                   strpos($log->event, 'security.') === 0;
        });
    }
    
    /**
     * Get user activity
     */
    private function getUserActivity($logs) {
        $activity = [];
        
        foreach ($logs as $log) {
            if ($log->user_id) {
                $date = date('Y-m-d', strtotime($log->timestamp));
                if (!isset($activity[$date])) {
                    $activity[$date] = [];
                }
                if (!in_array($log->user_id, $activity[$date])) {
                    $activity[$date][] = $log->user_id;
                }
            }
        }
        
        $result = [];
        foreach ($activity as $date => $users) {
            $result[] = [
                'date' => $date,
                'active_users' => count($users)
            ];
        }
        
        return $result;
    }
}