<?php
/**
 * Base Entity
 *
 * Abstract base class for all domain entities.
 *
 * @package MoneyQuiz\Domain\Entities
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\Events\DomainEvent;
use MoneyQuiz\Domain\Exceptions\EntityException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Abstract entity class.
 *
 * Provides common functionality for all domain entities including
 * identity management, event recording, and validation.
 *
 * @since 7.0.0
 */
abstract class Entity {
    
    /**
     * Entity ID.
     *
     * @var int|null
     */
    protected ?int $id = null;
    
    /**
     * Creation timestamp.
     *
     * @var \DateTimeInterface|null
     */
    protected ?\DateTimeInterface $created_at = null;
    
    /**
     * Last update timestamp.
     *
     * @var \DateTimeInterface|null
     */
    protected ?\DateTimeInterface $updated_at = null;
    
    /**
     * Recorded domain events.
     *
     * @var array<DomainEvent>
     */
    private array $events = [];
    
    /**
     * Get entity ID.
     *
     * @return int|null Entity ID or null if not persisted.
     */
    public function get_id(): ?int {
        return $this->id;
    }
    
    /**
     * Check if entity is persisted.
     *
     * @return bool True if entity has been saved to database.
     */
    public function is_persisted(): bool {
        return $this->id !== null && $this->id > 0;
    }
    
    /**
     * Get creation timestamp.
     *
     * @return \DateTimeInterface|null Creation time.
     */
    public function get_created_at(): ?\DateTimeInterface {
        return $this->created_at;
    }
    
    /**
     * Get last update timestamp.
     *
     * @return \DateTimeInterface|null Last update time.
     */
    public function get_updated_at(): ?\DateTimeInterface {
        return $this->updated_at;
    }
    
    /**
     * Record domain event.
     *
     * Events are collected and can be dispatched after entity persistence.
     *
     * @param DomainEvent $event Event to record.
     * @return void
     */
    protected function record_event(DomainEvent $event): void {
        $this->events[] = $event;
    }
    
    /**
     * Get and clear recorded events.
     *
     * @return array<DomainEvent> Recorded events.
     */
    public function release_events(): array {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
    
    /**
     * Validate entity state.
     *
     * @throws EntityException If validation fails.
     * @return void
     */
    abstract public function validate(): void;
    
    /**
     * Convert entity to array.
     *
     * @return array Entity data.
     */
    abstract public function to_array(): array;
    
    /**
     * Create entity from array.
     *
     * @param array $data Entity data.
     * @return static New entity instance.
     */
    abstract public static function from_array(array $data): self;
    
    /**
     * Update timestamps.
     *
     * @param bool $is_create Whether this is a create operation.
     * @return void
     */
    protected function update_timestamps(bool $is_create = false): void {
        $now = new \DateTimeImmutable('now', wp_timezone());
        
        if ($is_create && $this->created_at === null) {
            $this->created_at = $now;
        }
        
        $this->updated_at = $now;
    }
}