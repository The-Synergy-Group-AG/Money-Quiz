<?php
/**
 * Quiz Entity
 *
 * Represents a quiz with its questions and settings.
 *
 * @package MoneyQuiz\Domain\Entities
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\Events\QuizCreated;
use MoneyQuiz\Domain\Events\QuizUpdated;
use MoneyQuiz\Domain\Events\QuizPublished;
use MoneyQuiz\Domain\Events\QuizArchived;
use MoneyQuiz\Domain\Exceptions\EntityException;
use MoneyQuiz\Domain\ValueObjects\QuizSettings;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz entity class.
 *
 * @since 7.0.0
 */
class Quiz extends Entity {
    
    /**
     * Quiz statuses.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    
    /**
     * Valid statuses.
     *
     * @var array<string>
     */
    private const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED
    ];
    
    /**
     * Quiz title.
     *
     * @var string
     */
    private string $title;
    
    /**
     * Quiz description.
     *
     * @var string
     */
    private string $description;
    
    /**
     * Quiz status.
     *
     * @var string
     */
    private string $status;
    
    /**
     * Quiz settings.
     *
     * @var QuizSettings
     */
    private QuizSettings $settings;
    
    /**
     * Creator user ID.
     *
     * @var int
     */
    private int $created_by;
    
    /**
     * Associated questions.
     *
     * @var array<Question>
     */
    private array $questions = [];
    
    /**
     * Constructor.
     *
     * @param string       $title       Quiz title.
     * @param string       $description Quiz description.
     * @param int          $created_by  Creator user ID.
     * @param QuizSettings $settings    Quiz settings.
     */
    public function __construct(
        string $title,
        string $description,
        int $created_by,
        ?QuizSettings $settings = null
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->created_by = $created_by;
        $this->status = self::STATUS_DRAFT;
        $this->settings = $settings ?? new QuizSettings();
        
        $this->validate();
        $this->update_timestamps(true);
        
        // Record creation event
        $this->record_event(new QuizCreated($this));
    }
    
    /**
     * Update quiz details.
     *
     * @param string $title       New title.
     * @param string $description New description.
     * @return void
     */
    public function update(string $title, string $description): void {
        $old_title = $this->title;
        $old_description = $this->description;
        
        $this->title = $title;
        $this->description = $description;
        
        $this->validate();
        $this->update_timestamps();
        
        // Record update event
        $this->record_event(new QuizUpdated($this, [
            'title' => [$old_title, $title],
            'description' => [$old_description, $description]
        ]));
    }
    
    /**
     * Publish quiz.
     *
     * @throws EntityException If quiz cannot be published.
     * @return void
     */
    public function publish(): void {
        if ($this->status === self::STATUS_PUBLISHED) {
            return; // Already published
        }
        
        if (empty($this->questions)) {
            throw new EntityException('Cannot publish quiz without questions');
        }
        
        $this->status = self::STATUS_PUBLISHED;
        $this->update_timestamps();
        
        $this->record_event(new QuizPublished($this));
    }
    
    /**
     * Archive quiz.
     *
     * @return void
     */
    public function archive(): void {
        if ($this->status === self::STATUS_ARCHIVED) {
            return; // Already archived
        }
        
        $this->status = self::STATUS_ARCHIVED;
        $this->update_timestamps();
        
        $this->record_event(new QuizArchived($this));
    }
    
    /**
     * Add question to quiz.
     *
     * @param Question $question Question to add.
     * @return void
     */
    public function add_question(Question $question): void {
        $this->questions[] = $question;
        $this->update_timestamps();
    }
    
    /**
     * Get quiz title.
     *
     * @return string Title.
     */
    public function get_title(): string {
        return $this->title;
    }
    
    /**
     * Get quiz description.
     *
     * @return string Description.
     */
    public function get_description(): string {
        return $this->description;
    }
    
    /**
     * Get quiz status.
     *
     * @return string Status.
     */
    public function get_status(): string {
        return $this->status;
    }
    
    /**
     * Get quiz settings.
     *
     * @return QuizSettings Settings.
     */
    public function get_settings(): QuizSettings {
        return $this->settings;
    }
    
    /**
     * Get creator ID.
     *
     * @return int Creator user ID.
     */
    public function get_created_by(): int {
        return $this->created_by;
    }
    
    /**
     * Get questions.
     *
     * @return array<Question> Questions.
     */
    public function get_questions(): array {
        return $this->questions;
    }
    
    /**
     * Validate entity.
     *
     * @throws EntityException If validation fails.
     * @return void
     */
    public function validate(): void {
        if (empty($this->title)) {
            throw new EntityException('Quiz title is required');
        }
        
        if (strlen($this->title) > 200) {
            throw new EntityException('Quiz title must not exceed 200 characters');
        }
        
        if (strlen($this->description) > 1000) {
            throw new EntityException('Quiz description must not exceed 1000 characters');
        }
        
        if (!in_array($this->status, self::VALID_STATUSES, true)) {
            throw new EntityException('Invalid quiz status');
        }
        
        if ($this->created_by <= 0) {
            throw new EntityException('Invalid creator ID');
        }
    }
    
    /**
     * Convert to array.
     *
     * @return array Quiz data.
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'settings' => $this->settings->to_array(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'question_count' => count($this->questions)
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Quiz data.
     * @return static Quiz instance.
     */
    public static function from_array(array $data): self {
        $quiz = new self(
            $data['title'],
            $data['description'],
            $data['created_by'],
            isset($data['settings']) ? QuizSettings::from_array($data['settings']) : null
        );
        
        // Set persisted properties
        if (isset($data['id'])) {
            $quiz->id = (int) $data['id'];
        }
        
        if (isset($data['status'])) {
            $quiz->status = $data['status'];
        }
        
        if (isset($data['created_at'])) {
            $quiz->created_at = new \DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $quiz->updated_at = new \DateTimeImmutable($data['updated_at']);
        }
        
        return $quiz;
    }
}