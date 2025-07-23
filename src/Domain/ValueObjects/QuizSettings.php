<?php
/**
 * Quiz Settings Value Object
 *
 * Immutable value object for quiz configuration.
 *
 * @package MoneyQuiz\Domain\ValueObjects
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\ValueObjects;

use MoneyQuiz\Domain\Exceptions\ValueObjectException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz settings value object class.
 *
 * @since 7.0.0
 */
final class QuizSettings {
    
    /**
     * Time limit in minutes (0 = unlimited).
     *
     * @var int
     */
    private int $time_limit;
    
    /**
     * Whether to randomize questions.
     *
     * @var bool
     */
    private bool $randomize_questions;
    
    /**
     * Whether to randomize answers.
     *
     * @var bool
     */
    private bool $randomize_answers;
    
    /**
     * Whether to show progress.
     *
     * @var bool
     */
    private bool $show_progress;
    
    /**
     * Whether to allow going back.
     *
     * @var bool
     */
    private bool $allow_back;
    
    /**
     * Whether to show results immediately.
     *
     * @var bool
     */
    private bool $immediate_results;
    
    /**
     * Passing score percentage.
     *
     * @var int
     */
    private int $passing_score;
    
    /**
     * Maximum attempts allowed (0 = unlimited).
     *
     * @var int
     */
    private int $max_attempts;
    
    /**
     * Custom CSS classes.
     *
     * @var string
     */
    private string $css_classes;
    
    /**
     * Constructor.
     *
     * @param int    $time_limit          Time limit in minutes.
     * @param bool   $randomize_questions Whether to randomize questions.
     * @param bool   $randomize_answers   Whether to randomize answers.
     * @param bool   $show_progress       Whether to show progress.
     * @param bool   $allow_back          Whether to allow going back.
     * @param bool   $immediate_results   Whether to show results immediately.
     * @param int    $passing_score       Passing score percentage.
     * @param int    $max_attempts        Maximum attempts allowed.
     * @param string $css_classes         Custom CSS classes.
     */
    public function __construct(
        int $time_limit = 0,
        bool $randomize_questions = false,
        bool $randomize_answers = false,
        bool $show_progress = true,
        bool $allow_back = true,
        bool $immediate_results = true,
        int $passing_score = 0,
        int $max_attempts = 0,
        string $css_classes = ''
    ) {
        $this->time_limit = $time_limit;
        $this->randomize_questions = $randomize_questions;
        $this->randomize_answers = $randomize_answers;
        $this->show_progress = $show_progress;
        $this->allow_back = $allow_back;
        $this->immediate_results = $immediate_results;
        $this->passing_score = $passing_score;
        $this->max_attempts = $max_attempts;
        $this->css_classes = $css_classes;
        
        $this->validate();
    }
    
    /**
     * Validate settings.
     *
     * @throws ValueObjectException If validation fails.
     * @return void
     */
    private function validate(): void {
        if ($this->time_limit < 0) {
            throw new ValueObjectException('Time limit cannot be negative');
        }
        
        if ($this->time_limit > 480) { // 8 hours max
            throw new ValueObjectException('Time limit cannot exceed 480 minutes');
        }
        
        if ($this->passing_score < 0 || $this->passing_score > 100) {
            throw new ValueObjectException('Passing score must be between 0 and 100');
        }
        
        if ($this->max_attempts < 0) {
            throw new ValueObjectException('Max attempts cannot be negative');
        }
        
        if (strlen($this->css_classes) > 200) {
            throw new ValueObjectException('CSS classes must not exceed 200 characters');
        }
    }
    
    /**
     * Get time limit.
     *
     * @return int Time limit in minutes.
     */
    public function get_time_limit(): int {
        return $this->time_limit;
    }
    
    /**
     * Check if questions should be randomized.
     *
     * @return bool True if randomize.
     */
    public function should_randomize_questions(): bool {
        return $this->randomize_questions;
    }
    
    /**
     * Check if answers should be randomized.
     *
     * @return bool True if randomize.
     */
    public function should_randomize_answers(): bool {
        return $this->randomize_answers;
    }
    
    /**
     * Check if progress should be shown.
     *
     * @return bool True if show progress.
     */
    public function should_show_progress(): bool {
        return $this->show_progress;
    }
    
    /**
     * Check if going back is allowed.
     *
     * @return bool True if allow back.
     */
    public function allows_back(): bool {
        return $this->allow_back;
    }
    
    /**
     * Check if results should be shown immediately.
     *
     * @return bool True if immediate results.
     */
    public function shows_immediate_results(): bool {
        return $this->immediate_results;
    }
    
    /**
     * Get passing score.
     *
     * @return int Passing score percentage.
     */
    public function get_passing_score(): int {
        return $this->passing_score;
    }
    
    /**
     * Get max attempts.
     *
     * @return int Max attempts (0 = unlimited).
     */
    public function get_max_attempts(): int {
        return $this->max_attempts;
    }
    
    /**
     * Get CSS classes.
     *
     * @return string CSS classes.
     */
    public function get_css_classes(): string {
        return $this->css_classes;
    }
    
    /**
     * Convert to array.
     *
     * @return array Settings data.
     */
    public function to_array(): array {
        return [
            'time_limit' => $this->time_limit,
            'randomize_questions' => $this->randomize_questions,
            'randomize_answers' => $this->randomize_answers,
            'show_progress' => $this->show_progress,
            'allow_back' => $this->allow_back,
            'immediate_results' => $this->immediate_results,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'css_classes' => $this->css_classes
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Settings data.
     * @return self New instance.
     */
    public static function from_array(array $data): self {
        return new self(
            $data['time_limit'] ?? 0,
            $data['randomize_questions'] ?? false,
            $data['randomize_answers'] ?? false,
            $data['show_progress'] ?? true,
            $data['allow_back'] ?? true,
            $data['immediate_results'] ?? true,
            $data['passing_score'] ?? 0,
            $data['max_attempts'] ?? 0,
            $data['css_classes'] ?? ''
        );
    }
}