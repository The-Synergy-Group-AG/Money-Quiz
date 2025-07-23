<?php
/**
 * Answer Value Object
 *
 * Immutable value object representing a question answer.
 *
 * @package MoneyQuiz\Domain\ValueObjects
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\ValueObjects;

use MoneyQuiz\Domain\Exceptions\ValueObjectException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Answer value object class.
 *
 * @since 7.0.0
 */
final class Answer {
    
    /**
     * Answer ID.
     *
     * @var string
     */
    private string $id;
    
    /**
     * Answer text.
     *
     * @var string
     */
    private string $text;
    
    /**
     * Answer value/score.
     *
     * @var int
     */
    private int $value;
    
    /**
     * Display order.
     *
     * @var int
     */
    private int $order;
    
    /**
     * Whether this is correct answer (for quiz types).
     *
     * @var bool
     */
    private bool $is_correct;
    
    /**
     * Additional metadata.
     *
     * @var array
     */
    private array $metadata;
    
    /**
     * Constructor.
     *
     * @param string $text       Answer text.
     * @param int    $value      Answer value/score.
     * @param int    $order      Display order.
     * @param bool   $is_correct Whether correct answer.
     * @param array  $metadata   Additional metadata.
     * @param string $id         Answer ID (auto-generated if empty).
     */
    public function __construct(
        string $text,
        int $value = 0,
        int $order = 0,
        bool $is_correct = false,
        array $metadata = [],
        string $id = ''
    ) {
        $this->id = $id ?: $this->generate_id();
        $this->text = $text;
        $this->value = $value;
        $this->order = $order;
        $this->is_correct = $is_correct;
        $this->metadata = $metadata;
        
        $this->validate();
    }
    
    /**
     * Generate unique answer ID.
     *
     * @return string Generated ID.
     */
    private function generate_id(): string {
        return 'ans_' . wp_generate_password(12, false);
    }
    
    /**
     * Validate answer.
     *
     * @throws ValueObjectException If validation fails.
     * @return void
     */
    private function validate(): void {
        if (empty($this->text)) {
            throw new ValueObjectException('Answer text is required');
        }
        
        if (strlen($this->text) > 500) {
            throw new ValueObjectException('Answer text must not exceed 500 characters');
        }
        
        if ($this->value < -1000 || $this->value > 1000) {
            throw new ValueObjectException('Answer value must be between -1000 and 1000');
        }
        
        if ($this->order < 0) {
            throw new ValueObjectException('Answer order must be non-negative');
        }
    }
    
    /**
     * Get answer ID.
     *
     * @return string Answer ID.
     */
    public function get_id(): string {
        return $this->id;
    }
    
    /**
     * Get answer text.
     *
     * @return string Answer text.
     */
    public function get_text(): string {
        return $this->text;
    }
    
    /**
     * Get answer value.
     *
     * @return int Answer value.
     */
    public function get_value(): int {
        return $this->value;
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
     * Check if correct answer.
     *
     * @return bool True if correct.
     */
    public function is_correct(): bool {
        return $this->is_correct;
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
     * Create copy with new text.
     *
     * @param string $text New text.
     * @return self New instance.
     */
    public function with_text(string $text): self {
        return new self(
            $text,
            $this->value,
            $this->order,
            $this->is_correct,
            $this->metadata,
            $this->id
        );
    }
    
    /**
     * Create copy with new value.
     *
     * @param int $value New value.
     * @return self New instance.
     */
    public function with_value(int $value): self {
        return new self(
            $this->text,
            $value,
            $this->order,
            $this->is_correct,
            $this->metadata,
            $this->id
        );
    }
    
    /**
     * Convert to array.
     *
     * @return array Answer data.
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'value' => $this->value,
            'order' => $this->order,
            'is_correct' => $this->is_correct,
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Answer data.
     * @return self New instance.
     */
    public static function from_array(array $data): self {
        return new self(
            $data['text'] ?? '',
            $data['value'] ?? 0,
            $data['order'] ?? 0,
            $data['is_correct'] ?? false,
            $data['metadata'] ?? [],
            $data['id'] ?? ''
        );
    }
    
    /**
     * Check equality.
     *
     * @param Answer $other Other answer.
     * @return bool True if equal.
     */
    public function equals(Answer $other): bool {
        return $this->id === $other->id
            && $this->text === $other->text
            && $this->value === $other->value
            && $this->is_correct === $other->is_correct;
    }
}