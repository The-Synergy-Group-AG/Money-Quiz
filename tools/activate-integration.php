#!/usr/bin/env php
<?php
/**
 * Integration Activation Script
 * 
 * Activates the Money Quiz integration features with safe defaults
 * 
 * Usage: php activate-integration.php
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

// Bootstrap WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/usr/local/www/wp-load.php'
];

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
    if ( file_exists( $path ) ) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if ( ! $wp_loaded ) {
    die( "Error: Could not find wp-load.php. Please run from plugin directory.\n" );
}

echo "Money Quiz Integration Activation\n";
echo "=================================\n\n";

// Check if plugin is active
if ( ! defined( 'MONEY_QUIZ_PLUGIN_FILE' ) ) {
    die( "Error: Money Quiz plugin is not active.\n" );
}

// Step 1: Set initial configuration
echo "Setting initial configuration...\n";

// Set safe rollout percentage
update_option( 'money_quiz_modern_rollout', 10 );
echo "✓ Modern rollout set to 10%\n";

// Enable safety features
update_option( 'money_quiz_enable_query_protection', true );
echo "✓ Database query protection enabled\n";

update_option( 'money_quiz_enable_input_sanitization', true );
echo "✓ Input sanitization enabled\n";

update_option( 'money_quiz_enable_csrf_protection', true );
echo "✓ CSRF protection enabled\n";

// Set function flags (only low-risk functions)
$function_flags = [
    'mq_get_quiz_questions' => true,
    'mq_calculate_archetype' => true,
    'mq_get_archetypes' => true,
    'mq_get_setting' => true,
    'mq_save_quiz_result' => false,
    'mq_send_result_email' => false,
    'mq_get_prospects' => false,
    'mq_process_quiz' => false,
    'mq_save_prospect' => false,
    'mq_delete_prospect' => false
];

update_option( 'money_quiz_feature_flags', $function_flags );
echo "✓ Function flags configured (low-risk functions enabled)\n";

// Step 2: Initialize integration components
echo "\nInitializing integration components...\n";

// Load integration class
require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/class-legacy-integration.php';

if ( class_exists( '\MoneyQuiz\Integration\Legacy_Integration' ) ) {
    $integration = \MoneyQuiz\Integration\Legacy_Integration::instance();
    $integration->init();
    echo "✓ Legacy integration initialized\n";
}

// Load admin settings page
require_once MONEY_QUIZ_PLUGIN_DIR . 'includes/admin/class-integration-settings.php';

if ( class_exists( '\MoneyQuiz\Admin\Integration_Settings' ) ) {
    $settings = new \MoneyQuiz\Admin\Integration_Settings();
    $settings->init();
    echo "✓ Integration settings page registered\n";
}

// Step 3: Create necessary directories
echo "\nCreating necessary directories...\n";

$directories = [
    WP_CONTENT_DIR . '/money-quiz-logs',
    WP_CONTENT_DIR . '/money-quiz-cache',
    WP_CONTENT_DIR . '/money-quiz-temp'
];

foreach ( $directories as $dir ) {
    if ( ! file_exists( $dir ) ) {
        if ( wp_mkdir_p( $dir ) ) {
            echo "✓ Created directory: " . basename( $dir ) . "\n";
            
            // Add .htaccess for security
            file_put_contents( $dir . '/.htaccess', 'Deny from all' );
            file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
        } else {
            echo "✗ Failed to create directory: " . basename( $dir ) . "\n";
        }
    } else {
        echo "✓ Directory exists: " . basename( $dir ) . "\n";
    }
}

// Step 4: Run initial checks
echo "\nRunning initial checks...\n";

// Check error logging
if ( defined( 'MONEY_QUIZ_ERROR_LOGGING' ) && MONEY_QUIZ_ERROR_LOGGING ) {
    echo "✓ Error logging is enabled\n";
} else {
    echo "⚠ Error logging is not enabled. Add MONEY_QUIZ_ERROR_LOGGING to wp-config.php\n";
}

// Check WordPress debug mode
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    echo "✓ WordPress debug mode is enabled\n";
} else {
    echo "⚠ WordPress debug mode is disabled. Consider enabling for testing\n";
}

// Test database wrapper
if ( function_exists( 'mq_safe_db' ) ) {
    $db_wrapper = mq_safe_db();
    echo "✓ Database safety wrapper is available\n";
} else {
    echo "✗ Database safety wrapper not found\n";
}

// Test input sanitizer
if ( function_exists( 'mq_sanitize_input' ) ) {
    echo "✓ Input sanitizer is available\n";
} else {
    echo "✗ Input sanitizer not found\n";
}

// Step 5: Display summary
echo "\n";
echo str_repeat( '=', 50 ) . "\n";
echo "Activation Complete!\n";
echo str_repeat( '=', 50 ) . "\n\n";

echo "Next steps:\n";
echo "1. Add the configuration lines from wp-config-additions.txt to your wp-config.php\n";
echo "2. Visit the WordPress admin dashboard\n";
echo "3. Navigate to Money Quiz → Integration\n";
echo "4. Monitor the integration status\n";
echo "5. Run: php " . __DIR__ . "/test-integration.php --verbose\n\n";

echo "Integration Settings URL: " . admin_url( 'admin.php?page=moneyquiz-integration' ) . "\n\n";

// Clear any caches
wp_cache_flush();
echo "✓ Cache cleared\n\n";

echo "Integration is now active with safe defaults!\n";