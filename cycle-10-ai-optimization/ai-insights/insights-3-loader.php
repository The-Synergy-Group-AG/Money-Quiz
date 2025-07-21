<?php
/**
 * AI Insights Loader
 * 
 * @package MoneyQuiz\AI\Insights
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Insights;

require_once __DIR__ . '/insights-1-collector.php';
require_once __DIR__ . '/insights-2-dashboard.php';

/**
 * Insights Manager
 */
class AIInsightsManager {
    
    private static $instance = null;
    private $collector;
    private $dashboard;
    
    private function __construct() {
        $this->collector = InsightsCollector::getInstance();
        $this->dashboard = InsightsDashboard::getInstance();
        $this->dashboard->setCollector($this->collector);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function init() {
        $instance = self::getInstance();
        
        // Register AI features
        $instance->registerFeatures();
        
        // Admin menu
        add_action('admin_menu', [$instance, 'addAdminPage']);
        
        // REST endpoints
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
        
        // Dashboard assets
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_money_quiz_refresh_insights', [$instance, 'ajaxRefreshInsights']);
    }
    
    private function registerFeatures() {
        $features = [
            'pattern_recognition' => 'Pattern Recognition',
            'recommendations' => 'ML Recommendations',
            'predictive_analytics' => 'Predictive Analytics',
            'nlp_processing' => 'NLP Processing',
            'smart_caching' => 'Smart Caching',
            'performance_tuning' => 'Performance Tuning',
            'ml_training' => 'ML Training'
        ];
        
        foreach ($features as $key => $name) {
            $this->collector->registerFeature($key, null);
        }
    }
    
    public function addAdminPage() {
        add_menu_page(
            'AI Insights',
            'AI Insights',
            'manage_options',
            'money-quiz-ai',
            [$this, 'renderDashboard'],
            'dashicons-chart-line',
            30
        );
    }
    
    public function renderDashboard() {
        $this->dashboard->render();
    }
    
    public function enqueueAssets($hook) {
        if ($hook !== 'toplevel_page_money-quiz-ai') {
            return;
        }
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Custom dashboard styles
        wp_add_inline_style('wp-admin', '
            .ai-insights-charts { margin-top: 30px; }
            .ai-insights-charts .card { padding: 20px; }
            .ai-insights-charts canvas { max-height: 300px; }
        ');
    }
    
    public function registerEndpoints() {
        register_rest_route('money-quiz/v1', '/ai-insights', [
            'methods' => 'GET',
            'callback' => [$this, 'getInsights'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('money-quiz/v1', '/ai-insights/feature/(?P<feature>\w+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getFeatureInsights'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    public function getInsights() {
        return $this->collector->collectAllInsights();
    }
    
    public function getFeatureInsights($request) {
        $feature = $request->get_param('feature');
        return $this->collector->getInsightsByFeature($feature);
    }
    
    public function ajaxRefreshInsights() {
        check_ajax_referer('money_quiz_ai_nonce');
        
        $insights = $this->collector->collectAllInsights();
        
        wp_send_json_success($insights);
    }
    
    public static function getQuickStats() {
        $instance = self::getInstance();
        return $instance->collector->getInsightsSummary();
    }
}

// Initialize
add_action('plugins_loaded', [AIInsightsManager::class, 'init']);