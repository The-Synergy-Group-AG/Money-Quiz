<?php
/**
 * Hybrid Router - Core routing logic for progressive migration
 * 
 * Routes requests between legacy and modern systems based on feature flags
 * 
 * @package MoneyQuiz
 * @subpackage Routing
 * @since 4.0.0
 */

namespace MoneyQuiz\Routing;

use MoneyQuiz\Routing\Handlers\LegacyHandler;
use MoneyQuiz\Routing\Handlers\ModernHandler;
use MoneyQuiz\Routing\Monitoring\RouteMonitor;
use MoneyQuiz\Routing\Security\InputSanitizer;

/**
 * Main routing class for hybrid progressive migration
 */
class HybridRouter {
    
    /**
     * @var FeatureFlagManager
     */
    private $feature_flags;
    
    /**
     * @var LegacyHandler
     */
    private $legacy_handler;
    
    /**
     * @var ModernHandler
     */
    private $modern_handler;
    
    /**
     * @var RouteMonitor
     */
    private $monitor;
    
    /**
     * @var InputSanitizer
     */
    private $sanitizer;
    
    /**
     * @var array Routing statistics
     */
    private $stats = [
        'total_requests' => 0,
        'modern_requests' => 0,
        'legacy_requests' => 0,
        'fallback_requests' => 0
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->feature_flags = new FeatureFlagManager();
        $this->legacy_handler = new LegacyHandler();
        $this->modern_handler = new ModernHandler();
        $this->monitor = new RouteMonitor();
        $this->sanitizer = new InputSanitizer();
        
        // Hook into WordPress for stats tracking
        add_action('shutdown', [$this, 'save_stats']);
    }
    
    /**
     * Route a request to appropriate handler
     * 
     * @param string $action The action to route
     * @param array $data Request data
     * @return array Response with system info
     */
    public function route($action, $data = []) {
        $start_time = microtime(true);
        $this->stats['total_requests']++;
        
        try {
            // Sanitize all inputs first
            $sanitized_data = $this->sanitizer->sanitize($action, $data);
            
            // Add routing metadata
            $sanitized_data['_routing'] = [
                'action' => $action,
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'session_id' => $this->get_session_id()
            ];
            
            // Determine which system should handle
            $use_modern = $this->should_use_modern($action, $sanitized_data);
            
            if ($use_modern) {
                $this->stats['modern_requests']++;
                $result = $this->route_to_modern($action, $sanitized_data);
            } else {
                $this->stats['legacy_requests']++;
                $result = $this->route_to_legacy($action, $sanitized_data);
            }
            
            // Record success metrics
            $duration = microtime(true) - $start_time;
            $this->monitor->record_success(
                $result['system'],
                $action,
                $duration,
                memory_get_peak_usage()
            );
            
            // Add routing metadata to response
            $result['_meta'] = [
                'routed_by' => 'hybrid_router',
                'duration' => $duration,
                'system' => $result['system'] ?? 'unknown'
            ];
            
            return $result;
            
        } catch (\Exception $e) {
            // Record error
            $this->monitor->record_error($e, $action, $data);
            
            // Always fallback to legacy on error
            $this->stats['fallback_requests']++;
            
            try {
                $result = $this->route_to_legacy($action, $data);
                $result['_meta']['fallback'] = true;
                $result['_meta']['fallback_reason'] = $e->getMessage();
                
                return $result;
                
            } catch (\Exception $fallback_exception) {
                // If even legacy fails, return error response
                return $this->error_response($fallback_exception, $action);
            }
        }
    }
    
    /**
     * Determine if modern system should handle request
     * 
     * @param string $action
     * @param array $data
     * @return bool
     */
    private function should_use_modern($action, $data) {
        // Check if hybrid routing is enabled at all
        if (!get_option('mq_hybrid_routing_enabled', true)) {
            return false;
        }
        
        // Check safe mode integration
        if (has_filter('mq_use_modern_system')) {
            $filtered = apply_filters('mq_use_modern_system', null, $action, $data);
            if ($filtered !== null) {
                return $filtered;
            }
        }
        
        // Check emergency rollback flag
        if (get_transient('mq_emergency_rollback')) {
            return false;
        }
        
        // Map actions to feature flags
        $action_to_flag = [
            'quiz_display' => 'modern_quiz_display',
            'quiz_list' => 'modern_quiz_list',
            'archetype_fetch' => 'modern_archetype_fetch',
            'archetype_list' => 'modern_archetype_fetch',
            'statistics_view' => 'modern_statistics',
            'statistics_summary' => 'modern_statistics'
        ];
        
        $flag = $action_to_flag[$action] ?? null;
        
        if (!$flag) {
            // Unknown action - use legacy
            return false;
        }
        
        // Check feature flag with user stickiness
        $user_id = $data['_routing']['user_id'] ?? 0;
        return $this->feature_flags->is_enabled($flag, $user_id);
    }
    
    /**
     * Route to modern system
     * 
     * @param string $action
     * @param array $data
     * @return array
     */
    private function route_to_modern($action, $data) {
        $result = $this->modern_handler->handle($action, $data);
        $result['system'] = 'modern';
        
        // Validate result structure
        if (!$this->validate_response($result)) {
            throw new \RuntimeException('Invalid response from modern handler');
        }
        
        return $result;
    }
    
    /**
     * Route to legacy system
     * 
     * @param string $action
     * @param array $data
     * @return array
     */
    private function route_to_legacy($action, $data) {
        $result = $this->legacy_handler->handle($action, $data);
        $result['system'] = 'legacy';
        
        return $result;
    }
    
    /**
     * Validate response structure
     * 
     * @param array $response
     * @return bool
     */
    private function validate_response($response) {
        // Basic validation - can be extended
        return is_array($response) && 
               (isset($response['success']) || isset($response['output']) || isset($response['data']));
    }
    
    /**
     * Generate error response
     * 
     * @param \Exception $e
     * @param string $action
     * @return array
     */
    private function error_response($e, $action) {
        return [
            'success' => false,
            'error' => true,
            'message' => 'Request could not be processed',
            'system' => 'error',
            '_meta' => [
                'action' => $action,
                'error_logged' => true
            ]
        ];
    }
    
    /**
     * Get or create session ID
     * 
     * @return string
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    /**
     * Save routing statistics
     */
    public function save_stats() {
        if (array_sum($this->stats) > 0) {
            $existing = get_option('mq_routing_stats', []);
            $today = date('Y-m-d');
            
            if (!isset($existing[$today])) {
                $existing[$today] = [
                    'total_requests' => 0,
                    'modern_requests' => 0,
                    'legacy_requests' => 0,
                    'fallback_requests' => 0
                ];
            }
            
            foreach ($this->stats as $key => $value) {
                $existing[$today][$key] += $value;
            }
            
            // Keep only last 30 days
            $existing = array_slice($existing, -30, null, true);
            
            update_option('mq_routing_stats', $existing);
        }
    }
    
    /**
     * Get routing statistics
     * 
     * @param int $days Number of days to retrieve
     * @return array
     */
    public function get_stats($days = 7) {
        $all_stats = get_option('mq_routing_stats', []);
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));
        
        $filtered = array_filter($all_stats, function($date) use ($cutoff) {
            return $date >= $cutoff;
        }, ARRAY_FILTER_USE_KEY);
        
        return $filtered;
    }
}