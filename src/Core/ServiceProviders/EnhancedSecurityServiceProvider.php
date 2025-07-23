<?php
/**
 * Enhanced Security Service Provider
 *
 * Registers all enhanced security services with improvements from Grok review.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Config\ConfigManager;
use MoneyQuiz\Core\ErrorHandler;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Security\OutputEscaper;
use MoneyQuiz\Security\EnhancedNonceManager;
use MoneyQuiz\Security\ConfigurableRateLimiter;
use MoneyQuiz\Security\AccessControl;
use MoneyQuiz\Security\SecurityAuditor;
use MoneyQuiz\Security\SecurityHeaders;
use MoneyQuiz\Security\CORSManager;
use MoneyQuiz\Frontend\EnhancedSessionManager;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Enhanced security service provider class.
 *
 * @since 7.0.0
 */
class EnhancedSecurityServiceProvider extends AbstractServiceProvider {
    
    /**
     * Register services.
     *
     * @since 7.0.0
     *
     * @return void
     */
    public function register(): void {
        // Register centralized error handler
        $this->singleton(
            ErrorHandler::class,
            function($container) {
                return new ErrorHandler(
                    $container->get(Logger::class),
                    defined('WP_DEBUG') && WP_DEBUG
                );
            }
        );
        
        // Register InputValidator
        $this->singleton(
            InputValidator::class,
            function($container) {
                return new InputValidator(
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register OutputEscaper (static class, no instantiation needed)
        $this->singleton(
            OutputEscaper::class,
            function() {
                return new class {
                    public function __call($method, $args) {
                        return OutputEscaper::$method(...$args);
                    }
                };
            }
        );
        
        // Register Enhanced NonceManager with constant-time comparison
        $this->singleton(
            EnhancedNonceManager::class,
            function($container) {
                return new EnhancedNonceManager(
                    $container->param('plugin.text_domain'),
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Configurable RateLimiter
        $this->singleton(
            ConfigurableRateLimiter::class,
            function($container) {
                global $wpdb;
                return new ConfigurableRateLimiter(
                    $wpdb->prefix . 'money_quiz_rate_limits',
                    $container->get(Logger::class),
                    $container->get(ConfigManager::class)
                );
            }
        );
        
        // Register AccessControl
        $this->singleton(
            AccessControl::class,
            function() {
                return new AccessControl();
            }
        );
        
        // Register SecurityAuditor
        $this->singleton(
            SecurityAuditor::class,
            function($container) {
                return new SecurityAuditor(
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Security Headers with nonce-based CSP
        $this->singleton(
            SecurityHeaders::class,
            function() {
                return new SecurityHeaders();
            }
        );
        
        // Register CORS Manager with strict validation
        $this->singleton(
            CORSManager::class,
            function($container) {
                return new CORSManager(
                    $container->get(Logger::class),
                    $container->get(ConfigManager::class)
                );
            }
        );
        
        // Register Enhanced Session Manager
        $this->singleton(
            EnhancedSessionManager::class,
            function($container) {
                return new EnhancedSessionManager(
                    $container->param('plugin.text_domain'),
                    $container->get(Logger::class)
                );
            }
        );
    }
    
    /**
     * Bootstrap services.
     *
     * @since 7.0.0
     *
     * @return void
     */
    public function boot(): void {
        // Initialize error handler first
        $error_handler = $this->get(ErrorHandler::class);
        $error_handler->init();
        
        // Initialize security headers with nonce-based CSP
        $headers = $this->get(SecurityHeaders::class);
        $headers->init();
        
        // Initialize CORS if enabled
        $cors = $this->get(CORSManager::class);
        $cors->init();
        
        // Initialize enhanced session management
        $session = $this->get(EnhancedSessionManager::class);
        add_action('init', [$session, 'start'], 1);
        
        // Set nonce lifetime policy
        $this->configure_nonce_lifetimes();
        
        // Initialize rate limiter cleanup
        $rate_limiter = $this->get(ConfigurableRateLimiter::class);
        add_action('money_quiz_daily_cleanup', [$rate_limiter, 'cleanup']);
        
        // Prevent user enumeration
        add_filter('rest_endpoints', [$this, 'disable_user_endpoints']);
        
        // Remove version strings from assets
        add_filter('style_loader_src', [$this, 'remove_version_strings'], 9999);
        add_filter('script_loader_src', [$this, 'remove_version_strings'], 9999);
        
        // Schedule daily security cleanup
        if (!wp_next_scheduled('money_quiz_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'money_quiz_daily_cleanup');
        }
        
        // Log security initialization
        $logger = $this->get(Logger::class);
        $logger->info('Enhanced security services initialized', [
            'constant_time_nonce' => true,
            'configurable_rate_limits' => true,
            'session_regeneration' => true,
            'cors_strict_validation' => true,
            'csp_nonce_based' => true,
            'centralized_error_handling' => true
        ]);
    }
    
    /**
     * Configure nonce lifetimes based on policy.
     */
    private function configure_nonce_lifetimes(): void {
        $nonce_manager = $this->get(EnhancedNonceManager::class);
        
        // Critical actions - 5 minutes
        $nonce_manager->set_critical_lifetime('delete_quiz', 300);
        $nonce_manager->set_critical_lifetime('reset_data', 300);
        $nonce_manager->set_critical_lifetime('delete_user_data', 300);
        $nonce_manager->set_critical_lifetime('bulk_delete', 300);
        
        // Export operations - 10 minutes
        $nonce_manager->set_critical_lifetime('export_data', 600);
        $nonce_manager->set_critical_lifetime('export_quiz', 600);
        $nonce_manager->set_critical_lifetime('export_results', 600);
        $nonce_manager->set_critical_lifetime('manage_users', 600);
        
        // Settings changes - 15 minutes
        $nonce_manager->set_critical_lifetime('change_settings', 900);
        $nonce_manager->set_critical_lifetime('update_security', 900);
        $nonce_manager->set_critical_lifetime('modify_permissions', 900);
    }
    
    /**
     * Disable user enumeration endpoints.
     *
     * @param array $endpoints REST endpoints.
     * @return array Modified endpoints.
     */
    public function disable_user_endpoints(array $endpoints): array {
        unset($endpoints['/wp/v2/users']);
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        return $endpoints;
    }
    
    /**
     * Remove version strings from assets.
     *
     * @param string $src Asset source URL.
     * @return string Modified URL.
     */
    public function remove_version_strings(string $src): string {
        if (strpos($src, 'money-quiz') !== false) {
            return remove_query_arg('ver', $src);
        }
        return $src;
    }
}