<?php
/**
 * Version Constants - Unified version definitions
 * 
 * This file ensures all version references throughout the plugin
 * use consistent values. It should be included early in the
 * plugin lifecycle.
 * 
 * @package MoneyQuiz
 * @subpackage Version
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Primary version constant (already defined in main plugin file)
if (!defined('MONEY_QUIZ_VERSION')) {
    define('MONEY_QUIZ_VERSION', '4.0.0');
}

// Database schema version
if (!defined('MONEY_QUIZ_DB_VERSION')) {
    define('MONEY_QUIZ_DB_VERSION', '4.0.0');
}

// API version for external integrations
if (!defined('MONEY_QUIZ_API_VERSION')) {
    define('MONEY_QUIZ_API_VERSION', 'v1');
}

// Minimum required versions
if (!defined('MONEY_QUIZ_MIN_PHP_VERSION')) {
    define('MONEY_QUIZ_MIN_PHP_VERSION', '7.4');
}

if (!defined('MONEY_QUIZ_MIN_WP_VERSION')) {
    define('MONEY_QUIZ_MIN_WP_VERSION', '5.8');
}

// Version history for migration tracking
if (!defined('MONEY_QUIZ_VERSION_HISTORY')) {
    define('MONEY_QUIZ_VERSION_HISTORY', [
        '1.4.0' => 'legacy_original',
        '2.0.0' => 'legacy_enhanced',
        '3.3.0' => 'safe_wrapper_added',
        '4.0.0' => 'hybrid_progressive_migration'
    ]);
}

// Component versions (for granular tracking)
if (!defined('MONEY_QUIZ_COMPONENT_VERSIONS')) {
    define('MONEY_QUIZ_COMPONENT_VERSIONS', [
        'core' => '4.0.0',
        'admin' => '4.0.0',
        'frontend' => '4.0.0',
        'api' => '4.0.0',
        'database' => '4.0.0',
        'routing' => '1.5.0',
        'safe_wrapper' => '1.0.0'
    ]);
}

/**
 * Get unified version
 * 
 * @return string
 */
function mq_get_version() {
    return MONEY_QUIZ_VERSION;
}

/**
 * Get component version
 * 
 * @param string $component
 * @return string|null
 */
function mq_get_component_version($component) {
    return MONEY_QUIZ_COMPONENT_VERSIONS[$component] ?? null;
}

/**
 * Check if version meets minimum requirement
 * 
 * @param string $version
 * @param string $minimum
 * @return bool
 */
function mq_version_meets_requirement($version, $minimum) {
    return version_compare($version, $minimum, '>=');
}

/**
 * Get version migration path
 * 
 * @param string $from_version
 * @param string $to_version
 * @return array
 */
function mq_get_migration_path($from_version, $to_version = null) {
    if ($to_version === null) {
        $to_version = MONEY_QUIZ_VERSION;
    }
    
    $versions = array_keys(MONEY_QUIZ_VERSION_HISTORY);
    $from_index = array_search($from_version, $versions);
    $to_index = array_search($to_version, $versions);
    
    if ($from_index === false || $to_index === false) {
        return [];
    }
    
    return array_slice($versions, $from_index + 1, $to_index - $from_index);
}

// Ensure version option is set
add_action('init', function() {
    $stored_version = get_option('money_quiz_version');
    if ($stored_version !== MONEY_QUIZ_VERSION) {
        update_option('money_quiz_version', MONEY_QUIZ_VERSION);
        
        // Trigger version update action
        do_action('money_quiz_version_updated', $stored_version, MONEY_QUIZ_VERSION);
    }
}, 1);