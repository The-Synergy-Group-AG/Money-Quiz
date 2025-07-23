<?php
/**
 * Domain Event Interface
 *
 * Base interface for all domain events.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Domain event interface.
 *
 * All domain events must implement this interface to ensure
 * consistent event handling throughout the application.
 *
 * @since 7.0.0
 */
interface DomainEvent {
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string;
    
    /**
     * Get event payload.
     *
     * @return array Event data.
     */
    public function get_payload(): array;
    
    /**
     * Get event timestamp.
     *
     * @return \DateTimeInterface When event occurred.
     */
    public function get_occurred_at(): \DateTimeInterface;
    
    /**
     * Get aggregate ID.
     *
     * @return int|string ID of the aggregate that triggered the event.
     */
    public function get_aggregate_id();
    
    /**
     * Get aggregate type.
     *
     * @return string Type of aggregate (e.g., 'quiz', 'question').
     */
    public function get_aggregate_type(): string;
}