<?php
/**
 * Question Entity
 *
 * Represents a quiz question with answers.
 *
 * @package MoneyQuiz\Domain\Entities
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\Events\QuestionCreated;
use MoneyQuiz\Domain\Events\QuestionUpdated;
use MoneyQuiz\Domain\Exceptions\EntityException;
use MoneyQuiz\Domain\ValueObjects\Answer;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Question entity class.
 *
 * @since 7.0.0
 */
class Question extends Entity {
    
    /**
     * Question types.
     */
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_SCALE = 'scale';
    public const TYPE_MATRIX = 'matrix';
    
    /**
     * Valid question types.
     *
     * @var array<string>
     */
    private const VALID_TYPES = [
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_TRUE_FALSE,
        self::TYPE_SCALE,
        self::TYPE_MATRIX
    ];
    
    /**
     * Quiz ID.
     *
     * @var int
     */
    private int $quiz_id;
    
    /**
     * Question text.
     *
     * @var string
     */
    private string $text;
    
    /**
     * Question type.
     *
     * @var string
     */
    private string $type;
    
    /**
     * Available answers.
     *
     * @var array<Answer>
     */
    private array $answers;
    
    /**
     * Points value.
     *
     * @var int
     */
    private int $points;
    
    /**
     * Display order.
     *
     * @var int
     */
    private int $order;
    
    /**
     * Whether question is required.
     *
     * @var bool
     */
    private bool $required;
    
    /**
     * Additional metadata.
     *
     * @var array
     */
    private array $metadata;
    
    /**
     * Constructor.
     *
     * @param int          $quiz_id  Quiz ID.
     * @param string       $text     Question text.
     * @param string       $type     Question type.
     * @param array<Answer> $answers Available answers.
     * @param int          $points   Points value.
     * @param int          $order    Display order.
     * @param bool         $required Whether required.
     * @param array        $metadata Additional metadata.
     */
    public function __construct(
        int $quiz_id,
        string $text,
        string $type,
        array $answers,
        int $points = 0,
        int $order = 0,
        bool $required = true,
        array $metadata = []
    ) {
        $this->quiz_id = $quiz_id;
        $this->text = $text;
        $this->type = $type;
        $this->answers = $answers;
        $this->points = $points;
        $this->order = $order;
        $this->required = $required;
        $this->metadata = $metadata;
        
        $this->validate();
        $this->update_timestamps(true);
        
        $this->record_event(new QuestionCreated($this));
    }
    
    /**
     * Update question.
     *
     * @param string       $text    New question text.
     * @param array<Answer> $answers New answers.
     * @return void
     */
    public function update(string $text, array $answers): void {
        $old_text = $this->text;
        $old_answers = $this->answers;
        
        $this->text = $text;
        $this->answers = $answers;
        
        $this->validate();
        $this->update_timestamps();
        
        $this->record_event(new QuestionUpdated($this, [
            'text' => [$old_text, $text],
            'answers' => [count($old_answers), count($answers)]
        ]));
    }
    
    /**
     * Update display order.
     *
     * @param int $order New order.
     * @return void
     */
    public function set_order(int $order): void {
        if ($order < 0) {
            throw new EntityException('Order must be non-negative');
        }
        
        $this->order = $order;
        $this->update_timestamps();
    }
    
    /**
     * Get quiz ID.
     *
     * @return int Quiz ID.
     */
    public function get_quiz_id(): int {
        return $this->quiz_id;
    }
    
    /**
     * Get question text.
     *
     * @return string Question text.
     */
    public function get_text(): string {
        return $this->text;
    }
    
    /**
     * Get question type.
     *
     * @return string Question type.
     */
    public function get_type(): string {
        return $this->type;
    }
    
    /**
     * Get answers.
     *
     * @return array<Answer> Answers.
     */
    public function get_answers(): array {
        return $this->answers;
    }
    
    /**
     * Get points.
     *
     * @return int Points value.
     */
    public function get_points(): int {
        return $this->points;
    }
    
    /**
     * Get display order.
     *
     * @return int Order.
     */
    public function get_order(): int {
        return $this->order;
    }
    
    /**
     * Check if required.
     *
     * @return bool True if required.
     */
    public function is_required(): bool {
        return $this->required;
    }
    
    /**
     * Get metadata.
     *
     * @return array Metadata.
     */
    public function get_metadata(): array {
        return $this->metadata;
    }
    
    /**
     * Validate entity.
     *
     * @throws EntityException If validation fails.
     * @return void
     */
    public function validate(): void {
        if ($this->quiz_id <= 0) {
            throw new EntityException('Invalid quiz ID');
        }
        
        if (empty($this->text)) {
            throw new EntityException('Question text is required');
        }
        
        if (strlen($this->text) > 1000) {
            throw new EntityException('Question text must not exceed 1000 characters');
        }
        
        if (!in_array($this->type, self::VALID_TYPES, true)) {
            throw new EntityException("Invalid question type: {$this->type}");
        }
        
        if (empty($this->answers)) {
            throw new EntityException('Question must have at least one answer');
        }
        
        // Validate based on type
        $this->validate_by_type();
        
        if ($this->points < 0 || $this->points > 100) {
            throw new EntityException('Points must be between 0 and 100');
        }
        
        if ($this->order < 0) {
            throw new EntityException('Order must be non-negative');
        }
    }
    
    /**
     * Validate based on question type.
     *
     * @throws EntityException If type-specific validation fails.
     * @return void
     */
    private function validate_by_type(): void {
        switch ($this->type) {
            case self::TYPE_TRUE_FALSE:
                if (count($this->answers) !== 2) {
                    throw new EntityException('True/false questions must have exactly 2 answers');
                }
                break;
                
            case self::TYPE_MULTIPLE_CHOICE:
                if (count($this->answers) < 2) {
                    throw new EntityException('Multiple choice questions must have at least 2 answers');
                }
                if (count($this->answers) > 10) {
                    throw new EntityException('Multiple choice questions cannot have more than 10 answers');
                }
                break;
                
            case self::TYPE_SCALE:
                if (count($this->answers) < 3 || count($this->answers) > 10) {
                    throw new EntityException('Scale questions must have 3-10 answers');
                }
                break;
        }
    }
    
    /**
     * Convert to array.
     *
     * @return array Question data.
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'text' => $this->text,
            'type' => $this->type,
            'answers' => array_map(fn($answer) => $answer->to_array(), $this->answers),
            'points' => $this->points,
            'order' => $this->order,
            'required' => $this->required,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Question data.
     * @return static Question instance.
     */
    public static function from_array(array $data): self {
        $answers = array_map(
            fn($answer_data) => Answer::from_array($answer_data),
            $data['answers'] ?? []
        );
        
        $question = new self(
            $data['quiz_id'],
            $data['text'],
            $data['type'],
            $answers,
            $data['points'] ?? 0,
            $data['order'] ?? 0,
            $data['required'] ?? true,
            $data['metadata'] ?? []
        );
        
        // Set persisted properties
        if (isset($data['id'])) {
            $question->id = (int) $data['id'];
        }
        
        if (isset($data['created_at'])) {
            $question->created_at = new \DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $question->updated_at = new \DateTimeImmutable($data['updated_at']);
        }
        
        return $question;
    }
}