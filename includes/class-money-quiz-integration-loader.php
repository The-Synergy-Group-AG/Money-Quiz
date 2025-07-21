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
        try {
            // Check if WordPress is fully loaded
            if (!function_exists('plugin_dir_path') || !function_exists('plugin_dir_url') || !function_exists('plugin_basename')) {
                error_log('MoneyQuiz: WordPress not fully loaded, deferring integration loader');
                return;
            }
            
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
        } catch (Exception $e) {
            // Log error but don't crash the plugin
            error_log('MoneyQuiz Integration Loader Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if cycle file dependencies exist
     */
    private static function check_cycle_dependencies($cycle_path) {
        $required_files = [
            'mvc-implementation/worker-1-core-plugin-class.php',
            'mvc-implementation/worker-2-controllers.php',
            'service-layer/worker-4-database-service.php',
            'data-models/worker-7-core-models.php',
            'utilities/worker-9-utilities-helpers.php'
        ];
        
        foreach ($required_files as $file) {
            if (!file_exists($cycle_path . $file)) {
                error_log('MoneyQuiz: Required cycle file missing: ' . $cycle_path . $file);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Load Cycle 3 - Architecture Transformation
     */
    private static function load_cycle_3($base_path) {
        $cycle_path = $base_path . 'cycle-3-architecture-transformation/';
        
        if (file_exists($cycle_path)) {
            // Check dependencies before loading
            if (!self::check_cycle_dependencies($cycle_path)) {
                error_log('MoneyQuiz: Cycle 3 dependencies missing, skipping load');
                return;
            }
            
            // MVC Implementation
            self::safe_require_once($cycle_path . 'mvc-implementation/worker-1-core-plugin-class.php');
            self::safe_require_once($cycle_path . 'mvc-implementation/worker-2-controllers.php');
            self::safe_require_once($cycle_path . 'mvc-implementation/worker-3-api-controller.php');
            self::safe_require_once($cycle_path . 'mvc-implementation/worker-3-quiz-controller.php');
            
            // Service Layer
            self::safe_require_once($cycle_path . 'service-layer/worker-4-database-service.php');
            self::safe_require_once($cycle_path . 'service-layer/worker-5-email-service.php');
            self::safe_require_once($cycle_path . 'service-layer/worker-6-quiz-validation-services.php');
            
            // Data Models
            self::safe_require_once($cycle_path . 'data-models/worker-7-core-models.php');
            self::safe_require_once($cycle_path . 'data-models/worker-8-additional-models.php');
            
            // Utilities
            self::safe_require_once($cycle_path . 'utilities/worker-9-utilities-helpers.php');
            
            // Integration (only if all dependencies exist)
            self::safe_require_once($cycle_path . 'integration/worker-10-component-integration.php');
        }
    }
    
    /**
     * Load Cycle 4 - Modern Features
     */
    private static function load_cycle_4($base_path) {
        $cycle_path = $base_path . 'cycle-4-modern-features/';
        
        if (file_exists($cycle_path)) {
            // AI Integration
            self::safe_require_once($cycle_path . 'ai-integration/worker-1-ai-service.php');
            self::safe_require_once($cycle_path . 'ai-integration/worker-2-ai-providers.php');
            
            // Analytics Dashboard
            self::safe_require_once($cycle_path . 'analytics-dashboard/worker-3-analytics-service.php');
            self::safe_require_once($cycle_path . 'analytics-dashboard/worker-4-analytics-dashboard.php');
            
            // Other Features
            self::safe_require_once($cycle_path . 'multi-language/worker-5-translation-service.php');
            self::safe_require_once($cycle_path . 'ab-testing/worker-6-ab-testing-service.php');
            self::safe_require_once($cycle_path . 'webhooks/worker-7-webhook-service.php');
            self::safe_require_once($cycle_path . 'personalization/worker-8-personalization-service.php');
            self::safe_require_once($cycle_path . 'notifications/worker-9-notification-service.php');
            self::safe_require_once($cycle_path . 'data-portability/worker-10-export-import-service.php');
        }
    }
    
    /**
     * Load Cycle 5 - Performance
     */
    private static function load_cycle_5($base_path) {
        $cycle_path = $base_path . 'cycle-5-performance/';
        
        if (file_exists($cycle_path)) {
            // Database Optimization
            self::safe_require_once($cycle_path . 'database-optimization/worker-1-query-optimizer.php');
            self::safe_require_once($cycle_path . 'database-optimization/query-profiler.php');
            
            // Caching
            self::safe_require_once($cycle_path . 'caching/worker-2-cache-manager.php');
            self::safe_require_once($cycle_path . 'caching/page-cache-system.php');
            self::safe_require_once($cycle_path . 'caching/fragment-cache.php');
            self::safe_require_once($cycle_path . 'caching/object-cache-integration.php');
            
            // Background Jobs
            self::safe_require_once($cycle_path . 'background-jobs/queue-system.php');
            self::safe_require_once($cycle_path . 'background-jobs/async-task-processor.php');
            self::safe_require_once($cycle_path . 'background-jobs/worker-pool-manager.php');
            
            // API Performance
            self::safe_require_once($cycle_path . 'api/graphql-implementation.php');
            self::safe_require_once($cycle_path . 'api/request-batching.php');
            self::safe_require_once($cycle_path . 'api/response-compression.php');
        }
    }
    
    /**
     * Load Cycle 6 - Security
     */
    private static function load_cycle_6($base_path) {
        $cycle_path = $base_path . 'cycle-6-security/';
        
        if (file_exists($cycle_path)) {
            // Main security loader
            self::safe_require_once($cycle_path . 'security-loader.php');
            
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
            self::safe_require_once($cycle_path . 'rest-api/api-10-loader.php');
            
            // React Admin
            self::safe_require_once($cycle_path . 'react-admin/react-8-loader.php');
            
            // Analytics
            self::safe_require_once($cycle_path . 'analytics/analytics-6-loader.php');
            
            // Documentation
            self::safe_require_once($cycle_path . 'documentation/docs-5-loader.php');
            
            // Testing
            self::safe_require_once($cycle_path . 'testing/test-6-loader.php');
            
            // Webhooks
            self::safe_require_once($cycle_path . 'webhooks/webhook-5-loader.php');
        }
    }
    
    /**
     * Load Cycle 10 - AI Optimization
     */
    private static function load_cycle_10($base_path) {
        $cycle_path = $base_path . 'cycle-10-ai-optimization/';
        
        if (file_exists($cycle_path)) {
            // AI Core
            self::safe_require_once($cycle_path . 'ai-core/ai-3-core-loader.php');
            
            // ML Recommendations
            self::safe_require_once($cycle_path . 'ml-recommendations/ml-4-loader.php');
            
            // Predictive Analytics
            self::safe_require_once($cycle_path . 'predictive-analytics/pred-4-loader.php');
            
            // NLP Processing
            self::safe_require_once($cycle_path . 'nlp-processing/nlp-3-loader.php');
            
            // Smart Caching
            self::safe_require_once($cycle_path . 'smart-caching/cache-3-loader.php');
            
            // Performance Tuning
            self::safe_require_once($cycle_path . 'performance-tuning/perf-3-loader.php');
            
            // ML Training
            self::safe_require_once($cycle_path . 'ml-training/train-3-loader.php');
            
            // AI Insights
            self::safe_require_once($cycle_path . 'ai-insights/insights-3-loader.php');
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
    
    /**
     * Safely require a file without crashing if it doesn't exist
     */
    private static function safe_require_once($file_path) {
        if (file_exists($file_path)) {
            try {
                // Check if WordPress functions are available before loading cycle files
                if (strpos($file_path, 'cycle-') !== false) {
                    if (!function_exists('plugin_dir_path') || !function_exists('plugin_dir_url') || !function_exists('plugin_basename')) {
                        error_log('MoneyQuiz: WordPress functions not available, skipping cycle file: ' . $file_path);
                        return;
                    }
                    
                    // Additional check for cycle files - verify all dependencies exist
                    if (strpos($file_path, 'worker-10-component-integration.php') !== false) {
                        $autoloader_path = dirname($file_path) . '/includes/class-autoloader.php';
                        if (!file_exists($autoloader_path)) {
                            error_log('MoneyQuiz: Required autoloader missing, skipping component integration: ' . $autoloader_path);
                            return;
                        }
                    }
                }
                
                require_once $file_path;
            } catch (Exception $e) {
                error_log('MoneyQuiz: Failed to load file ' . $file_path . ': ' . $e->getMessage());
            } catch (Error $e) {
                error_log('MoneyQuiz: Fatal error loading file ' . $file_path . ': ' . $e->getMessage());
            }
        } else {
            error_log('MoneyQuiz: File does not exist: ' . $file_path);
        }
    }
}