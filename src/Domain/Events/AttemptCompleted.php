<?php
/**
 * Attempt Completed Event
 *
 * Domain event fired when a quiz attempt is completed.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Attempt;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt completed event class.
 *
 * @since 7.0.0
 */
class AttemptCompleted implements DomainEvent {
    
    /**
     * Event name.
     *
     * @var string
     */
    private const EVENT_NAME = 'attempt.completed';
    
    /**
     * The attempt that was completed.
     *
     * @var Attempt
     */
    private Attempt $attempt;
    
    /**
     * Event timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $occurred_at;
    
    /**
     * Constructor.
     *
     * @param Attempt $attempt The attempt that was completed.
     */
    public function __construct(Attempt $attempt) {
        $this->attempt = $attempt;
        $this->occurred_at = new \DateTimeImmutable('now', wp_timezone());
    }
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string {
        return self::EVENT_NAME;
    }
    
    /**
     * Get when event occurred.
     *
     * @return \DateTimeInterface Timestamp.
     */
    public function get_occurred_at(): \DateTimeInterface {
        return $this->occurred_at;
    }
    
    /**
     * Get event payload.
     *
     * @return array Event data.
     */
    public function get_payload(): array {
        return [
            'attempt_id' => $this->attempt->get_id(),
            'quiz_id' => $this->attempt->get_quiz_id(),
            'user_id' => $this->attempt->get_user_id(),
            'user_email' => $this->attempt->get_user_email(),
            'result_id' => $this->attempt->get_result_id(),
            'time_taken' => $this->attempt->get_time_taken(),
            'completed_at' => $this->attempt->get_completed_at()->format('c')
        ];
    }
    
    /**
     * Get the attempt.
     *
     * @return Attempt The attempt.
     */
    public function get_attempt(): Attempt {
        return $this->attempt;
    }
}