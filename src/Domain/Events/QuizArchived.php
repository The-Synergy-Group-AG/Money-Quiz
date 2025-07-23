<?php
/**
 * Quiz Archived Event
 *
 * Raised when a quiz is archived.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Quiz;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz archived event class.
 *
 * @since 7.0.0
 */
final class QuizArchived implements DomainEvent {
    
    /**
     * Quiz entity.
     *
     * @var Quiz
     */
    private Quiz $quiz;
    
    /**
     * Event timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $occurred_at;
    
    /**
     * Constructor.
     *
     * @param Quiz $quiz The archived quiz.
     */
    public function __construct(Quiz $quiz) {
        $this->quiz = $quiz;
        $this->occurred_at = new \DateTimeImmutable('now', wp_timezone());
    }
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string {
        return 'quiz.archived';
    }
    
    /**
     * Get event payload.
     *
     * @return array Event data.
     */
    public function get_payload(): array {
        return [
            'quiz_id' => $this->quiz->get_id(),
            'title' => $this->quiz->get_title(),
            'archived_by' => get_current_user_id()
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
     * @return int Quiz ID.
     */
    public function get_aggregate_id() {
        return $this->quiz->get_id();
    }
    
    /**
     * Get aggregate type.
     *
     * @return string Aggregate type.
     */
    public function get_aggregate_type(): string {
        return 'quiz';
    }
    
    /**
     * Get the quiz entity.
     *
     * @return Quiz The archived quiz.
     */
    public function get_quiz(): Quiz {
        return $this->quiz;
    }
}