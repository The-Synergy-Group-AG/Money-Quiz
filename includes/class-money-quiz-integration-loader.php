<?php
/**
 * Integration Loader for Enhanced Features
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Integration_Loader {
    
    /**
     * Load all enhanced features
     */
    public static function load_features() {
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        
        // Cycle 3 - Architecture Transformation
        self::load_cycle_3($plugin_path);
        
        // Cycle 4 - Modern Features
        self::load_cycle_4($plugin_path);
        
        // Cycle 5 - Performance
        self::load_cycle_5($plugin_path);
        
        // Cycle 6 - Security
        self::load_cycle_6($plugin_path);
        
        // Cycle 7 - Enhancements
        self::load_cycle_7($plugin_path);
        
        // Cycle 10 - AI Optimization
        self::load_cycle_10($plugin_path);
    }
    
    /**
     * Load Cycle 3 - Architecture Transformation
     */
    private static function load_cycle_3($base_path) {
        $cycle_path = $base_path . 'cycle-3-architecture-transformation/';
        
        if (file_exists($cycle_path)) {
            // MVC Implementation
            require_once $cycle_path . 'mvc-implementation/worker-1-core-plugin-class.php';
            require_once $cycle_path . 'mvc-implementation/worker-2-controllers.php';
            require_once $cycle_path . 'mvc-implementation/worker-3-api-controller.php';
            require_once $cycle_path . 'mvc-implementation/worker-3-quiz-controller.php';
            
            // Service Layer
            require_once $cycle_path . 'service-layer/worker-4-database-service.php';
            require_once $cycle_path . 'service-layer/worker-5-email-service.php';
            require_once $cycle_path . 'service-layer/worker-6-quiz-validation-services.php';
            
            // Data Models
            require_once $cycle_path . 'data-models/worker-7-core-models.php';
            require_once $cycle_path . 'data-models/worker-8-additional-models.php';
            
            // Utilities
            require_once $cycle_path . 'utilities/worker-9-utilities-helpers.php';
            
            // Integration
            require_once $cycle_path . 'integration/worker-10-component-integration.php';
        }
    }
    
    /**
     * Load Cycle 4 - Modern Features
     */
    private static function load_cycle_4($base_path) {
        $cycle_path = $base_path . 'cycle-4-modern-features/';
        
        if (file_exists($cycle_path)) {
            // AI Integration
            require_once $cycle_path . 'ai-integration/worker-1-ai-service.php';
            require_once $cycle_path . 'ai-integration/worker-2-ai-providers.php';
            
            // Analytics Dashboard
            require_once $cycle_path . 'analytics-dashboard/worker-3-analytics-service.php';
            require_once $cycle_path . 'analytics-dashboard/worker-4-analytics-dashboard.php';
            
            // Other Features
            require_once $cycle_path . 'multi-language/worker-5-translation-service.php';
            require_once $cycle_path . 'ab-testing/worker-6-ab-testing-service.php';
            require_once $cycle_path . 'webhooks/worker-7-webhook-service.php';
            require_once $cycle_path . 'personalization/worker-8-personalization-service.php';
            require_once $cycle_path . 'notifications/worker-9-notification-service.php';
            require_once $cycle_path . 'data-portability/worker-10-export-import-service.php';
        }
    }
    
    /**
     * Load Cycle 5 - Performance
     */
    private static function load_cycle_5($base_path) {
        $cycle_path = $base_path . 'cycle-5-performance/';
        
        if (file_exists($cycle_path)) {
            // Database Optimization
            require_once $cycle_path . 'database-optimization/worker-1-query-optimizer.php';
            require_once $cycle_path . 'database-optimization/query-profiler.php';
            
            // Caching
            require_once $cycle_path . 'caching/worker-2-cache-manager.php';
            require_once $cycle_path . 'caching/page-cache-system.php';
            require_once $cycle_path . 'caching/fragment-cache.php';
            require_once $cycle_path . 'caching/object-cache-integration.php';
            
            // Background Jobs
            require_once $cycle_path . 'background-jobs/queue-system.php';
            require_once $cycle_path . 'background-jobs/async-task-processor.php';
            require_once $cycle_path . 'background-jobs/worker-pool-manager.php';
            
            // API Performance
            require_once $cycle_path . 'api/graphql-implementation.php';
            require_once $cycle_path . 'api/request-batching.php';
            require_once $cycle_path . 'api/response-compression.php';
        }
    }
    
    /**
     * Load Cycle 6 - Security
     */
    private static function load_cycle_6($base_path) {
        $cycle_path = $base_path . 'cycle-6-security/';
        
        if (file_exists($cycle_path)) {
            // Main security loader
            require_once $cycle_path . 'security-loader.php';
            
            // This will handle loading all security submodules
            if (class_exists('MoneyQuizSecurityLoader')) {
                MoneyQuizSecurityLoader::init();
            }
        }
    }
    
    /**
     * Load Cycle 7 - Enhancements
     */
    private static function load_cycle_7($base_path) {
        $cycle_path = $base_path . 'cycle-7-enhancements/';
        
        if (file_exists($cycle_path)) {
            // REST API
            require_once $cycle_path . 'rest-api/api-10-loader.php';
            
            // React Admin
            require_once $cycle_path . 'react-admin/react-8-loader.php';
            
            // Analytics
            require_once $cycle_path . 'analytics/analytics-6-loader.php';
            
            // Documentation
            require_once $cycle_path . 'documentation/docs-5-loader.php';
            
            // Testing
            require_once $cycle_path . 'testing/test-6-loader.php';
            
            // Webhooks
            require_once $cycle_path . 'webhooks/webhook-5-loader.php';
        }
    }
    
    /**
     * Load Cycle 10 - AI Optimization
     */
    private static function load_cycle_10($base_path) {
        $cycle_path = $base_path . 'cycle-10-ai-optimization/';
        
        if (file_exists($cycle_path)) {
            // AI Core
            require_once $cycle_path . 'ai-core/ai-3-core-loader.php';
            
            // ML Recommendations
            require_once $cycle_path . 'ml-recommendations/ml-4-loader.php';
            
            // Predictive Analytics
            require_once $cycle_path . 'predictive-analytics/pred-4-loader.php';
            
            // NLP Processing
            require_once $cycle_path . 'nlp-processing/nlp-3-loader.php';
            
            // Smart Caching
            require_once $cycle_path . 'smart-caching/cache-3-loader.php';
            
            // Performance Tuning
            require_once $cycle_path . 'performance-tuning/perf-3-loader.php';
            
            // ML Training
            require_once $cycle_path . 'ml-training/train-3-loader.php';
            
            // AI Insights
            require_once $cycle_path . 'ai-insights/insights-3-loader.php';
        }
    }
    
    /**
     * Check if features exist
     */
    public static function check_features() {
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        $status = [];
        
        $cycles = [
            'cycle-3-architecture-transformation',
            'cycle-4-modern-features',
            'cycle-5-performance',
            'cycle-6-security',
            'cycle-7-enhancements',
            'cycle-10-ai-optimization'
        ];
        
        foreach ($cycles as $cycle) {
            $status[$cycle] = is_dir($plugin_path . $cycle);
        }
        
        return $status;
    }
}