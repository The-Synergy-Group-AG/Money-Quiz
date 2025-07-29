<?php
/**
 * Isolated Environment Configuration
 * 
 * This configuration file is for isolated/development environments
 * where 100% traffic routing to modern system is safe.
 * 
 * @package MoneyQuiz
 * @subpackage Config
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define isolated environment flag
if (!defined('MONEY_QUIZ_ISOLATED_ENV')) {
    define('MONEY_QUIZ_ISOLATED_ENV', true);
}

// Override feature flags for isolated environment
add_filter('mq_default_feature_flags', function($flags) {
    if (MONEY_QUIZ_ISOLATED_ENV) {
        return [
            'modern_quiz_display' => 1.0,
            'modern_quiz_list' => 1.0,
            'modern_archetype_fetch' => 1.0,
            'modern_statistics' => 1.0,
            'modern_quiz_submit' => 1.0,
            'modern_prospect_save' => 1.0,
            'modern_email_send' => 1.0
        ];
    }
    return $flags;
});

// Override week configurations for isolated environment
add_filter('mq_week_configs', function($configs) {
    if (MONEY_QUIZ_ISOLATED_ENV) {
        // All weeks at 100% for isolated environment
        return [
            1 => array_fill_keys(['modern_quiz_display', 'modern_quiz_list', 'modern_archetype_fetch', 'modern_statistics', 'modern_quiz_submit', 'modern_prospect_save', 'modern_email_send'], 1.0),
            2 => array_fill_keys(['modern_quiz_display', 'modern_quiz_list', 'modern_archetype_fetch', 'modern_statistics', 'modern_quiz_submit', 'modern_prospect_save', 'modern_email_send'], 1.0),
            3 => array_fill_keys(['modern_quiz_display', 'modern_quiz_list', 'modern_archetype_fetch', 'modern_statistics', 'modern_quiz_submit', 'modern_prospect_save', 'modern_email_send'], 1.0),
            4 => array_fill_keys(['modern_quiz_display', 'modern_quiz_list', 'modern_archetype_fetch', 'modern_statistics', 'modern_quiz_submit', 'modern_prospect_save', 'modern_email_send'], 1.0)
        ];
    }
    return $configs;
});

// Disable gradual rollout for isolated environment
add_filter('mq_enable_gradual_rollout', function($enabled) {
    if (MONEY_QUIZ_ISOLATED_ENV) {
        return false;
    }
    return $enabled;
});

// Log isolated environment mode
add_action('init', function() {
    if (MONEY_QUIZ_ISOLATED_ENV && current_user_can('manage_options')) {
        error_log('Money Quiz: Running in ISOLATED ENVIRONMENT mode - 100% traffic to modern system');
    }
});