<?php
/**
 * Service Container for Dependency Injection
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Service_Container {
    
    private static $instance = null;
    private $services = [];
    private $factories = [];
    private $initialized = [];
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a service factory
     */
    public function register($name, $factory) {
        $this->factories[$name] = $factory;
    }
    
    /**
     * Get a service
     */
    public function get($name) {
        if (!isset($this->services[$name])) {
            if (!isset($this->factories[$name])) {
                throw new Exception("Service '{$name}' not found");
            }
            
            $this->services[$name] = call_user_func($this->factories[$name], $this);
            $this->initialized[$name] = true;
        }
        
        return $this->services[$name];
    }
    
    /**
     * Check if service exists
     */
    public function has($name) {
        return isset($this->factories[$name]) || isset($this->services[$name]);
    }
    
    /**
     * Initialize all services
     */
    public function initialize() {
        try {
            // Register all service factories
            $this->registerCoreServices();
            $this->registerAIServices();
            $this->registerSecurityServices();
            $this->registerPerformanceServices();
            $this->registerEnhancementServices();
            
            // Initialize eager services
            $this->initializeEagerServices();
        } catch (Exception $e) {
            // Log error but don't crash the plugin
            error_log('MoneyQuiz Service Container Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Register core services
     */
    private function registerCoreServices() {
        // Database Service
        $this->register('database', function($container) {
            if (class_exists('MoneyQuiz\\Services\\DatabaseService')) {
                return MoneyQuiz\Services\DatabaseService::getInstance();
            }
            return null;
        });
        
        // Email Service
        $this->register('email', function($container) {
            if (class_exists('MoneyQuiz\\Services\\EmailService')) {
                return MoneyQuiz\Services\EmailService::getInstance();
            }
            return null;
        });
        
        // Validation Service
        $this->register('validation', function($container) {
            if (class_exists('MoneyQuiz\\Services\\ValidationService')) {
                return new MoneyQuiz\Services\ValidationService();
            }
            return null;
        });
        
        // Translation Service
        $this->register('translation', function($container) {
            if (class_exists('MoneyQuiz\\Services\\TranslationService')) {
                return MoneyQuiz\Services\TranslationService::getInstance();
            }
            return null;
        });
    }
    
    /**
     * Register AI services
     */
    private function registerAIServices() {
        // AI Core Service
        $this->register('ai', function($container) {
            if (class_exists('MoneyQuiz\\AI\\AIService')) {
                return MoneyQuiz\AI\AIService::getInstance();
            }
            return null;
        });
        
        // Pattern Recognition
        $this->register('pattern_recognition', function($container) {
            if (class_exists('MoneyQuiz\\AI\\PatternRecognition')) {
                return MoneyQuiz\AI\PatternRecognition::getInstance();
            }
            return null;
        });
        
        // ML Recommendations
        $this->register('ml_recommendations', function($container) {
            if (class_exists('MoneyQuiz\\AI\\MLRecommendations\\RecommendationEngine')) {
                return MoneyQuiz\AI\MLRecommendations\RecommendationEngine::getInstance();
            }
            return null;
        });
        
        // Predictive Analytics
        $this->register('predictive_analytics', function($container) {
            if (class_exists('MoneyQuiz\\AI\\PredictiveAnalytics\\AnalyticsEngine')) {
                return MoneyQuiz\AI\PredictiveAnalytics\AnalyticsEngine::getInstance();
            }
            return null;
        });
        
        // NLP Processor
        $this->register('nlp', function($container) {
            if (class_exists('MoneyQuiz\\AI\\NLP\\NLPProcessor')) {
                return MoneyQuiz\AI\NLP\NLPProcessor::getInstance();
            }
            return null;
        });
    }
    
    /**
     * Register security services
     */
    private function registerSecurityServices() {
        // Security Manager
        $this->register('security', function($container) {
            if (class_exists('MoneyQuizSecurityManager')) {
                return MoneyQuizSecurityManager::getInstance();
            }
            return null;
        });
        
        // CSRF Protection
        $this->register('csrf', function($container) {
            if (class_exists('MoneyQuizCSRFProtection')) {
                return MoneyQuizCSRFProtection::getInstance();
            }
            return null;
        });
        
        // Rate Limiter
        $this->register('rate_limiter', function($container) {
            if (class_exists('MoneyQuizRateLimiter')) {
                return MoneyQuizRateLimiter::getInstance();
            }
            return null;
        });
        
        // Audit Logger
        $this->register('audit', function($container) {
            if (class_exists('MoneyQuizAuditLogger')) {
                return MoneyQuizAuditLogger::getInstance();
            }
            return null;
        });
    }
    
    /**
     * Register performance services
     */
    private function registerPerformanceServices() {
        // Cache Manager
        $this->register('cache', function($container) {
            if (class_exists('MoneyQuiz\\Cache\\CacheManager')) {
                return MoneyQuiz\Cache\CacheManager::getInstance();
            }
            return null;
        });
        
        // Query Optimizer
        $this->register('query_optimizer', function($container) {
            if (class_exists('MoneyQuiz\\Performance\\QueryOptimizer')) {
                return MoneyQuiz\Performance\QueryOptimizer::getInstance();
            }
            return null;
        });
        
        // Background Jobs
        $this->register('jobs', function($container) {
            if (class_exists('MoneyQuiz\\Jobs\\QueueSystem')) {
                return MoneyQuiz\Jobs\QueueSystem::getInstance();
            }
            return null;
        });
        
        // Smart Cache
        $this->register('smart_cache', function($container) {
            if (class_exists('MoneyQuiz\\AI\\SmartCache\\IntelligentCache')) {
                return MoneyQuiz\AI\SmartCache\IntelligentCache::getInstance();
            }
            return null;
        });
    }
    
    /**
     * Register enhancement services
     */
    private function registerEnhancementServices() {
        // Analytics Service
        $this->register('analytics', function($container) {
            if (class_exists('MoneyQuiz\\Analytics\\AnalyticsService')) {
                return MoneyQuiz\Analytics\AnalyticsService::getInstance();
            }
            return null;
        });
        
        // Webhook Service
        $this->register('webhooks', function($container) {
            if (class_exists('MoneyQuiz\\Webhooks\\WebhookService')) {
                return MoneyQuiz\Webhooks\WebhookService::getInstance();
            }
            return null;
        });
        
        // A/B Testing
        $this->register('ab_testing', function($container) {
            if (class_exists('MoneyQuiz\\ABTesting\\ABTestingService')) {
                return MoneyQuiz\ABTesting\ABTestingService::getInstance();
            }
            return null;
        });
        
        // Personalization
        $this->register('personalization', function($container) {
            if (class_exists('MoneyQuiz\\Personalization\\PersonalizationService')) {
                return MoneyQuiz\Personalization\PersonalizationService::getInstance();
            }
            return null;
        });
    }
    
    /**
     * Initialize eager services
     */
    private function initializeEagerServices() {
        $eager_services = [
            'security',  // Initialize security first
            'database',  // Database connections
            'cache',     // Cache warming
            'ai'         // AI models loading
        ];
        
        foreach ($eager_services as $service) {
            try {
                if ($this->has($service)) {
                    $instance = $this->get($service);
                    if ($instance && method_exists($instance, 'init')) {
                        $instance->init();
                    }
                }
            } catch (Exception $e) {
                error_log('Money Quiz: Failed to initialize service ' . $service . ': ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get all registered services
     */
    public function getRegisteredServices() {
        return array_keys($this->factories);
    }
    
    /**
     * Get initialized services
     */
    public function getInitializedServices() {
        return array_keys($this->initialized);
    }
    
    /**
     * Clear all services (for testing)
     */
    public function clear() {
        $this->services = [];
        $this->initialized = [];
    }
}

