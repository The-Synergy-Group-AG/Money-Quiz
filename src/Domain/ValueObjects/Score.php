<?php
/**
 * Score Value Object
 *
 * Immutable value object representing a quiz score.
 *
 * @package MoneyQuiz\Domain\ValueObjects
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\ValueObjects;

use MoneyQuiz\Domain\Exceptions\ValueObjectException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Score value object class.
 *
 * @since 7.0.0
 */
final class Score {
    
    /**
     * Total score points.
     *
     * @var int
     */
    private int $total;
    
    /**
     * Maximum possible score.
     *
     * @var int
     */
    private int $max_score;
    
    /**
     * Score breakdown by category/dimension.
     *
     * @var array<string, int>
     */
    private array $breakdown;
    
    /**
     * Percentage score.
     *
     * @var float
     */
    private float $percentage;
    
    /**
     * Constructor.
     *
     * @param int   $total     Total score.
     * @param int   $max_score Maximum possible score.
     * @param array $breakdown Score breakdown by dimension.
     */
    public function __construct(int $total, int $max_score, array $breakdown = []) {
        $this->total = $total;
        $this->max_score = $max_score;
        $this->breakdown = $breakdown;
        $this->percentage = $max_score > 0 ? round(($total / $max_score) * 100, 2) : 0;
        
        $this->validate();
    }
    
    /**
     * Validate score.
     *
     * @throws ValueObjectException If validation fails.
     * @return void
     */
    private function validate(): void {
        if ($this->total < 0) {
            throw new ValueObjectException('Total score cannot be negative');
        }
        
        if ($this->max_score < 0) {
            throw new ValueObjectException('Maximum score cannot be negative');
        }
        
        if ($this->total > $this->max_score) {
            throw new ValueObjectException('Total score cannot exceed maximum score');
        }
        
        foreach ($this->breakdown as $dimension => $score) {
            if (!is_string($dimension) || empty($dimension)) {
                throw new ValueObjectException('Invalid dimension name in breakdown');
            }
            if ($score < 0) {
                throw new ValueObjectException("Score for dimension '{$dimension}' cannot be negative");
            }
        }
    }
    
    /**
     * Get total score.
     *
     * @return int Total score.
     */
    public function get_total(): int {
        return $this->total;
    }
    
    /**
     * Get maximum score.
     *
     * @return int Maximum score.
     */
    public function get_max_score(): int {
        return $this->max_score;
    }
    
    /**
     * Get score breakdown.
     *
     * @return array<string, int> Breakdown by dimension.
     */
    public function get_breakdown(): array {
        return $this->breakdown;
    }
    
    /**
     * Get percentage score.
     *
     * @return float Percentage (0-100).
     */
    public function get_percentage(): float {
        return $this->percentage;
    }
    
    /**
     * Get score for specific dimension.
     *
     * @param string $dimension Dimension name.
     * @return int Dimension score or 0 if not found.
     */
    public function get_dimension_score(string $dimension): int {
        return $this->breakdown[$dimension] ?? 0;
    }
    
    /**
     * Check if score meets threshold.
     *
     * @param int $threshold Threshold to check.
     * @return bool True if score meets or exceeds threshold.
     */
    public function meets_threshold(int $threshold): bool {
        return $this->total >= $threshold;
    }
    
    /**
     * Check if percentage meets threshold.
     *
     * @param float $threshold Percentage threshold (0-100).
     * @return bool True if percentage meets or exceeds threshold.
     */
    public function meets_percentage_threshold(float $threshold): bool {
        return $this->percentage >= $threshold;
    }
    
    /**
     * Add dimension score.
     *
     * @param string $dimension Dimension name.
     * @param int    $score     Score to add.
     * @return self New Score instance.
     */
    public function with_dimension(string $dimension, int $score): self {
        $new_breakdown = $this->breakdown;
        $new_breakdown[$dimension] = $score;
        
        return new self($this->total, $this->max_score, $new_breakdown);
    }
    
    /**
     * Convert to array.
     *
     * @return array Score data.
     */
    public function to_array(): array {
        return [
            'total' => $this->total,
            'max_score' => $this->max_score,
            'percentage' => $this->percentage,
            'breakdown' => $this->breakdown
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Score data.
     * @return self New instance.
     */
    public static function from_array(array $data): self {
        return new self(
            $data['total'] ?? 0,
            $data['max_score'] ?? 100,
            $data['breakdown'] ?? []
        );
    }
    
    /**
     * Check equality.
     *
     * @param Score $other Other score.
     * @return bool True if equal.
     */
    public function equals(Score $other): bool {
        return $this->total === $other->total
            && $this->max_score === $other->max_score
            && $this->breakdown === $other->breakdown;
    }
}