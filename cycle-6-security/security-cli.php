<?php
/**
 * Security CLI Commands
 * 
 * @package MoneyQuiz\Security
 * @version 1.0.0
 */

namespace MoneyQuiz\Security;

use WP_CLI;

/**
 * Security CLI Controller
 */
class SecurityCli {
    
    /**
     * Initialize CLI commands
     */
    public static function init() {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }
        
        WP_CLI::add_command('money-quiz security', __CLASS__);
    }
    
    /**
     * Check security status
     * 
     * ## EXAMPLES
     *     wp money-quiz security status
     */
    public function status($args, $assoc_args) {
        $manager = SecurityManager::getInstance();
        $status = $manager->getSecurityStatus();
        
        WP_CLI::line('Security Status:');
        WP_CLI::line('================');
        
        // General status
        WP_CLI::line(sprintf('Initialized: %s', $status['initialized'] ? 'Yes' : 'No'));
        WP_CLI::line(sprintf('Security Score: %d/100', $status['security_score']));
        WP_CLI::line(sprintf('Last Scan: %s', $status['last_scan'] ?: 'Never'));
        
        // Module status
        WP_CLI::line("\nActive Modules:");
        foreach ($status['modules'] as $module) {
            $enabled = SecurityConfig::get($module . '_enabled', true);
            WP_CLI::line(sprintf('- %s: %s', ucfirst($module), $enabled ? 'Enabled' : 'Disabled'));
        }
        
        // Environment
        WP_CLI::line("\nEnvironment:");
        WP_CLI::line(sprintf('PHP Version: %s', PHP_VERSION));
        WP_CLI::line(sprintf('SSL Enabled: %s', is_ssl() ? 'Yes' : 'No'));
        WP_CLI::line(sprintf('Debug Mode: %s', WP_DEBUG ? 'On' : 'Off'));
    }
    
    /**
     * Run security scan
     * 
     * ## OPTIONS
     * 
     * [--type=<type>]
     * : Type of scan to run (full, quick, dependencies)
     * default: full
     * 
     * ## EXAMPLES
     *     wp money-quiz security scan
     *     wp money-quiz security scan --type=dependencies
     */
    public function scan($args, $assoc_args) {
        $type = $assoc_args['type'] ?? 'full';
        
        WP_CLI::line('Starting security scan...');
        
        if (!function_exists('money_quiz_security_scan')) {
            WP_CLI::error('Security scanner not available');
            return;
        }
        
        $start_time = microtime(true);
        $results = money_quiz_security_scan();
        $duration = round(microtime(true) - $start_time, 2);
        
        if (!$results) {
            WP_CLI::error('Scan failed');
            return;
        }
        
        // Display results
        WP_CLI::success(sprintf('Scan completed in %s seconds', $duration));
        WP_CLI::line(sprintf('Security Score: %d/100', $results['security_score']));
        
        // Show issues by severity
        if (isset($results['dependency_scan']['summary'])) {
            $summary = $results['dependency_scan']['summary'];
            WP_CLI::line("\nDependency Issues:");
            WP_CLI::line(sprintf('- Critical: %d', $summary['critical'] ?? 0));
            WP_CLI::line(sprintf('- High: %d', $summary['high'] ?? 0));
            WP_CLI::line(sprintf('- Medium: %d', $summary['medium'] ?? 0));
            WP_CLI::line(sprintf('- Low: %d', $summary['low'] ?? 0));
        }
    }
    
    /**
     * Configure security settings
     * 
     * ## OPTIONS
     * 
     * <setting>
     * : Setting name to configure
     * 
     * <value>
     * : New value for the setting
     * 
     * ## EXAMPLES
     *     wp money-quiz security set force_ssl true
     *     wp money-quiz security set csrf_enabled false
     */
    public function set($args, $assoc_args) {
        list($setting, $value) = $args;
        
        // Convert string booleans
        if ($value === 'true') $value = true;
        if ($value === 'false') $value = false;
        
        SecurityConfig::set($setting, $value);
        
        WP_CLI::success(sprintf('Setting "%s" updated to: %s', $setting, json_encode($value)));
    }
    
    /**
     * Get security setting value
     * 
     * ## OPTIONS
     * 
     * <setting>
     * : Setting name to retrieve
     * 
     * ## EXAMPLES
     *     wp money-quiz security get force_ssl
     */
    public function get($args, $assoc_args) {
        $setting = $args[0];
        $value = SecurityConfig::get($setting);
        
        WP_CLI::line(sprintf('%s: %s', $setting, json_encode($value)));
    }
    
    /**
     * List all security settings
     * 
     * ## OPTIONS
     * 
     * [--format=<format>]
     * : Output format (table, json, csv)
     * default: table
     * 
     * ## EXAMPLES
     *     wp money-quiz security list
     *     wp money-quiz security list --format=json
     */
    public function list($args, $assoc_args) {
        $format = $assoc_args['format'] ?? 'table';
        $settings = SecurityConfig::getAll();
        
        $data = [];
        foreach ($settings as $key => $value) {
            $data[] = [
                'setting' => $key,
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => gettype($value)
            ];
        }
        
        WP_CLI\Utils\format_items($format, $data, ['setting', 'value', 'type']);
    }
    
    /**
     * Reset security settings to defaults
     * 
     * ## OPTIONS
     * 
     * [--yes]
     * : Skip confirmation prompt
     * 
     * ## EXAMPLES
     *     wp money-quiz security reset
     *     wp money-quiz security reset --yes
     */
    public function reset($args, $assoc_args) {
        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm('Are you sure you want to reset all security settings to defaults?');
        }
        
        SecurityConfig::resetToDefaults();
        
        WP_CLI::success('Security settings reset to defaults');
    }
    
    /**
     * Test security module
     * 
     * ## OPTIONS
     * 
     * <module>
     * : Module to test (csrf, xss, sql, rate_limit)
     * 
     * ## EXAMPLES
     *     wp money-quiz security test csrf
     *     wp money-quiz security test xss
     */
    public function test($args, $assoc_args) {
        $module = $args[0];
        
        WP_CLI::line(sprintf('Testing %s module...', strtoupper($module)));
        
        switch ($module) {
            case 'csrf':
                $this->testCsrf();
                break;
                
            case 'xss':
                $this->testXss();
                break;
                
            case 'sql':
                $this->testSql();
                break;
                
            case 'rate_limit':
                $this->testRateLimit();
                break;
                
            default:
                WP_CLI::error('Invalid module. Choose from: csrf, xss, sql, rate_limit');
        }
    }
    
    /**
     * Export security settings
     * 
     * ## OPTIONS
     * 
     * [--output=<file>]
     * : Output file path
     * 
     * ## EXAMPLES
     *     wp money-quiz security export
     *     wp money-quiz security export --output=security-config.json
     */
    public function export($args, $assoc_args) {
        $data = SecurityConfig::export();
        $output = $assoc_args['output'] ?? null;
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        if ($output) {
            file_put_contents($output, $json);
            WP_CLI::success(sprintf('Settings exported to: %s', $output));
        } else {
            WP_CLI::line($json);
        }
    }
    
    /**
     * Import security settings
     * 
     * ## OPTIONS
     * 
     * <file>
     * : Import file path
     * 
     * ## EXAMPLES
     *     wp money-quiz security import security-config.json
     */
    public function import($args, $assoc_args) {
        $file = $args[0];
        
        if (!file_exists($file)) {
            WP_CLI::error('Import file not found');
            return;
        }
        
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        
        if (!$data) {
            WP_CLI::error('Invalid JSON in import file');
            return;
        }
        
        if (SecurityConfig::import($data)) {
            WP_CLI::success('Settings imported successfully');
        } else {
            WP_CLI::error('Import failed');
        }
    }
    
    /**
     * Test CSRF protection
     */
    private function testCsrf() {
        if (!class_exists('MoneyQuiz\Security\CSRF\CsrfProtection')) {
            WP_CLI::error('CSRF protection not available');
            return;
        }
        
        $csrf = \MoneyQuiz\Security\CSRF\CsrfProtection::getInstance();
        $token = $csrf->generateToken('cli_test');
        
        WP_CLI::line('CSRF Test Results:');
        WP_CLI::line(sprintf('Token Generated: %s', $token ? 'Yes' : 'No'));
        WP_CLI::line(sprintf('Token Length: %d', strlen($token)));
        
        if ($csrf->verifyToken('cli_test', $token)) {
            WP_CLI::success('Token validation passed');
        } else {
            WP_CLI::error('Token validation failed');
        }
    }
    
    /**
     * Test XSS protection
     */
    private function testXss() {
        if (!class_exists('MoneyQuiz\Security\XSS\XssProtection')) {
            WP_CLI::error('XSS protection not available');
            return;
        }
        
        $xss = \MoneyQuiz\Security\XSS\XssProtection::getInstance();
        $test_cases = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert(1)>',
            'Normal text'
        ];
        
        WP_CLI::line('XSS Test Results:');
        foreach ($test_cases as $input) {
            $filtered = $xss->filterInput($input);
            $protected = ($input !== $filtered);
            
            WP_CLI::line(sprintf(
                'Input: "%s" => Output: "%s" [%s]',
                $input,
                $filtered,
                $protected ? 'Protected' : 'Unchanged'
            ));
        }
    }
    
    /**
     * Test SQL protection
     */
    private function testSql() {
        if (!class_exists('MoneyQuiz\Security\SQL\SqlValidator')) {
            WP_CLI::error('SQL protection not available');
            return;
        }
        
        $test_cases = [
            "' OR '1'='1",
            "1; DROP TABLE users",
            "normal input"
        ];
        
        WP_CLI::line('SQL Injection Test Results:');
        foreach ($test_cases as $input) {
            $valid = \MoneyQuiz\Security\SQL\SqlValidator::validate($input);
            
            WP_CLI::line(sprintf(
                'Input: "%s" => %s',
                $input,
                $valid ? 'Valid' : 'Blocked'
            ));
        }
    }
    
    /**
     * Test rate limiting
     */
    private function testRateLimit() {
        if (!class_exists('MoneyQuiz\Security\RateLimit\RateLimiter')) {
            WP_CLI::error('Rate limiting not available');
            return;
        }
        
        $limiter = \MoneyQuiz\Security\RateLimit\RateLimiter::getInstance();
        
        WP_CLI::line('Rate Limit Test:');
        
        for ($i = 1; $i <= 5; $i++) {
            $result = $limiter->checkLimit('cli_test');
            
            WP_CLI::line(sprintf(
                'Request %d: %s (Remaining: %d)',
                $i,
                $result['allowed'] ? 'Allowed' : 'Blocked',
                $result['remaining'] ?? 0
            ));
            
            if (!$result['allowed']) {
                break;
            }
        }
    }
}

// Initialize CLI commands
add_action('plugins_loaded', function() {
    if (defined('WP_CLI') && WP_CLI) {
        SecurityCli::init();
    }
});