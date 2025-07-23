<?php
/**
 * Question Updated Event
 *
 * Raised when a question is updated.
 *
 * @package MoneyQuiz\Domain\Events
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Events;

use MoneyQuiz\Domain\Entities\Question;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Question updated event class.
 *
 * @since 7.0.0
 */
final class QuestionUpdated implements DomainEvent {
    
    /**
     * Question entity.
     *
     * @var Question
     */
    private Question $question;
    
    /**
     * Changed fields.
     *
     * @var array
     */
    private array $changes;
    
    /**
     * Event timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $occurred_at;
    
    /**
     * Constructor.
     *
     * @param Question $question The updated question.
     * @param array    $changes  Changed fields.
     */
    public function __construct(Question $question, array $changes = []) {
        $this->question = $question;
        $this->changes = $changes;
        $this->occurred_at = new \DateTimeImmutable('now', wp_timezone());
    }
    
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function get_event_name(): string {
        return 'question.updated';
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
            'changes' => $this->changes
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
     * @return Question The updated question.
     */
    public function get_question(): Question {
        return $this->question;
    }
    
    /**
     * Get changes.
     *
     * @return array Changed fields.
     */
    public function get_changes(): array {
        return $this->changes;
    }
}