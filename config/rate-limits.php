<?php
/**
 * Rate Limiting Configuration
 *
 * Defines rate limiting rules for API endpoints.
 *
 * @package MoneyQuiz
 * @since   7.0.0
 */

// Security: Prevent direct access.
defined('ABSPATH') || exit;

return [
    /**
     * Default rate limit profiles.
     */
    'profiles' => [
        'default' => [
            'requests' => 60,
            'window' => 60, // 1 minute
            'description' => 'Standard rate limit for general API usage'
        ],
        'strict' => [
            'requests' => 10,
            'window' => 60, // 1 minute
            'description' => 'Strict limit for sensitive operations'
        ],
        'relaxed' => [
            'requests' => 300,
            'window' => 60, // 1 minute
            'description' => 'Relaxed limit for read-only operations'
        ],
        'auth' => [
            'requests' => 5,
            'window' => 300, // 5 minutes
            'description' => 'Authentication and security endpoints'
        ],
        'admin' => [
            'requests' => 1000,
            'window' => 60, // 1 minute
            'description' => 'Admin users have higher limits'
        ]
    ],
    
    /**
     * Endpoint-specific configurations.
     */
    'endpoints' => [
        'start_attempt' => [
            'profile' => 'default',
            'custom' => null // Use profile settings
        ],
        'submit_answers' => [
            'profile' => 'strict',
            'custom' => null
        ],
        'complete_attempt' => [
            'profile' => 'strict',
            'custom' => null
        ],
        'get_result' => [
            'profile' => 'relaxed',
            'custom' => null
        ],
        'get_user_results' => [
            'profile' => 'relaxed',
            'custom' => null
        ],
        'get_archetypes' => [
            'profile' => 'relaxed',
            'custom' => null
        ],
        'get_archetype' => [
            'profile' => 'relaxed',
            'custom' => null
        ]
    ],
    
    /**
     * User type overrides.
     */
    'user_overrides' => [
        'administrator' => 'admin',
        'editor' => 'relaxed',
        'subscriber' => 'default',
        'anonymous' => 'strict'
    ],
    
    /**
     * IP-based exceptions.
     */
    'ip_exceptions' => [
        // Add trusted IPs that bypass rate limiting
        // '127.0.0.1',
        // '::1'
    ],
    
    /**
     * Response headers configuration.
     */
    'headers' => [
        'enabled' => true,
        'prefix' => 'X-RateLimit-'
    ],
    
    /**
     * Storage configuration.
     */
    'storage' => [
        'driver' => 'transient', // 'transient', 'cache', 'database'
        'prefix' => 'money_quiz_rate_limit_',
        'cleanup_probability' => 0.01 // 1% chance on each request
    ]
];