<?php
/**
 * Result Calculated Event
 *
 * Raised when a quiz result is calculated.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Result;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result calculated event class.
 *
 * @since 7.0.0
 */
final class ResultCalculated implements DomainEvent {
    
    /**
     * Result entity.
     *
     * @var Result
     */
    private Result $result;
    
    /**
     * Event timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $occurred_at;
    
    /**
     * Constructor.
     *
     * @param Result $result The calculated result.
     */
    public function __construct(Result $result) {
        $this->result = $result;
        $this->occurred_at = new \DateTimeImmutable('now', wp_timezone());
    }
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string {
        return 'result.calculated';
    }
    
    /**
     * Get event payload.
     *
     * @return array Event data.
     */
    public function get_payload(): array {
        return [
            'result_id' => $this->result->get_id(),
            'attempt_id' => $this->result->get_attempt_id(),
            'quiz_id' => $this->result->get_quiz_id(),
            'user_id' => $this->result->get_user_id(),
            'score' => $this->result->get_score()->get_total(),
            'percentage' => $this->result->get_score()->get_percentage()
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
     * @return Result The calculated result.
     */
    public function get_result(): Result {
        return $this->result;
    }
}