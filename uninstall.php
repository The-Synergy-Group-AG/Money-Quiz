<?php
/**
 * Money Quiz Uninstall
 *
 * Uninstalling Money Quiz deletes tables, options, and capabilities.
 *
 * @package MoneyQuiz
 * @since   1.0.0
 * @version 4.0.0
 * @author  The Synergy Group AG <Andre@thesynergygroup.ch>
 */

// Exit if accessed directly or not in uninstall context
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user capabilities
if (!current_user_can('activate_plugins')) {
    return;
}

// Load plugin file for access to functions
require_once plugin_dir_path(__FILE__) . 'money-quiz.php';

/**
 * Only remove ALL product data if MONEY_QUIZ_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if (defined('MONEY_QUIZ_REMOVE_ALL_DATA') && true === MONEY_QUIZ_REMOVE_ALL_DATA) {
    
    global $wpdb;
    
    // Define tables to drop
    $tables = array(
        // Modern tables
        $wpdb->prefix . 'mq_quizzes',
        $wpdb->prefix . 'mq_questions',
        $wpdb->prefix . 'mq_answers',
        $wpdb->prefix . 'mq_results',
        $wpdb->prefix . 'mq_prospects',
        $wpdb->prefix . 'mq_archetypes',
        $wpdb->prefix . 'mq_archetype_mappings',
        $wpdb->prefix . 'mq_emails',
        $wpdb->prefix . 'mq_email_logs',
        $wpdb->prefix . 'mq_integrations',
        $wpdb->prefix . 'mq_settings',
        
        // Routing system tables
        $wpdb->prefix . 'mq_routing_metrics',
        $wpdb->prefix . 'mq_rollback_events',
        $wpdb->prefix . 'mq_feature_assignments',
        
        // Legacy tables (if they exist)
        $wpdb->prefix . 'moneyquiz',
        $wpdb->prefix . 'moneyquiz_questions',
        $wpdb->prefix . 'moneyquiz_archetypes',
        $wpdb->prefix . 'moneyquiz_prospects',
        $wpdb->prefix . 'moneyquiz_emails',
        $wpdb->prefix . 'moneyquiz_integrations',
        $wpdb->prefix . 'moneyquiz_popup',
        $wpdb->prefix . 'moneyquiz_cta'
    );
    
    // Drop tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Delete options
    $options = array(
        // Core options
        'money_quiz_version',
        'money_quiz_db_version',
        'money_quiz_mode',
        'money_quiz_settings',
        
        // Feature flags
        'mq_feature_flags',
        'mq_feature_flag_percentages',
        'mq_feature_flag_log',
        'mq_feature_adoption_stats',
        
        // Routing options
        'mq_hybrid_routing_enabled',
        'mq_routing_stats',
        'mq_monitor_thresholds',
        'mq_rollback_config',
        'mq_rollback_reasons',
        'mq_rollback_recoveries',
        'mq_threshold_breaches',
        
        // Version management
        'mq_version_mismatches',
        'mq_last_version_check',
        'mq_migration_start_date',
        'mq_hybrid_week',
        'mq_hybrid_start_date',
        
        // Safe mode options
        'mq_safety_checks',
        'mq_quarantine_mode',
        'mq_security_log',
        
        // Isolated environment
        'mq_isolated_environment',
        'mq_enable_email_tracking',
        'mq_enable_user_tracking',
        'mq_enable_analytics',
        'mq_enable_legacy_compatibility',
        'mq_send_admin_notifications',
        'mq_enable_gradual_rollout',
        'mq_auto_reconcile_enabled',
        
        // Legacy options (if they exist)
        'moneyquiz_version',
        'moneyquiz_settings',
        'moneyquiz_emails',
        'moneyquiz_integrations'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Delete transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mq_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mq_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_money_quiz_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_money_quiz_%'");
    
    // Remove capabilities
    $role = get_role('administrator');
    if ($role) {
        $capabilities = array(
            'manage_money_quiz',
            'edit_money_quiz',
            'delete_money_quiz',
            'publish_money_quiz',
            'manage_money_quiz_settings'
        );
        
        foreach ($capabilities as $cap) {
            $role->remove_cap($cap);
        }
    }
    
    // Delete user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mq_%'");
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'money_quiz_%'");
    
    // Clear scheduled cron events
    $cron_events = array(
        'money_quiz_safety_check',
        'mq_check_routing_health',
        'mq_aggregate_metrics',
        'mq_cleanup_old_metrics',
        'mq_daily_cleanup',
        'mq_check_rollback_status',
        'mq_optimization_check'
    );
    
    foreach ($cron_events as $event) {
        $timestamp = wp_next_scheduled($event);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $event);
        }
    }
    
    // Clear any cached data
    wp_cache_flush();
    
    // Log uninstall completion
    error_log('Money Quiz plugin has been completely uninstalled. All data has been removed.');
    
} else {
    // If MONEY_QUIZ_REMOVE_ALL_DATA is not set, just deactivate without removing data
    error_log('Money Quiz plugin has been deactivated. Data has been preserved. To remove all data, set MONEY_QUIZ_REMOVE_ALL_DATA to true in wp-config.php before uninstalling.');
}

// Clear rewrite rules
flush_rewrite_rules();