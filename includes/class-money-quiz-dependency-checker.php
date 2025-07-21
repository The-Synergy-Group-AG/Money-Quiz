<?php
/**
 * Dependency Checker for MoneyQuiz Plugin
 * 
 * Monitors critical dependencies and displays admin notices for missing components
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Dependency_Checker {
    
    /**
     * Initialize the dependency checker
     */
    public static function init() {
        add_action('admin_notices', [__CLASS__, 'check_dependencies']);
        add_action('admin_init', [__CLASS__, 'dismiss_notice']);
        add_action('wp_ajax_money_quiz_dismiss_dependency_notice', [__CLASS__, 'ajax_dismiss_notice']);
        add_action('wp_ajax_money_quiz_run_composer', [__CLASS__, 'ajax_run_composer']);
    }
    
    /**
     * Check all critical dependencies
     */
    public static function check_dependencies() {
        $issues = self::get_dependency_issues();
        
        if (!empty($issues)) {
            self::display_admin_notices($issues);
        }
    }
    
    /**
     * Get all dependency issues
     */
    public static function get_dependency_issues() {
        // Check cache first for performance
        $cached_issues = get_transient('moneyquiz_dependency_issues');
        if ($cached_issues !== false) {
            return $cached_issues;
        }
        
        $issues = [];
        
        // Check Composer autoloader
        if (!self::check_composer_autoloader()) {
            $issues[] = [
                'type' => 'critical',
                'title' => 'Missing Composer Autoloader',
                'message' => 'The Composer autoloader is missing. This will cause critical errors. Please run <code>composer install</code> in the plugin directory.',
                'action' => 'composer_install'
            ];
        }
        
        // Check vendor directory
        if (!self::check_vendor_directory()) {
            $issues[] = [
                'type' => 'critical',
                'title' => 'Missing Vendor Directory',
                'message' => 'The vendor directory is missing. Dependencies are not installed. Please run <code>composer install</code> in the plugin directory.',
                'action' => 'composer_install'
            ];
        }
        
        // Check critical files
        $missing_files = self::check_critical_files();
        if (!empty($missing_files)) {
            $issues[] = [
                'type' => 'warning',
                'title' => 'Missing Critical Files',
                'message' => 'Some critical plugin files are missing: <code>' . implode('</code>, <code>', $missing_files) . '</code>. Please reinstall the plugin.',
                'action' => 'reinstall_plugin'
            ];
        }
        
        // Check PHP version
        if (!self::check_php_version()) {
            $issues[] = [
                'type' => 'warning',
                'title' => 'PHP Version Issue',
                'message' => 'Your PHP version (' . PHP_VERSION . ') may not be compatible with all plugin features. Recommended: PHP 7.4 or higher.',
                'action' => 'upgrade_php'
            ];
        }
        
        // Check WordPress version
        if (!self::check_wp_version()) {
            $issues[] = [
                'type' => 'warning',
                'title' => 'WordPress Version Issue',
                'message' => 'Your WordPress version may not be compatible with all plugin features. Recommended: WordPress 5.0 or higher.',
                'action' => 'upgrade_wordpress'
            ];
        }
        
        // Cache results for 5 minutes to improve performance
        set_transient('moneyquiz_dependency_issues', $issues, 5 * 60);
        
        return $issues;
    }
    
    /**
     * Check if Composer autoloader exists
     */
    private static function check_composer_autoloader() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        return file_exists($plugin_dir . 'vendor/autoload.php');
    }
    
    /**
     * Check if vendor directory exists
     */
    private static function check_vendor_directory() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        return is_dir($plugin_dir . 'vendor');
    }
    
    /**
     * Check critical files
     */
    private static function check_critical_files() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $critical_files = [
            'moneyquiz.php',
            'class.moneyquiz.php',
            'includes/class-money-quiz-integration-loader.php',
            'includes/class-money-quiz-service-container.php',
            'includes/class-money-quiz-hooks-registry.php'
        ];
        
        $missing_files = [];
        foreach ($critical_files as $file) {
            if (!file_exists($plugin_dir . $file)) {
                $missing_files[] = $file;
            }
        }
        
        return $missing_files;
    }
    
    /**
     * Check PHP version
     */
    private static function check_php_version() {
        return version_compare(PHP_VERSION, '7.4.0', '>=');
    }
    
    /**
     * Check WordPress version
     */
    private static function check_wp_version() {
        global $wp_version;
        return version_compare($wp_version, '5.0.0', '>=');
    }
    
    /**
     * Display admin notices
     */
    private static function display_admin_notices($issues) {
        foreach ($issues as $issue) {
            $notice_id = 'money_quiz_' . sanitize_title($issue['title']);
            
            // Check if notice was dismissed
            if (get_transient('money_quiz_dismissed_' . $notice_id)) {
                continue;
            }
            
            $class = $issue['type'] === 'critical' ? 'error' : 'warning';
            $icon = $issue['type'] === 'critical' ? '🚨' : '⚠️';
            
            echo '<div class="notice notice-' . $class . ' is-dismissible" data-notice-id="' . $notice_id . '">';
            echo '<p><strong>' . $icon . ' MoneyQuiz: ' . esc_html($issue['title']) . '</strong></p>';
            echo '<p>' . wp_kses_post($issue['message']) . '</p>';
            
            if ($issue['action'] === 'composer_install') {
                echo '<p><a href="#" class="button button-primary money-quiz-run-composer">Run Composer Install</a> ';
                echo '<a href="#" class="button money-quiz-dismiss-notice" data-notice-id="' . $notice_id . '">Dismiss</a></p>';
            } else {
                echo '<p><a href="#" class="button money-quiz-dismiss-notice" data-notice-id="' . $notice_id . '">Dismiss</a></p>';
            }
            
            echo '</div>';
        }
        
        // Add JavaScript for dismiss functionality
        self::add_notice_script();
    }
    
    /**
     * Add JavaScript for notice functionality
     */
    private static function add_notice_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Dismiss notice
            $('.money-quiz-dismiss-notice').on('click', function(e) {
                e.preventDefault();
                var noticeId = $(this).data('notice-id');
                var $notice = $(this).closest('.notice');
                
                $.post(ajaxurl, {
                    action: 'money_quiz_dismiss_dependency_notice',
                    notice_id: noticeId,
                    nonce: '<?php echo wp_create_nonce('money_quiz_dismiss_notice'); ?>'
                }, function() {
                    $notice.fadeOut();
                });
            });
            
            // Run composer install
            $('.money-quiz-run-composer').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                $button.text('Running...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'money_quiz_run_composer',
                    nonce: '<?php echo wp_create_nonce('money_quiz_run_composer'); ?>'
                }, function(response) {
                    if (response.success) {
                        $button.text('Success!').addClass('button-secondary');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $button.text('Failed').addClass('button-secondary');
                        alert('Failed to run composer install. Please run it manually.');
                    }
                }).fail(function() {
                    $button.text('Failed').addClass('button-secondary');
                    alert('Failed to run composer install. Please run it manually.');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle notice dismissal
     */
    public static function dismiss_notice() {
        if (isset($_GET['money_quiz_dismiss']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'money_quiz_dismiss_notice')) {
                $notice_id = sanitize_text_field($_GET['money_quiz_dismiss']);
                set_transient('money_quiz_dismissed_' . $notice_id, true, WEEK_IN_SECONDS);
            }
        }
    }
    
    /**
     * AJAX handler for dismissing notices
     */
    public static function ajax_dismiss_notice() {
        check_ajax_referer('money_quiz_dismiss_notice', 'nonce');
        
        $notice_id = sanitize_text_field($_POST['notice_id']);
        set_transient('money_quiz_dismissed_' . $notice_id, true, WEEK_IN_SECONDS);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for running composer install
     */
    public static function ajax_run_composer() {
        check_ajax_referer('money_quiz_run_composer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $composer_path = $plugin_dir . 'composer.json';
        
        if (!file_exists($composer_path)) {
            wp_send_json_error('Composer.json not found');
        }
        
        // Try to run composer install
        $output = [];
        $return_var = 0;
        
        exec('cd ' . escapeshellarg($plugin_dir) . ' && composer install --no-dev --optimize-autoloader 2>&1', $output, $return_var);
        
        if ($return_var === 0) {
            wp_send_json_success(['message' => 'Composer install completed successfully']);
        } else {
            wp_send_json_error(['message' => 'Composer install failed: ' . implode("\n", $output)]);
        }
    }
    
    /**
     * Get system information for debugging
     */
    public static function get_system_info() {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('MONEYQUIZ_VERSION') ? MONEYQUIZ_VERSION : 'Unknown',
            'vendor_exists' => self::check_vendor_directory(),
            'autoloader_exists' => self::check_composer_autoloader(),
            'critical_files_missing' => self::check_critical_files(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }
    
    /**
     * Check if the critical failure prevention system is active
     * 
     * @return bool
     */
    public static function is_system_active() {
        $issues = self::get_dependency_issues();
        
        // Filter out only critical issues
        $critical_issues = array_filter($issues, function($issue) {
            return $issue['type'] === 'critical';
        });
        
        // System is active if no critical issues exist
        return empty($critical_issues);
    }
    
    /**
     * Get system status for admin display
     */
    public static function get_system_status() {
        $is_active = self::is_system_active();
        $issues = self::get_dependency_issues();
        
        return [
            'active' => $is_active,
            'status' => $is_active ? 'ACTIVE' : 'INACTIVE',
            'critical_issues' => count(array_filter($issues, function($issue) {
                return $issue['type'] === 'critical';
            })),
            'warning_issues' => count(array_filter($issues, function($issue) {
                return $issue['type'] === 'warning';
            })),
            'total_issues' => count($issues)
        ];
    }
} 