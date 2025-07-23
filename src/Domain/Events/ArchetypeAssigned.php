<?php
/**
 * Archetype Assigned Event
 *
 * Raised when an archetype is assigned to a result.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Result;
use MoneyQuiz\Domain\Entities\Archetype;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype assigned event class.
 *
 * @since 7.0.0
 */
final class ArchetypeAssigned implements DomainEvent {
    
    /**
     * Result entity.
     *
     * @var Result
     */
    private Result $result;
    
    /**
     * Assigned archetype.
     *
     * @var Archetype
     */
    private Archetype $archetype;
    
    /**
     * Event timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $occurred_at;
    
    /**
     * Constructor.
     *
     * @param Result    $result    The result.
     * @param Archetype $archetype The assigned archetype.
     */
    public function __construct(Result $result, Archetype $archetype) {
        $this->result = $result;
        $this->archetype = $archetype;
        $this->occurred_at = new \DateTimeImmutable('now', wp_timezone());
    }
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string {
        return 'archetype.assigned';
    }
    
    /**
     * Get event payload.
     *
     * @return array Event data.
     */
    public function get_payload(): array {
        return [
            'result_id' => $this->result->get_id(),
            'archetype_id' => $this->archetype->get_id(),
            'archetype_slug' => $this->archetype->get_slug(),
            'archetype_name' => $this->archetype->get_name(),
            'user_id' => $this->result->get_user_id(),
            'quiz_id' => $this->result->get_quiz_id()
        ];
    }
    
    /**
     * Get event timestamp.
     *
     * @return \DateTimeInterface When event occurred.
     */
    public function get_occurred_at(): \DateTimeInterface {
        return $this->occurred_at;
    }
    
    /**
     * Get aggregate ID.
     *
     * @return int Result ID.
     */
    public function get_aggregate_id() {
        return $this->result->get_id();
    }
    
    /**
     * Get aggregate type.
     *
     * @return string Aggregate type.
     */
    public function get_aggregate_type(): string {
        return 'result';
    }
    
    /**
     * Get the result entity.
     *
     * @return Result The result.
     */
    public function get_result(): Result {
        return $this->result;
    }
    
    /**
     * Get the assigned archetype.
     *
     * @return Archetype The archetype.
     */
    public function get_archetype(): Archetype {
        return $this->archetype;
    }
}