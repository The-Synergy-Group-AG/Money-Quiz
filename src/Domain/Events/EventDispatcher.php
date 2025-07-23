<?php
/**
 * Event Dispatcher
 *
 * Handles domain event dispatching.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Event dispatcher class.
 *
 * @since 7.0.0
 */
class EventDispatcher {
    
    /**
     * Registered listeners.
     *
     * @var array<string, array<callable>>
     */
    private array $listeners = [];
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Whether to dispatch events asynchronously.
     *
     * @var bool
     */
    private bool $async_enabled;
    
    /**
     * Constructor.
     *
     * @param Logger $logger        Logger instance.
     * @param bool   $async_enabled Whether to enable async dispatch.
     */
    public function __construct(Logger $logger, bool $async_enabled = false) {
        $this->logger = $logger;
        $this->async_enabled = $async_enabled;
    }
    
    /**
     * Register event listener.
     *
     * @param string   $event_name Event name to listen for.
     * @param callable $listener   Listener callback.
     * @param int      $priority   Priority (lower executes first).
     * @return void
     */
    public function add_listener(string $event_name, callable $listener, int $priority = 10): void {
        if (!isset($this->listeners[$event_name])) {
            $this->listeners[$event_name] = [];
        }
        
        $this->listeners[$event_name][] = [
            'callback' => $listener,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort($this->listeners[$event_name], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    /**
     * Remove event listener.
     *
     * @param string   $event_name Event name.
     * @param callable $listener   Listener to remove.
     * @return void
     */
    public function remove_listener(string $event_name, callable $listener): void {
        if (!isset($this->listeners[$event_name])) {
            return;
        }
        
        $this->listeners[$event_name] = array_filter(
            $this->listeners[$event_name],
            fn($item) => $item['callback'] !== $listener
        );
    }
    
    /**
     * Dispatch domain event.
     *
     * @param DomainEvent $event Event to dispatch.
     * @return void
     */
    public function dispatch(DomainEvent $event): void {
        $event_name = $event->get_event_name();
        
        $this->logger->debug('Dispatching domain event', [
            'event' => $event_name,
            'aggregate_type' => $event->get_aggregate_type(),
            'aggregate_id' => $event->get_aggregate_id()
        ]);
        
        // Dispatch to WordPress hooks
        do_action('money_quiz_' . $event_name, $event);
        
        // Dispatch to registered listeners
        if (isset($this->listeners[$event_name])) {
            foreach ($this->listeners[$event_name] as $listener_info) {
                $this->invoke_listener($listener_info['callback'], $event);
            }
        }
        
        // Dispatch to wildcard listeners
        if (isset($this->listeners['*'])) {
            foreach ($this->listeners['*'] as $listener_info) {
                $this->invoke_listener($listener_info['callback'], $event);
            }
        }
    }
    
    /**
     * Dispatch multiple events.
     *
     * @param array<DomainEvent> $events Events to dispatch.
     * @return void
     */
    public function dispatch_batch(array $events): void {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
    
    /**
     * Invoke listener callback.
     *
     * @param callable    $listener Listener callback.
     * @param DomainEvent $event    Event object.
     * @return void
     */
    private function invoke_listener(callable $listener, DomainEvent $event): void {
        try {
            if ($this->async_enabled && $this->should_dispatch_async($event)) {
                $this->dispatch_async($listener, $event);
            } else {
                call_user_func($listener, $event);
            }
        } catch (\Exception $e) {
            $this->logger->error('Event listener failed', [
                'event' => $event->get_event_name(),
                'listener' => $this->get_listener_name($listener),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if event should be dispatched asynchronously.
     *
     * @param DomainEvent $event Event to check.
     * @return bool True if should dispatch async.
     */
    private function should_dispatch_async(DomainEvent $event): bool {
        // Define events that should always be synchronous
        $sync_events = [
            'quiz.created',
            'quiz.updated',
            'question.created',
            'question.updated'
        ];
        
        return !in_array($event->get_event_name(), $sync_events, true);
    }
    
    /**
     * Dispatch event asynchronously.
     *
     * @param callable    $listener Listener callback.
     * @param DomainEvent $event    Event object.
     * @return void
     */
    private function dispatch_async(callable $listener, DomainEvent $event): void {
        // Schedule as WordPress action
        wp_schedule_single_event(
            time() + 1,
            'money_quiz_async_event',
            [
                'listener' => $this->serialize_listener($listener),
                'event' => serialize($event)
            ]
        );
    }
    
    /**
     * Get listener name for logging.
     *
     * @param callable $listener Listener callback.
     * @return string Listener name.
     */
    private function get_listener_name(callable $listener): string {
        if (is_string($listener)) {
            return $listener;
        }
        
        if (is_array($listener)) {
            $class = is_object($listener[0]) ? get_class($listener[0]) : $listener[0];
            return $class . '::' . $listener[1];
        }
        
        if (is_object($listener) && !$listener instanceof \Closure) {
            return get_class($listener) . '::__invoke';
        }
        
        return 'Closure';
    }
    
    /**
     * Serialize listener for async dispatch.
     *
     * @param callable $listener Listener callback.
     * @return string Serialized listener.
     */
    private function serialize_listener(callable $listener): string {
        if (is_string($listener)) {
            return $listener;
        }
        
        if (is_array($listener) && is_string($listener[0])) {
            return implode('::', $listener);
        }
        
        // For object methods and closures, we'll need to handle differently
        // For now, just return a placeholder
        return 'deferred_listener';
    }
}