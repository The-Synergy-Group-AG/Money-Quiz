<?php
/**
 * Archetype Entity
 *
 * Represents a money personality archetype.
 *
 * @package MoneyQuiz\Domain\Entities
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\Exceptions\EntityException;
use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\ValueObjects\Recommendation;
use MoneyQuiz\Domain\ValueObjects\ArchetypeCriteria;
use MoneyQuiz\Domain\Serializers\ArchetypeSerializer;
use MoneyQuiz\Domain\Traits\ArchetypeMethods;
use MoneyQuiz\Domain\Services\RecommendationGenerator;
use MoneyQuiz\Domain\Contracts\ArchetypeInterface;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype entity class.
 *
 * @since 7.0.0
 */
class Archetype extends Entity implements ArchetypeInterface {
    use ArchetypeMethods;
    
    /**
     * Archetype name.
     *
     * @var string
     */
    private string $name;
    
    /**
     * Archetype slug.
     *
     * @var string
     */
    private string $slug;
    
    /**
     * Archetype description.
     *
     * @var string
     */
    private string $description;
    
    /**
     * Archetype characteristics.
     *
     * @var array<string>
     */
    private array $characteristics;
    
    /**
     * Matching criteria.
     *
     * @var ArchetypeCriteria
     */
    private ArchetypeCriteria $criteria;
    
    /**
     * Recommendation templates.
     *
     * @var array
     */
    private array $recommendation_templates;
    
    /**
     * Display order.
     *
     * @var int
     */
    private int $order;
    
    /**
     * Whether archetype is active.
     *
     * @var bool
     */
    private bool $is_active;
    
    /**
     * Constructor.
     *
     * @param string            $name                    Archetype name.
     * @param string            $slug                    URL-friendly slug.
     * @param string            $description             Detailed description.
     * @param array             $characteristics         Key characteristics.
     * @param ArchetypeCriteria $criteria                Matching criteria.
     * @param array             $recommendation_templates Recommendation templates.
     * @param int               $order                   Display order.
     * @param bool              $is_active               Whether active.
     */
    public function __construct(
        string $name,
        string $slug,
        string $description,
        array $characteristics,
        ArchetypeCriteria $criteria,
        array $recommendation_templates = [],
        int $order = 0,
        bool $is_active = true
    ) {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->characteristics = $characteristics;
        $this->criteria = $criteria;
        $this->recommendation_templates = $recommendation_templates;
        $this->order = $order;
        $this->is_active = $is_active;
        
        $this->validate();
        $this->update_timestamps(true);
    }
    
    /**
     * Generate recommendations for a score.
     *
     * @param Score $score The quiz score.
     * @return array<Recommendation> Generated recommendations.
     */
    public function generate_recommendations(Score $score): array {
        return RecommendationGenerator::generate($this, $score);
    }
    
    /**
     * Convert to array.
     *
     * @return array Archetype data.
     */
    public function to_array(): array {
        return ArchetypeSerializer::to_array($this);
    }
    
    /**
     * Create from array.
     *
     * @param array $data Archetype data.
     * @return static Archetype instance.
     */
    public static function from_array(array $data): self {
        return ArchetypeSerializer::from_array($data);
    }
}