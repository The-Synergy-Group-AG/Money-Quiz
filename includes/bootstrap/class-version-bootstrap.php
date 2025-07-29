<?php
/**
 * Version Bootstrap - Early version reconciliation initialization
 * 
 * This class runs very early in the plugin lifecycle to ensure
 * version consistency before any other systems initialize.
 * 
 * @package MoneyQuiz
 * @subpackage Bootstrap
 * @since 4.0.0
 */

namespace MoneyQuiz\Bootstrap;

use MoneyQuiz\Version\VersionManager;
use MoneyQuiz\Version\VersionReconciliationInit;

if (!defined('ABSPATH')) {
    exit;
}

class VersionBootstrap {
    
    /**
     * @var bool Whether version check has been performed
     */
    private static $checked = false;
    
    /**
     * @var array Version check results
     */
    private static $results = [];
    
    /**
     * Run version bootstrap
     * 
     * @return bool True if versions are consistent, false otherwise
     */
    public static function run() {
        if (self::$checked) {
            return self::$results['consistent'] ?? false;
        }
        
        self::$checked = true;
        
        try {
            // Load version management classes
            self::load_version_classes();
            
            // Quick version check
            $quick_check = self::quick_version_check();
            
            if (!$quick_check['needs_reconciliation']) {
                self::$results = [
                    'consistent' => true,
                    'version' => $quick_check['version']
                ];
                return true;
            }
            
            // Full reconciliation needed
            self::$results = self::perform_reconciliation();
            
            return self::$results['consistent'] ?? false;
            
        } catch (\Exception $e) {
            error_log('Money Quiz Version Bootstrap Error: ' . $e->getMessage());
            self::$results = [
                'consistent' => false,
                'error' => $e->getMessage()
            ];
            return false;
        }
    }
    
    /**
     * Load required version management classes
     */
    private static function load_version_classes() {
        $base_path = MONEY_QUIZ_PLUGIN_DIR . 'includes/version/';
        
        $required_files = [
            'class-version-manager.php',
            'class-version-migration.php',
            'class-database-version-tracker.php',
            'class-version-consistency-checker.php',
            'class-version-reconciliation-init.php'
        ];
        
        foreach ($required_files as $file) {
            $path = $base_path . $file;
            if (file_exists($path)) {
                require_once $path;
            } else {
                throw new \RuntimeException("Required version file missing: $file");
            }
        }
    }
    
    /**
     * Perform quick version check
     * 
     * @return array
     */
    private static function quick_version_check() {
        // Check stored version
        $stored_version = get_option('money_quiz_version');
        $plugin_version = MONEY_QUIZ_VERSION;
        
        // Check if this is first run
        if (!$stored_version) {
            return [
                'needs_reconciliation' => true,
                'reason' => 'First run - no stored version'
            ];
        }
        
        // Check if versions match
        if ($stored_version !== $plugin_version) {
            return [
                'needs_reconciliation' => true,
                'reason' => 'Version mismatch',
                'stored' => $stored_version,
                'plugin' => $plugin_version
            ];
        }
        
        // Check last reconciliation
        $last_check = get_option('mq_last_version_check', 0);
        $check_interval = 86400; // 24 hours
        
        if ((time() - $last_check) > $check_interval) {
            return [
                'needs_reconciliation' => true,
                'reason' => 'Periodic check needed'
            ];
        }
        
        return [
            'needs_reconciliation' => false,
            'version' => $stored_version
        ];
    }
    
    /**
     * Perform full reconciliation
     * 
     * @return array
     */
    private static function perform_reconciliation() {
        $init = new VersionReconciliationInit();
        $init->init();
        
        $manager = $init->get_manager();
        
        // Detect versions
        $versions = $manager->detect_all_versions();
        
        // Check for mismatches
        $mismatches = $manager->identify_mismatches($versions);
        
        if (empty($mismatches)) {
            // Update last check time
            update_option('mq_last_version_check', time());
            
            return [
                'consistent' => true,
                'versions' => $versions
            ];
        }
        
        // Auto-reconcile if safe
        if (self::is_safe_to_auto_reconcile($mismatches)) {
            $plan = $manager->create_reconciliation_plan($versions, '4.0.0');
            $result = $manager->execute_reconciliation($plan);
            
            if ($result['success']) {
                update_option('money_quiz_version', '4.0.0');
                update_option('mq_last_version_check', time());
                
                return [
                    'consistent' => true,
                    'reconciled' => true,
                    'versions' => $result['versions']
                ];
            }
        }
        
        // Manual reconciliation needed
        self::flag_for_admin_attention($mismatches);
        
        return [
            'consistent' => false,
            'mismatches' => $mismatches,
            'manual_required' => true
        ];
    }
    
    /**
     * Check if auto-reconciliation is safe
     * 
     * @param array $mismatches
     * @return bool
     */
    private static function is_safe_to_auto_reconcile($mismatches) {
        // Don't auto-reconcile critical mismatches
        foreach ($mismatches as $mismatch) {
            if ($mismatch['severity'] === 'critical') {
                return false;
            }
        }
        
        // Don't auto-reconcile in production without admin consent
        if (defined('WP_ENV') && WP_ENV === 'production') {
            $auto_reconcile = get_option('mq_auto_reconcile_enabled', false);
            return $auto_reconcile;
        }
        
        return true;
    }
    
    /**
     * Flag issues for admin attention
     * 
     * @param array $mismatches
     */
    private static function flag_for_admin_attention($mismatches) {
        set_transient('mq_version_mismatches', $mismatches, HOUR_IN_SECONDS);
        
        // Add admin notice
        add_action('admin_notices', function() use ($mismatches) {
            $critical_count = 0;
            foreach ($mismatches as $mismatch) {
                if ($mismatch['severity'] === 'critical') {
                    $critical_count++;
                }
            }
            
            $class = $critical_count > 0 ? 'notice-error' : 'notice-warning';
            $message = sprintf(
                'Money Quiz version inconsistencies detected. %s',
                '<a href="' . admin_url('admin.php?page=money-quiz-version-management') . '">Review and fix</a>'
            );
            
            printf('<div class="notice %s"><p>%s</p></div>', $class, $message);
        });
    }
    
    /**
     * Get bootstrap results
     * 
     * @return array
     */
    public static function get_results() {
        return self::$results;
    }
    
    /**
     * Force version check
     * 
     * @return bool
     */
    public static function force_check() {
        self::$checked = false;
        delete_option('mq_last_version_check');
        return self::run();
    }
}

// Run bootstrap immediately if not in CLI
if (!defined('WP_CLI') || !WP_CLI) {
    add_action('plugins_loaded', [VersionBootstrap::class, 'run'], 1);
}