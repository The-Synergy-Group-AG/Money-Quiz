<?php
/**
 * CSRF Protection Loader
 * 
 * @package MoneyQuiz\Security\CSRF
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\CSRF;

// Load all CSRF components
require_once __DIR__ . '/csrf-1-core-constants.php';
require_once __DIR__ . '/csrf-2-token-generation.php';
require_once __DIR__ . '/csrf-3-token-validation.php';
require_once __DIR__ . '/csrf-4-storage-backend.php';
require_once __DIR__ . '/csrf-5-integration.php';

/**
 * CSRF Protection Factory
 */
class CsrfProtection {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            // Choose storage backend
            $storage = apply_filters('money_quiz_csrf_storage', 'session');
            
            if ($storage === 'database') {
                $storage_backend = new CsrfDatabaseStorage();
            } else {
                $storage_backend = new CsrfSessionStorage();
            }
            
            // Create components
            $generator = new CsrfTokenGenerator($storage_backend);
            $validator = new CsrfTokenValidator($storage_backend);
            
            // Configure validator
            $validator->configure(
                apply_filters('money_quiz_csrf_check_ip', true),
                apply_filters('money_quiz_csrf_check_agent', true)
            );
            
            // Create integration
            self::$instance = new CsrfWordPressIntegration($generator, $validator);
        }
        
        return self::$instance;
    }
    
    /**
     * Initialize CSRF protection
     */
    public static function init() {
        $instance = self::getInstance();
        $instance->init();
        
        // Create database table if using DB storage
        if (apply_filters('money_quiz_csrf_storage', 'session') === 'database') {
            register_activation_hook(__FILE__, [CsrfDatabaseStorage::class, 'createTable']);
        }
    }
}

// Helper functions
if (!function_exists('money_quiz_csrf_field')) {
    function money_quiz_csrf_field($action = 'money_quiz_action', $echo = true) {
        $csrf = CsrfProtection::getInstance();
        return $csrf->generator->getField($action, $echo);
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', [CsrfProtection::class, 'init']);