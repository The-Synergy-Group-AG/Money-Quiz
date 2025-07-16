<?php
/**
 * Hooks and Filters Registry for Enhanced Features
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Hooks_Registry {
    
    /**
     * Register all hooks and filters
     */
    public static function register() {
        // Core WordPress hooks
        add_action('init', [__CLASS__, 'init_features'], 5);
        add_action('plugins_loaded', [__CLASS__, 'load_textdomain']);
        add_action('admin_init', [__CLASS__, 'admin_init']);
        add_action('admin_menu', [__CLASS__, 'admin_menus'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts']);
        
        // REST API
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        
        // AJAX handlers
        add_action('wp_ajax_money_quiz_analytics', [__CLASS__, 'handle_analytics_ajax']);
        add_action('wp_ajax_nopriv_money_quiz_analytics', [__CLASS__, 'handle_analytics_ajax']);
        
        // Cron jobs
        add_action('money_quiz_hourly_cron', [__CLASS__, 'hourly_cron_tasks']);
        add_action('money_quiz_daily_cron', [__CLASS__, 'daily_cron_tasks']);
        
        // Custom filters
        add_filter('money_quiz_ai_providers', [__CLASS__, 'register_ai_providers']);
        add_filter('money_quiz_cache_strategies', [__CLASS__, 'register_cache_strategies']);
        add_filter('money_quiz_security_rules', [__CLASS__, 'register_security_rules']);
        
        // Schedule cron events
        self::schedule_cron_events();
    }
    
    /**
     * Initialize features
     */
    public static function init_features() {
        // Initialize services if they exist
        if (class_exists('MoneyQuiz\\Services\\DatabaseService')) {
            MoneyQuiz\Services\DatabaseService::getInstance();
        }
        
        if (class_exists('MoneyQuiz\\AI\\AIService')) {
            MoneyQuiz\AI\AIService::getInstance();
        }
        
        if (class_exists('MoneyQuiz\\Analytics\\AnalyticsService')) {
            MoneyQuiz\Analytics\AnalyticsService::getInstance();
        }
        
        // Register custom post types if needed
        self::register_custom_post_types();
        
        // Initialize security features
        if (class_exists('MoneyQuizSecurityManager')) {
            MoneyQuizSecurityManager::getInstance();
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public static function load_textdomain() {
        load_plugin_textdomain(
            'money-quiz',
            false,
            dirname(plugin_basename(dirname(__FILE__))) . '/languages/'
        );
    }
    
    /**
     * Admin initialization
     */
    public static function admin_init() {
        // Register settings
        register_setting('money_quiz_settings', 'money_quiz_ai_enabled');
        register_setting('money_quiz_settings', 'money_quiz_cache_enabled');
        register_setting('money_quiz_settings', 'money_quiz_analytics_enabled');
        register_setting('money_quiz_settings', 'money_quiz_security_level');
        
        // Add capabilities
        self::add_capabilities();
    }
    
    /**
     * Register admin menus
     */
    public static function admin_menus() {
        // Main menu is already added by original plugin
        // Add submenus for new features
        
        if (current_user_can('manage_options')) {
            // AI Dashboard submenu
            add_submenu_page(
                'moneyquiz',
                __('AI Dashboard', 'money-quiz'),
                __('AI Dashboard', 'money-quiz'),
                'manage_options',
                'money-quiz-ai',
                [__CLASS__, 'render_ai_dashboard']
            );
            
            // Performance submenu
            add_submenu_page(
                'moneyquiz',
                __('Performance', 'money-quiz'),
                __('Performance', 'money-quiz'),
                'manage_options',
                'money-quiz-performance',
                [__CLASS__, 'render_performance_dashboard']
            );
            
            // Security submenu
            add_submenu_page(
                'moneyquiz',
                __('Security', 'money-quiz'),
                __('Security', 'money-quiz'),
                'manage_options',
                'money-quiz-security',
                [__CLASS__, 'render_security_dashboard']
            );
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public static function enqueue_scripts() {
        // Performance optimized loading
        if (get_option('money_quiz_performance_enabled', true)) {
            wp_enqueue_script(
                'money-quiz-performance',
                plugins_url('assets/js/performance.js', dirname(__FILE__)),
                ['jquery'],
                '1.0.0',
                true
            );
        }
        
        // AI features
        if (get_option('money_quiz_ai_enabled', true)) {
            wp_localize_script('money-quiz-performance', 'moneyQuizAI', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('money_quiz_ai_nonce')
            ]);
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'money-quiz') !== false) {
            // Analytics dashboard
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js', [], '3.9.1');
            
            // React for admin interface
            if (strpos($hook, 'money-quiz-admin') !== false) {
                wp_enqueue_script(
                    'money-quiz-react-admin',
                    plugins_url('assets/js/react-admin.js', dirname(__FILE__)),
                    ['wp-element'],
                    '1.0.0',
                    true
                );
            }
        }
    }
    
    /**
     * Register REST routes
     */
    public static function register_rest_routes() {
        // Let individual modules register their own routes
        do_action('money_quiz_register_rest_routes');
    }
    
    /**
     * Handle analytics AJAX
     */
    public static function handle_analytics_ajax() {
        check_ajax_referer('money_quiz_ai_nonce', 'nonce');
        
        if (class_exists('MoneyQuiz\\Analytics\\AnalyticsService')) {
            $analytics = MoneyQuiz\Analytics\AnalyticsService::getInstance();
            $data = $analytics->getAnalyticsData($_POST);
            wp_send_json_success($data);
        } else {
            wp_send_json_error('Analytics service not available');
        }
    }
    
    /**
     * Schedule cron events
     */
    private static function schedule_cron_events() {
        if (!wp_next_scheduled('money_quiz_hourly_cron')) {
            wp_schedule_event(time(), 'hourly', 'money_quiz_hourly_cron');
        }
        
        if (!wp_next_scheduled('money_quiz_daily_cron')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_daily_cron');
        }
    }
    
    /**
     * Hourly cron tasks
     */
    public static function hourly_cron_tasks() {
        // Cache cleanup
        do_action('money_quiz_cache_cleanup');
        
        // Performance monitoring
        do_action('money_quiz_performance_check');
    }
    
    /**
     * Daily cron tasks
     */
    public static function daily_cron_tasks() {
        // AI model training
        do_action('money_quiz_ml_training');
        
        // Security scan
        do_action('money_quiz_security_scan');
        
        // Analytics aggregation
        do_action('money_quiz_analytics_aggregate');
    }
    
    /**
     * Register AI providers
     */
    public static function register_ai_providers($providers) {
        $providers['openai'] = 'MoneyQuiz\\AI\\Providers\\OpenAIProvider';
        $providers['claude'] = 'MoneyQuiz\\AI\\Providers\\ClaudeProvider';
        $providers['grok'] = 'MoneyQuiz\\AI\\Providers\\GrokProvider';
        return $providers;
    }
    
    /**
     * Register cache strategies
     */
    public static function register_cache_strategies($strategies) {
        $strategies['page'] = 'MoneyQuiz\\Cache\\PageCacheStrategy';
        $strategies['fragment'] = 'MoneyQuiz\\Cache\\FragmentCacheStrategy';
        $strategies['object'] = 'MoneyQuiz\\Cache\\ObjectCacheStrategy';
        $strategies['cdn'] = 'MoneyQuiz\\Cache\\CDNStrategy';
        return $strategies;
    }
    
    /**
     * Register security rules
     */
    public static function register_security_rules($rules) {
        $rules['csrf'] = 'MoneyQuiz\\Security\\CSRFProtection';
        $rules['xss'] = 'MoneyQuiz\\Security\\XSSProtection';
        $rules['sql'] = 'MoneyQuiz\\Security\\SQLInjectionProtection';
        $rules['rate_limit'] = 'MoneyQuiz\\Security\\RateLimiting';
        return $rules;
    }
    
    /**
     * Register custom post types
     */
    private static function register_custom_post_types() {
        // Register if needed for new features
    }
    
    /**
     * Add capabilities
     */
    private static function add_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('money_quiz_manage_ai');
            $role->add_cap('money_quiz_view_analytics');
            $role->add_cap('money_quiz_manage_security');
        }
    }
    
    /**
     * Render AI dashboard
     */
    public static function render_ai_dashboard() {
        if (class_exists('MoneyQuiz\\AI\\Insights\\AIInsightsManager')) {
            $insights = MoneyQuiz\AI\Insights\AIInsightsManager::getInstance();
            $insights->renderDashboard();
        } else {
            echo '<div class="wrap"><h1>AI Dashboard</h1><p>AI features are being loaded...</p></div>';
        }
    }
    
    /**
     * Render performance dashboard
     */
    public static function render_performance_dashboard() {
        echo '<div class="wrap"><h1>Performance Dashboard</h1>';
        do_action('money_quiz_render_performance_dashboard');
        echo '</div>';
    }
    
    /**
     * Render security dashboard
     */
    public static function render_security_dashboard() {
        if (class_exists('MoneyQuizSecurityAdmin')) {
            MoneyQuizSecurityAdmin::renderDashboard();
        } else {
            echo '<div class="wrap"><h1>Security Dashboard</h1><p>Security features are being loaded...</p></div>';
        }
    }
}