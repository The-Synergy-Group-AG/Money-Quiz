<?php
/**
 * Phase 3 Core Service Provider
 *
 * Registers all Phase 3 core application components.
 *
 * @package MoneyQuiz\Core\ServiceProviders
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Domain\Events\EventDispatcher;
use MoneyQuiz\Application\Services\QuizService;
use MoneyQuiz\Application\Services\ResultCalculationService;
use MoneyQuiz\Application\Services\AttemptService;
use MoneyQuiz\Database\Repositories\QuizRepository;
use MoneyQuiz\Database\Repositories\ResultRepository;
use MoneyQuiz\Database\Repositories\ArchetypeRepository;
use MoneyQuiz\Database\Repositories\AttemptRepository;
use MoneyQuiz\API\Controllers\QuizController;
use MoneyQuiz\API\Controllers\ResultController;
use MoneyQuiz\API\Controllers\ArchetypeController;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Phase 3 core service provider class.
 *
 * @since 7.0.0
 */
class Phase3CoreServiceProvider extends AbstractServiceProvider {
    
    /**
     * Register services.
     *
     * @since 7.0.0
     *
     * @return void
     */
    public function register(): void {
        // Register Event Dispatcher
        $this->singleton(
            EventDispatcher::class,
            function($container) {
                $async_enabled = get_option('money_quiz_async_events', false);
                return new EventDispatcher(
                    $container->get(Logger::class),
                    $async_enabled
                );
            }
        );
        
        // Register Repositories
        $this->singleton(
            \MoneyQuiz\Domain\Repositories\QuizRepository::class,
            function($container) {
                global $wpdb;
                return new QuizRepository(
                    $wpdb,
                    $container->get(Logger::class)
                );
            }
        );
        
        $this->singleton(
            \MoneyQuiz\Domain\Repositories\ResultRepository::class,
            function($container) {
                global $wpdb;
                return new ResultRepository(
                    $wpdb,
                    $container->get(Logger::class)
                );
            }
        );
        
        $this->singleton(
            \MoneyQuiz\Domain\Repositories\ArchetypeRepository::class,
            function($container) {
                global $wpdb;
                return new ArchetypeRepository(
                    $wpdb,
                    $container->get(Logger::class)
                );
            }
        );
        
        $this->singleton(
            \MoneyQuiz\Domain\Repositories\AttemptRepository::class,
            function($container) {
                global $wpdb;
                return new AttemptRepository(
                    $wpdb,
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Quiz Service
        $this->singleton(
            QuizService::class,
            function($container) {
                return new QuizService(
                    $container->get(\MoneyQuiz\Domain\Repositories\QuizRepository::class),
                    $container->get(EventDispatcher::class),
                    $container->get(\MoneyQuiz\Security\Contracts\AuthorizationInterface::class),
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Result Calculation Service
        $this->singleton(
            ResultCalculationService::class,
            function($container) {
                return new ResultCalculationService(
                    $container->get(\MoneyQuiz\Domain\Repositories\ResultRepository::class),
                    $container->get(\MoneyQuiz\Domain\Repositories\ArchetypeRepository::class),
                    $container->get(EventDispatcher::class),
                    $container->get(\MoneyQuiz\Security\Contracts\AuthorizationInterface::class),
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register Attempt Service
        $this->singleton(
            AttemptService::class,
            function($container) {
                return new AttemptService(
                    $container->get(\MoneyQuiz\Domain\Repositories\AttemptRepository::class),
                    $container->get(\MoneyQuiz\Domain\Repositories\QuizRepository::class),
                    $container->get(ResultCalculationService::class),
                    $container->get(EventDispatcher::class),
                    $container->get(\MoneyQuiz\Security\Contracts\AuthorizationInterface::class),
                    $container->get(Logger::class)
                );
            }
        );
        
        // Register API Controllers
        $this->singleton(
            QuizController::class,
            function($container) {
                return new QuizController(
                    $container->get(QuizService::class),
                    $container->get(Logger::class)
                );
            }
        );
        
        $this->singleton(
            ResultController::class,
            function($container) {
                return new ResultController(
                    $container->get(ResultCalculationService::class),
                    $container->get(AttemptService::class),
                    $container->get(\MoneyQuiz\Security\Authorization::class),
                    $container->get(Logger::class)
                );
            }
        );
        
        $this->singleton(
            ArchetypeController::class,
            function($container) {
                return new ArchetypeController(
                    $container->get(\MoneyQuiz\Domain\Repositories\ArchetypeRepository::class),
                    $container->get(\MoneyQuiz\Security\Authorization::class),
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
        // Register domain event listeners
        $this->register_event_listeners();
        
        // Register WordPress hooks
        $this->register_hooks();
        
        // Register REST API routes
        $this->register_rest_routes();
        
        // Log Phase 3 initialization
        $this->get(Logger::class)->info('Phase 3 Core Components initialized', [
            'services' => [
                'event_dispatcher' => true,
                'quiz_service' => true,
                'result_service' => true,
                'attempt_service' => true,
                'repositories' => ['quiz', 'result', 'archetype', 'attempt'],
                'api_controllers' => ['quiz', 'result', 'archetype']
            ]
        ]);
    }
    
    /**
     * Register event listeners.
     *
     * @return void
     */
    private function register_event_listeners(): void {
        $dispatcher = $this->get(EventDispatcher::class);
        
        // Example: Log all domain events
        $dispatcher->add_listener('*', function($event) {
            $this->get(Logger::class)->info('Domain event occurred', [
                'event' => $event->get_event_name(),
                'payload' => $event->get_payload()
            ]);
        }, 100);
        
        // Quiz created listener
        $dispatcher->add_listener('quiz.created', function($event) {
            // Could trigger notifications, cache clearing, etc.
            do_action('money_quiz_after_quiz_created', $event->get_quiz());
        });
        
        // Quiz published listener
        $dispatcher->add_listener('quiz.published', function($event) {
            // Could send notifications to subscribers
            do_action('money_quiz_after_quiz_published', $event->get_quiz());
        });
        
        // Attempt started listener
        $dispatcher->add_listener('attempt.started', function($event) {
            // Track attempt analytics
            do_action('money_quiz_after_attempt_started', $event->get_attempt());
        });
        
        // Attempt completed listener
        $dispatcher->add_listener('attempt.completed', function($event) {
            // Could trigger email notifications, etc.
            do_action('money_quiz_after_attempt_completed', $event->get_attempt());
        });
    }
    
    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void {
        // Handle async events
        add_action('money_quiz_async_event', [$this, 'handle_async_event']);
    }
    
    /**
     * Handle async event execution.
     *
     * @param array $args Event arguments.
     * @return void
     */
    public function handle_async_event(array $args): void {
        try {
            $event = unserialize($args['event']);
            $listener = $args['listener'];
            
            // Execute the listener
            if (is_callable($listener)) {
                call_user_func($listener, $event);
            }
        } catch (\Exception $e) {
            $this->get(Logger::class)->error('Async event handling failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Register REST API routes.
     *
     * @return void
     */
    private function register_rest_routes(): void {
        add_action('rest_api_init', function() {
            // Register Quiz API routes
            $this->get(QuizController::class)->register_routes();
            
            // Register Result API routes
            $this->get(ResultController::class)->register_routes();
            
            // Register Archetype API routes
            $this->get(ArchetypeController::class)->register_routes();
        });
    }
}