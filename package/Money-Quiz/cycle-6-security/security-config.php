<?php
/**
 * Security Configuration
 * 
 * @package MoneyQuiz\Security
 * @version 1.0.0
 */

namespace MoneyQuiz\Security;

/**
 * Security Configuration Manager
 */
class SecurityConfig {
    
    /**
     * Default security settings
     */
    private static $defaults = [
        // General Security
        'force_ssl' => true,
        'hide_version' => true,
        'disable_xmlrpc' => true,
        'disable_file_edit' => true,
        
        // CSRF Protection
        'csrf_enabled' => true,
        'csrf_token_lifetime' => 3600,
        'csrf_regenerate' => true,
        
        // XSS Protection
        'xss_filtering' => true,
        'content_security_policy' => true,
        'allowed_html_tags' => ['p', 'a', 'strong', 'em', 'ul', 'ol', 'li'],
        
        // SQL Protection
        'sql_validation' => true,
        'prepared_statements_only' => true,
        
        // Rate Limiting
        'rate_limiting_enabled' => true,
        'default_rate_limit' => [
            'attempts' => 60,
            'window' => 60,
            'lockout' => 300
        ],
        
        // Security Headers
        'security_headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ],
        
        // Audit Logging
        'audit_logging' => true,
        'log_retention_days' => 90,
        'log_security_events' => true,
        
        // Vulnerability Scanning
        'auto_scan' => true,
        'scan_frequency' => 'weekly',
        'scan_notifications' => true
    ];
    
    /**
     * Get security setting
     */
    public static function get($key, $default = null) {
        $settings = get_option('money_quiz_security_settings', []);
        
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        
        if (isset(self::$defaults[$key])) {
            return self::$defaults[$key];
        }
        
        return $default;
    }
    
    /**
     * Set security setting
     */
    public static function set($key, $value) {
        $settings = get_option('money_quiz_security_settings', []);
        $settings[$key] = $value;
        
        update_option('money_quiz_security_settings', $settings);
        
        // Clear any caches
        wp_cache_delete('money_quiz_security_settings', 'options');
        
        // Trigger action
        do_action('money_quiz_security_setting_updated', $key, $value);
    }
    
    /**
     * Get all settings
     */
    public static function getAll() {
        $saved = get_option('money_quiz_security_settings', []);
        return array_merge(self::$defaults, $saved);
    }
    
    /**
     * Reset to defaults
     */
    public static function resetToDefaults() {
        update_option('money_quiz_security_settings', []);
        do_action('money_quiz_security_settings_reset');
    }
    
    /**
     * Validate settings
     */
    public static function validate($settings) {
        $validated = [];
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'force_ssl':
                case 'hide_version':
                case 'disable_xmlrpc':
                case 'csrf_enabled':
                case 'xss_filtering':
                case 'rate_limiting_enabled':
                case 'audit_logging':
                case 'auto_scan':
                    $validated[$key] = (bool) $value;
                    break;
                    
                case 'csrf_token_lifetime':
                case 'log_retention_days':
                    $validated[$key] = absint($value);
                    break;
                    
                case 'allowed_html_tags':
                    $validated[$key] = array_map('sanitize_key', (array) $value);
                    break;
                    
                case 'security_headers':
                case 'default_rate_limit':
                    $validated[$key] = (array) $value;
                    break;
                    
                default:
                    $validated[$key] = $value;
            }
        }
        
        return $validated;
    }
    
    /**
     * Export settings
     */
    public static function export() {
        return [
            'version' => '1.0.0',
            'exported_at' => current_time('mysql'),
            'settings' => self::getAll()
        ];
    }
    
    /**
     * Import settings
     */
    public static function import($data) {
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return false;
        }
        
        $validated = self::validate($data['settings']);
        update_option('money_quiz_security_settings', $validated);
        
        do_action('money_quiz_security_settings_imported', $validated);
        
        return true;
    }
}