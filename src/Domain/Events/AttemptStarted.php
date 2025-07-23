<?php
/**
 * Attempt Started Event
 *
 * Domain event fired when a quiz attempt is started.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Attempt;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt started event class.
 *
 * @since 7.0.0
 */
class AttemptStarted implements DomainEvent {
    
    /**
     * Event name.
     *
     * @var string
     */
    private const EVENT_NAME = 'attempt.started';
    
    /**
     * The attempt that was started.
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
     * @param Attempt $attempt The attempt that was started.
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
            'session_token' => $this->attempt->get_session_token(),
            'started_at' => $this->attempt->get_started_at()->format('c')
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