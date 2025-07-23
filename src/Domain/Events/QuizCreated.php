<?php
/**
 * Quiz Created Event
 *
 * Raised when a new quiz is created.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Quiz;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz created event class.
 *
 * @since 7.0.0
 */
final class QuizCreated implements DomainEvent {
    
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
     * @param Quiz $quiz The created quiz.
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
        return 'quiz.created';
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
            'description' => $this->quiz->get_description(),
            'status' => $this->quiz->get_status(),
            'created_by' => $this->quiz->get_created_by(),
            'settings' => $this->quiz->get_settings()->to_array()
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
     * @return Quiz The created quiz.
     */
    public function get_quiz(): Quiz {
        return $this->quiz;
    }
}