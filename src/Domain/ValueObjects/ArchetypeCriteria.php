<?php
/**
 * Archetype Criteria Value Object
 *
 * Immutable value object for archetype matching criteria.
 *
 * @package MoneyQuiz\Domain\ValueObjects
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\ValueObjects;

use MoneyQuiz\Domain\Exceptions\ValueObjectException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype criteria value object class.
 *
 * @since 7.0.0
 */
final class ArchetypeCriteria {
    
    /**
     * Minimum total score.
     *
     * @var int|null
     */
    private ?int $min_score;
    
    /**
     * Maximum total score.
     *
     * @var int|null
     */
    private ?int $max_score;
    
    /**
     * Minimum percentage.
     *
     * @var float|null
     */
    private ?float $min_percentage;
    
    /**
     * Maximum percentage.
     *
     * @var float|null
     */
    private ?float $max_percentage;
    
    /**
     * Dimension criteria.
     *
     * @var array<string, array>
     */
    private array $dimension_criteria;
    
    /**
     * Answer pattern requirements.
     *
     * @var array
     */
    private array $answer_patterns;
    
    /**
     * Constructor.
     *
     * @param int|null   $min_score          Minimum score.
     * @param int|null   $max_score          Maximum score.
     * @param float|null $min_percentage     Minimum percentage.
     * @param float|null $max_percentage     Maximum percentage.
     * @param array      $dimension_criteria Dimension requirements.
     * @param array      $answer_patterns    Answer pattern requirements.
     */
    public function __construct(
        ?int $min_score = null,
        ?int $max_score = null,
        ?float $min_percentage = null,
        ?float $max_percentage = null,
        array $dimension_criteria = [],
        array $answer_patterns = []
    ) {
        $this->min_score = $min_score;
        $this->max_score = $max_score;
        $this->min_percentage = $min_percentage;
        $this->max_percentage = $max_percentage;
        $this->dimension_criteria = $dimension_criteria;
        $this->answer_patterns = $answer_patterns;
        
        $this->validate();
    }
    
    /**
     * Validate criteria.
     *
     * @throws ValueObjectException If validation fails.
     * @return void
     */
    private function validate(): void {
        if ($this->min_score !== null && $this->min_score < 0) {
            throw new ValueObjectException('Minimum score cannot be negative');
        }
        
        if ($this->max_score !== null && $this->max_score < 0) {
            throw new ValueObjectException('Maximum score cannot be negative');
        }
        
        if ($this->min_score !== null && $this->max_score !== null && $this->min_score > $this->max_score) {
            throw new ValueObjectException('Minimum score cannot exceed maximum score');
        }
        
        if ($this->min_percentage !== null && ($this->min_percentage < 0 || $this->min_percentage > 100)) {
            throw new ValueObjectException('Minimum percentage must be between 0 and 100');
        }
        
        if ($this->max_percentage !== null && ($this->max_percentage < 0 || $this->max_percentage > 100)) {
            throw new ValueObjectException('Maximum percentage must be between 0 and 100');
        }
        
        if ($this->min_percentage !== null && $this->max_percentage !== null && $this->min_percentage > $this->max_percentage) {
            throw new ValueObjectException('Minimum percentage cannot exceed maximum percentage');
        }
    }
    
    /**
     * Check if score matches criteria.
     *
     * @param Score $score Score to check.
     * @return bool True if matches.
     */
    public function matches(Score $score): bool {
        // Check total score range
        if ($this->min_score !== null && $score->get_total() < $this->min_score) {
            return false;
        }
        
        if ($this->max_score !== null && $score->get_total() > $this->max_score) {
            return false;
        }
        
        // Check percentage range
        if ($this->min_percentage !== null && $score->get_percentage() < $this->min_percentage) {
            return false;
        }
        
        if ($this->max_percentage !== null && $score->get_percentage() > $this->max_percentage) {
            return false;
        }
        
        // Check dimension criteria
        foreach ($this->dimension_criteria as $dimension => $criteria) {
            $dimension_score = $score->get_dimension_score($dimension);
            
            if (isset($criteria['min']) && $dimension_score < $criteria['min']) {
                return false;
            }
            
            if (isset($criteria['max']) && $dimension_score > $criteria['max']) {
                return false;
            }
        }
        
        // All criteria met
        return true;
    }
    
    /**
     * Get minimum score.
     *
     * @return int|null Minimum score.
     */
    public function get_min_score(): ?int {
        return $this->min_score;
    }
    
    /**
     * Get maximum score.
     *
     * @return int|null Maximum score.
     */
    public function get_max_score(): ?int {
        return $this->max_score;
    }
    
    /**
     * Get minimum percentage.
     *
     * @return float|null Minimum percentage.
     */
    public function get_min_percentage(): ?float {
        return $this->min_percentage;
    }
    
    /**
     * Get maximum percentage.
     *
     * @return float|null Maximum percentage.
     */
    public function get_max_percentage(): ?float {
        return $this->max_percentage;
    }
    
    /**
     * Get dimension criteria.
     *
     * @return array Dimension criteria.
     */
    public function get_dimension_criteria(): array {
        return $this->dimension_criteria;
    }
    
    /**
     * Convert to array.
     *
     * @return array Criteria data.
     */
    public function to_array(): array {
        return [
            'min_score' => $this->min_score,
            'max_score' => $this->max_score,
            'min_percentage' => $this->min_percentage,
            'max_percentage' => $this->max_percentage,
            'dimension_criteria' => $this->dimension_criteria,
            'answer_patterns' => $this->answer_patterns
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Criteria data.
     * @return self New instance.
     */
    public static function from_array(array $data): self {
        return new self(
            $data['min_score'] ?? null,
            $data['max_score'] ?? null,
            $data['min_percentage'] ?? null,
            $data['max_percentage'] ?? null,
            $data['dimension_criteria'] ?? [],
            $data['answer_patterns'] ?? []
        );
    }
}