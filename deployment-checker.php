<?php
/**
 * Automated Deployment Checker for MoneyQuiz Plugin
 * 
 * This script should be run during deployment to ensure all dependencies
 * are properly installed and the plugin is ready for production.
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('DEPLOYMENT_CHECK')) {
    define('DEPLOYMENT_CHECK', true);
}

class Money_Quiz_Deployment_Checker {
    
    private $errors = [];
    private $warnings = [];
    private $success = [];
    
    /**
     * Run all deployment checks
     */
    public static function run_checks() {
        $checker = new self();
        
        echo "🔍 MoneyQuiz Deployment Checker\n";
        echo "================================\n\n";
        
        // Critical checks
        $checker->check_composer_dependencies();
        $checker->check_critical_files();
        $checker->check_php_version();
        $checker->check_wp_version();
        $checker->check_permissions();
        $checker->check_database_tables();
        $checker->check_plugin_integrity();
        
        // Display results
        $checker->display_results();
        
        // Return exit code
        return empty($checker->errors) ? 0 : 1;
    }
    
    /**
     * Check Composer dependencies
     */
    private function check_composer_dependencies() {
        echo "Checking Composer dependencies...\n";
        
        $plugin_dir = dirname(__FILE__);
        
        // Check composer.json exists
        if (!file_exists($plugin_dir . '/composer.json')) {
            $this->errors[] = 'composer.json not found';
            return;
        }
        
        // Check vendor directory exists
        if (!is_dir($plugin_dir . '/vendor')) {
            $this->errors[] = 'vendor directory not found - run composer install';
            return;
        }
        
        // Check autoloader exists
        if (!file_exists($plugin_dir . '/vendor/autoload.php')) {
            $this->errors[] = 'Composer autoloader not found';
            return;
        }
        
        // Check composer.lock exists
        if (!file_exists($plugin_dir . '/composer.lock')) {
            $this->warnings[] = 'composer.lock not found - dependencies may be inconsistent';
        }
        
        $this->success[] = 'Composer dependencies are properly installed';
    }
    
    /**
     * Check critical files
     */
    private function check_critical_files() {
        echo "Checking critical files...\n";
        
        $plugin_dir = dirname(__FILE__);
        $critical_files = [
            'moneyquiz.php',
            'class.moneyquiz.php',
            'includes/class-money-quiz-integration-loader.php',
            'includes/class-money-quiz-service-container.php',
            'includes/class-money-quiz-hooks-registry.php',
            'includes/class-money-quiz-dependency-checker.php',
            'includes/functions.php'
        ];
        
        foreach ($critical_files as $file) {
            if (!file_exists($plugin_dir . '/' . $file)) {
                $this->errors[] = "Critical file missing: $file";
            }
        }
        
        if (empty($this->errors)) {
            $this->success[] = 'All critical files are present';
        }
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        echo "Checking PHP version...\n";
        
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->errors[] = 'PHP version ' . PHP_VERSION . ' is below minimum required (7.4.0)';
        } else {
            $this->success[] = 'PHP version ' . PHP_VERSION . ' is compatible';
        }
    }
    
    /**
     * Check WordPress version
     */
    private function check_wp_version() {
        echo "Checking WordPress version...\n";
        
        if (defined('ABSPATH')) {
            global $wp_version;
            if (version_compare($wp_version, '5.0.0', '<')) {
                $this->warnings[] = 'WordPress version ' . $wp_version . ' may not support all features (recommended: 5.0.0+)';
            } else {
                $this->success[] = 'WordPress version ' . $wp_version . ' is compatible';
            }
        } else {
            $this->warnings[] = 'WordPress not detected - running in standalone mode';
        }
    }
    
    /**
     * Check file permissions
     */
    private function check_permissions() {
        echo "Checking file permissions...\n";
        
        $plugin_dir = dirname(__FILE__);
        
        // Check if plugin directory is readable
        if (!is_readable($plugin_dir)) {
            $this->errors[] = 'Plugin directory is not readable';
        }
        
        // Check if vendor directory is readable
        if (!is_readable($plugin_dir . '/vendor')) {
            $this->errors[] = 'Vendor directory is not readable';
        }
        
        // Check if logs directory is writable (if it exists)
        $logs_dir = $plugin_dir . '/logs';
        if (is_dir($logs_dir) && !is_writable($logs_dir)) {
            $this->warnings[] = 'Logs directory is not writable';
        }
        
        $this->success[] = 'File permissions are acceptable';
    }
    
    /**
     * Check database tables
     */
    private function check_database_tables() {
        echo "Checking database tables...\n";
        
        if (!defined('ABSPATH')) {
            $this->warnings[] = 'Cannot check database tables - WordPress not loaded';
            return;
        }
        
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'mq_master',
            $wpdb->prefix . 'mq_prospects',
            $wpdb->prefix . 'mq_taken',
            $wpdb->prefix . 'mq_results',
            $wpdb->prefix . 'mq_coach',
            $wpdb->prefix . 'mq_archetypes',
            $wpdb->prefix . 'mq_cta',
            $wpdb->prefix . 'mq_template_layout'
        ];
        
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            if (!$table_exists) {
                $this->warnings[] = "Database table missing: $table";
            }
        }
        
        if (empty($this->warnings)) {
            $this->success[] = 'All required database tables are present';
        }
    }
    
    /**
     * Check plugin integrity
     */
    private function check_plugin_integrity() {
        echo "Checking plugin integrity...\n";
        
        // Check for syntax errors in main files
        $main_files = [
            'moneyquiz.php',
            'class.moneyquiz.php',
            'includes/class-money-quiz-integration-loader.php',
            'includes/class-money-quiz-service-container.php',
            'includes/class-money-quiz-hooks-registry.php',
            'includes/class-money-quiz-dependency-checker.php'
        ];
        
        $plugin_dir = dirname(__FILE__);
        
        foreach ($main_files as $file) {
            $file_path = $plugin_dir . '/' . $file;
            if (file_exists($file_path)) {
                $output = [];
                $return_var = 0;
                exec("php -l " . escapeshellarg($file_path) . " 2>&1", $output, $return_var);
                
                if ($return_var !== 0) {
                    $this->errors[] = "Syntax error in $file: " . implode("\n", $output);
                }
            }
        }
        
        if (empty($this->errors)) {
            $this->success[] = 'All PHP files have valid syntax';
        }
    }
    
    /**
     * Display results
     */
    private function display_results() {
        echo "\n📊 Deployment Check Results\n";
        echo "==========================\n\n";
        
        if (!empty($this->success)) {
            echo "✅ Success:\n";
            foreach ($this->success as $message) {
                echo "   • $message\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  Warnings:\n";
            foreach ($this->warnings as $message) {
                echo "   • $message\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "❌ Errors:\n";
            foreach ($this->errors as $message) {
                echo "   • $message\n";
            }
            echo "\n";
        }
        
        // Summary
        $total_checks = count($this->success) + count($this->warnings) + count($this->errors);
        echo "Summary: " . count($this->success) . " passed, " . count($this->warnings) . " warnings, " . count($this->errors) . " errors\n\n";
        
        if (empty($this->errors)) {
            echo "🎉 Deployment check PASSED! Plugin is ready for production.\n";
        } else {
            echo "🚨 Deployment check FAILED! Please fix the errors above before deploying.\n";
        }
    }
    
    /**
     * Generate deployment report
     */
    public static function generate_report() {
        $checker = new self();
        
        // Run checks silently
        ob_start();
        $checker->check_composer_dependencies();
        $checker->check_critical_files();
        $checker->check_php_version();
        $checker->check_wp_version();
        $checker->check_permissions();
        $checker->check_database_tables();
        $checker->check_plugin_integrity();
        ob_end_clean();
        
        return [
            'success' => $checker->success,
            'warnings' => $checker->warnings,
            'errors' => $checker->errors,
            'timestamp' => current_time('mysql'),
            'plugin_version' => defined('MONEYQUIZ_VERSION') ? MONEYQUIZ_VERSION : 'Unknown'
        ];
    }
}

// Run checks if called directly
if (defined('DEPLOYMENT_CHECK') && DEPLOYMENT_CHECK) {
    exit(Money_Quiz_Deployment_Checker::run_checks());
} 