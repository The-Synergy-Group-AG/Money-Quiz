<?php
/**
 * Feature Flag Manager for Progressive Migration
 * 
 * Controls gradual rollout of modern system features
 * 
 * @package MoneyQuiz
 * @subpackage Routing
 * @since 4.0.0
 */

namespace MoneyQuiz\Routing;

/**
 * Manages feature flags for hybrid migration
 */
class FeatureFlagManager {
    
    /**
     * @var array Default feature flags
     */
    private $default_flags = [
        'modern_quiz_display' => 0.0,
        'modern_quiz_list' => 0.0,
        'modern_archetype_fetch' => 0.0,
        'modern_statistics' => 0.0,
        'modern_quiz_submit' => 0.0,
        'modern_prospect_save' => 0.0,
        'modern_email_send' => 0.0
    ];
    
    /**
     * @var array Current flags
     */
    private $flags;
    
    /**
     * @var array User assignments cache
     */
    private $user_assignments = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_flags();
        
        // Hook for admin updates
        add_action('update_option_mq_feature_flags', [$this, 'clear_cache'], 10, 2);
    }
    
    /**
     * Load flags from database
     */
    private function load_flags() {
        $saved_flags = get_option('mq_feature_flags', []);
        $this->flags = wp_parse_args($saved_flags, $this->default_flags);
        
        // Apply week-based configuration if exists
        $current_week = get_option('mq_hybrid_week', 0);
        if ($current_week > 0) {
            $this->apply_week_config($current_week);
        }
    }
    
    /**
     * Apply week-based configuration
     * 
     * @param int $week
     */
    private function apply_week_config($week) {
        $week_configs = [
            1 => [
                'modern_quiz_display' => 1.0,
                'modern_quiz_list' => 1.0,
                'modern_archetype_fetch' => 1.0,
                'modern_statistics' => 1.0,
                'modern_quiz_submit' => 1.0,
                'modern_prospect_save' => 1.0,
                'modern_email_send' => 1.0
            ],
            2 => [
                'modern_quiz_display' => 1.0,
                'modern_quiz_list' => 1.0,
                'modern_archetype_fetch' => 1.0,
                'modern_statistics' => 1.0,
                'modern_quiz_submit' => 1.0,
                'modern_prospect_save' => 1.0,
                'modern_email_send' => 1.0
            ],
            3 => [
                'modern_quiz_display' => 1.0,
                'modern_quiz_list' => 1.0,
                'modern_archetype_fetch' => 1.0,
                'modern_statistics' => 1.0,
                'modern_quiz_submit' => 1.0,
                'modern_prospect_save' => 1.0,
                'modern_email_send' => 1.0
            ],
            4 => array_fill_keys(array_keys($this->default_flags), 1.0)
        ];
        
        if (isset($week_configs[$week])) {
            $this->flags = wp_parse_args($week_configs[$week], $this->flags);
        }
    }
    
    /**
     * Check if feature is enabled for user
     * 
     * Uses consistent hashing for user stickiness
     * 
     * @param string $feature Feature flag name
     * @param int $user_id User ID (0 for anonymous)
     * @return bool
     */
    public function is_enabled($feature, $user_id = null) {
        if (!isset($this->flags[$feature])) {
            return false;
        }
        
        $percentage = floatval($this->flags[$feature]);
        
        // 0% or 100% are simple cases
        if ($percentage <= 0) {
            return false;
        }
        if ($percentage >= 1) {
            return true;
        }
        
        // Get consistent user identifier
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // For anonymous users, use session ID
        if ($user_id === 0) {
            $user_id = 'anon_' . $this->get_session_identifier();
        }
        
        // Check cache
        $cache_key = $feature . '_' . $user_id;
        if (isset($this->user_assignments[$cache_key])) {
            return $this->user_assignments[$cache_key];
        }
        
        // Use consistent hashing for stickiness
        $hash = crc32($cache_key);
        $max_hash = 4294967295; // Max value for CRC32
        $threshold = $percentage * $max_hash;
        
        $enabled = $hash < $threshold;
        
        // Cache the assignment
        $this->user_assignments[$cache_key] = $enabled;
        
        // Log assignment for monitoring
        $this->log_assignment($feature, $user_id, $enabled);
        
        return $enabled;
    }
    
    /**
     * Get all feature flags
     * 
     * @return array
     */
    public function get_all_flags() {
        return $this->flags;
    }
    
    /**
     * Get specific feature flag value
     * 
     * @param string $feature
     * @return float|null
     */
    public function get_flag_value($feature) {
        return $this->flags[$feature] ?? null;
    }
    
    /**
     * Update feature flag value
     * 
     * @param string $feature
     * @param float $value
     * @return bool
     */
    public function update_flag($feature, $value) {
        if (!isset($this->default_flags[$feature])) {
            return false;
        }
        
        $value = max(0, min(1, floatval($value)));
        $this->flags[$feature] = $value;
        
        return update_option('mq_feature_flags', $this->flags);
    }
    
    /**
     * Update multiple flags
     * 
     * @param array $flags
     * @return bool
     */
    public function update_flags($flags) {
        foreach ($flags as $feature => $value) {
            if (isset($this->default_flags[$feature])) {
                $this->flags[$feature] = max(0, min(1, floatval($value)));
            }
        }
        
        return update_option('mq_feature_flags', $this->flags);
    }
    
    /**
     * Get session identifier for anonymous users
     * 
     * @return string
     */
    private function get_session_identifier() {
        if (!session_id()) {
            session_start();
        }
        
        // Also check for cookie-based identifier
        if (isset($_COOKIE['mq_visitor_id'])) {
            return $_COOKIE['mq_visitor_id'];
        }
        
        // Create new identifier
        $visitor_id = wp_generate_uuid4();
        setcookie('mq_visitor_id', $visitor_id, time() + (86400 * 30), '/');
        
        return $visitor_id;
    }
    
    /**
     * Log feature flag assignment
     * 
     * @param string $feature
     * @param mixed $user_id
     * @param bool $enabled
     */
    private function log_assignment($feature, $user_id, $enabled) {
        // Only log a sample to avoid overwhelming the database
        if (mt_rand(1, 100) > 10) { // 10% sampling
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'mq_feature_assignments';
        
        // Create table if needed (should be done in activation)
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        
        $wpdb->insert(
            $table,
            [
                'feature' => $feature,
                'user_identifier' => strval($user_id),
                'enabled' => $enabled ? 1 : 0,
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%s']
        );
    }
    
    /**
     * Clear caches when flags are updated
     * 
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public function clear_cache($old_value, $new_value) {
        $this->user_assignments = [];
        $this->load_flags();
    }
    
    /**
     * Get feature adoption statistics
     * 
     * @param string $feature
     * @param int $hours
     * @return array
     */
    public function get_adoption_stats($feature, $hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'mq_feature_assignments';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(enabled) as enabled_count,
                COUNT(DISTINCT user_identifier) as unique_users
            FROM $table
            WHERE feature = %s
            AND timestamp > %s
        ", $feature, $since), ARRAY_A);
        
        if ($stats && $stats['total'] > 0) {
            $stats['adoption_rate'] = round(($stats['enabled_count'] / $stats['total']) * 100, 2);
        } else {
            $stats = [
                'total' => 0,
                'enabled_count' => 0,
                'unique_users' => 0,
                'adoption_rate' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Check if gradual rollout is in progress
     * 
     * @return bool
     */
    public function is_rollout_active() {
        foreach ($this->flags as $value) {
            if ($value > 0 && $value < 1) {
                return true;
            }
        }
        return false;
    }
}