<?php
/**
 * Integration Default Configuration
 * 
 * Default settings for Money Quiz integration features
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

return [
    
    /**
     * Modern Implementation Rollout
     * 
     * Percentage of requests that use modern implementations
     * Start low (10%) and gradually increase as you verify stability
     */
    'modern_rollout' => 10,
    
    /**
     * Safety Features
     * 
     * Enable/disable specific safety features
     */
    'safety_features' => [
        'query_protection' => true,      // Database query protection
        'input_sanitization' => true,    // Automatic input sanitization
        'csrf_protection' => true,       // CSRF token validation
        'error_logging' => true,         // Enhanced error logging
        'performance_monitoring' => true // Performance tracking
    ],
    
    /**
     * Function Feature Flags
     * 
     * Control which functions use modern implementations
     * Start with low-risk functions enabled
     */
    'function_flags' => [
        // Low risk - safe to enable immediately
        'mq_get_quiz_questions' => true,
        'mq_calculate_archetype' => true,
        'mq_get_archetypes' => true,
        'mq_get_setting' => true,
        
        // Medium risk - enable after testing
        'mq_save_quiz_result' => false,
        'mq_send_result_email' => false,
        'mq_get_prospects' => false,
        'mq_export_prospects' => false,
        
        // High risk - enable last
        'mq_process_quiz' => false,
        'mq_save_prospect' => false,
        'mq_delete_prospect' => false,
        'mq_save_settings' => false
    ],
    
    /**
     * Database Migration Settings
     */
    'database_migration' => [
        'auto_migrate' => false,         // Auto-migrate queries on detection
        'log_unsafe_queries' => true,    // Log unsafe queries for review
        'block_unsafe_queries' => false, // Block execution of unsafe queries
        'migration_batch_size' => 50     // Number of queries to migrate at once
    ],
    
    /**
     * Error Handling Settings
     */
    'error_handling' => [
        'display_errors' => false,       // Display errors to users
        'log_errors' => true,           // Log errors to file
        'email_critical' => true,       // Email admin on critical errors
        'error_log_days' => 30,         // Days to keep error logs
        'max_log_size' => 10485760      // Max log file size (10MB)
    ],
    
    /**
     * Performance Settings
     */
    'performance' => [
        'enable_caching' => true,        // Enable query caching
        'cache_ttl' => 3600,            // Cache TTL in seconds
        'slow_query_threshold' => 1.0,   // Slow query threshold in seconds
        'memory_limit_warning' => 0.8,   // Warn at 80% memory usage
        'track_metrics' => true         // Track performance metrics
    ],
    
    /**
     * Security Settings
     */
    'security' => [
        'csrf_token_lifetime' => 14400,  // CSRF token lifetime (4 hours)
        'max_csrf_tokens' => 20,         // Max tokens per session
        'rate_limit_enabled' => true,    // Enable rate limiting
        'rate_limit_requests' => 60,     // Max requests per minute
        'block_suspicious_ips' => false, // Auto-block suspicious IPs
        'log_security_events' => true   // Log security events
    ],
    
    /**
     * Monitoring Settings
     */
    'monitoring' => [
        'health_check_interval' => 300,  // Health check interval (5 min)
        'alert_threshold' => [
            'error_rate' => 10,          // Errors per hour
            'response_time' => 3.0,      // Seconds
            'memory_usage' => 0.9,       // 90% of limit
            'failed_queries' => 5        // Failed queries per hour
        ],
        'status_retention_days' => 7     // Days to keep status data
    ],
    
    /**
     * Integration Modes
     */
    'integration_mode' => 'hybrid',      // 'modern', 'legacy', or 'hybrid'
    
    /**
     * Development Settings
     */
    'development' => [
        'verbose_logging' => false,      // Verbose debug logging
        'show_query_analysis' => false,  // Show query analysis in footer
        'bypass_cache' => false,         // Bypass all caching
        'force_modern' => false,         // Force modern implementations
        'test_mode' => false            // Enable test mode features
    ]
];