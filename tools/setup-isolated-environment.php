<?php
/**
 * Setup Isolated Environment
 * 
 * Quick setup script for isolated testing environment
 * Run via WP-CLI: wp eval-file tools/setup-isolated-environment.php
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    die('This script must be run within WordPress');
}

echo "Setting up Money Quiz for Isolated Environment...\n\n";

// 1. Set all feature flags to 100%
$flags = [
    'modern_quiz_display' => 1.0,
    'modern_quiz_list' => 1.0,
    'modern_archetype_fetch' => 1.0,
    'modern_statistics' => 1.0,
    'modern_quiz_submit' => 1.0,
    'modern_prospect_save' => 1.0,
    'modern_email_send' => 1.0
];

update_option('mq_feature_flags', $flags);
echo "✓ Feature flags set to 100%\n";

// 2. Enable isolated environment mode
update_option('mq_isolated_environment', true);
echo "✓ Isolated environment mode enabled\n";

// 3. Disable unnecessary features
$disable_options = [
    'mq_enable_email_tracking' => false,
    'mq_enable_user_tracking' => false,
    'mq_enable_analytics' => false,
    'mq_enable_legacy_compatibility' => false,
    'mq_send_admin_notifications' => false,
    'mq_enable_gradual_rollout' => false
];

foreach ($disable_options as $option => $value) {
    update_option($option, $value);
}
echo "✓ Unnecessary features disabled\n";

// 4. Set migration week to 1 (but with 100% traffic)
update_option('mq_migration_start_date', current_time('mysql'));
update_option('mq_hybrid_week', 1);
echo "✓ Migration tracking initialized\n";

// 5. Clear any rollback flags
delete_transient('mq_emergency_rollback');
delete_transient('mq_rollback_cooldown');
echo "✓ Rollback flags cleared\n";

// 6. Create test quiz if none exist
$quiz_count = wp_count_posts('mq_quiz')->publish;
if ($quiz_count == 0) {
    $quiz_id = wp_insert_post([
        'post_title' => 'Test Quiz - Isolated Environment',
        'post_type' => 'mq_quiz',
        'post_status' => 'publish',
        'post_content' => 'This is a test quiz for isolated environment testing.'
    ]);
    
    if ($quiz_id) {
        // Add sample questions
        update_post_meta($quiz_id, '_mq_questions', [
            [
                'question' => 'How do you prefer to manage money?',
                'answers' => [
                    'Save everything possible',
                    'Balance saving and spending',
                    'Enjoy life now',
                    'Invest for growth'
                ]
            ],
            [
                'question' => 'What is your biggest financial goal?',
                'answers' => [
                    'Financial security',
                    'Early retirement',
                    'Travel the world',
                    'Start a business'
                ]
            ]
        ]);
        
        echo "✓ Test quiz created (ID: $quiz_id)\n";
    }
}

// 7. Flush rewrite rules
flush_rewrite_rules();
echo "✓ Rewrite rules flushed\n";

// 8. Clear caches
wp_cache_flush();
echo "✓ Caches cleared\n";

echo "\n";
echo "========================================\n";
echo "Isolated Environment Setup Complete!\n";
echo "========================================\n";
echo "\n";
echo "Configuration Summary:\n";
echo "- All traffic routing to modern system (100%)\n";
echo "- Email tracking disabled\n";
echo "- User tracking disabled\n";
echo "- Legacy compatibility disabled\n";
echo "- Admin notifications disabled\n";
echo "\n";
echo "Access your site at: " . home_url() . "\n";
echo "Admin panel: " . admin_url() . "\n";
echo "\n";
echo "To verify setup, check:\n";
echo "- Routing Control: " . admin_url('admin.php?page=mq-routing-control') . "\n";
echo "- Settings: " . admin_url('admin.php?page=money-quiz-settings') . "\n";
echo "\n";