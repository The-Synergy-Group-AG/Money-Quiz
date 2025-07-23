<?php
/**
 * Question Created Event
 *
 * Raised when a new question is created.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Question;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Question created event class.
 *
 * @since 7.0.0
 */
final class QuestionCreated implements DomainEvent {
    
    /**
     * Question entity.
     *
     * @var Question
     */
    private Question $question;
    
    /**
     * Event timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $occurred_at;
    
    /**
     * Constructor.
     *
     * @param Question $question The created question.
     */
    public function __construct(Question $question) {
        $this->question = $question;
        $this->occurred_at = new \DateTimeImmutable('now', wp_timezone());
    }
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string {
        return 'question.created';
    }
    
    /**
     * Get event payload.
     *
     * @return array Event data.
     */
    public function get_payload(): array {
        return [
            'question_id' => $this->question->get_id(),
            'quiz_id' => $this->question->get_quiz_id(),
            'text' => $this->question->get_text(),
            'type' => $this->question->get_type(),
            'answer_count' => count($this->question->get_answers()),
            'points' => $this->question->get_points()
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
     * @return int Question ID.
     */
    public function get_aggregate_id() {
        return $this->question->get_id();
    }
    
    /**
     * Get aggregate type.
     *
     * @return string Aggregate type.
     */
    public function get_aggregate_type(): string {
        return 'question';
    }
    
    /**
     * Get the question entity.
     *
     * @return Question The created question.
     */
    public function get_question(): Question {
        return $this->question;
    }
}