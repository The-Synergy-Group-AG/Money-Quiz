<?php
/**
 * Result Entity
 *
 * Represents a quiz result with archetype and recommendations.
 *
 * @package MoneyQuiz\Domain\Entities
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\Events\ResultCalculated;
use MoneyQuiz\Domain\Events\ArchetypeAssigned;
use MoneyQuiz\Domain\Exceptions\EntityException;
use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\ValueObjects\Recommendation;
use MoneyQuiz\Domain\Serializers\ResultSerializer;
use MoneyQuiz\Domain\Traits\ResultMethods;
use MoneyQuiz\Domain\Contracts\ResultInterface;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result entity class.
 *
 * @since 7.0.0
 */
class Result extends Entity implements ResultInterface {
    
    use ResultMethods;
    
    /**
     * Attempt ID this result belongs to.
     *
     * @var int
     */
    private int $attempt_id;
    
    /**
     * Quiz ID.
     *
     * @var int
     */
    private int $quiz_id;
    
    /**
     * User ID.
     *
     * @var int
     */
    private int $user_id;
    
    /**
     * Calculated score.
     *
     * @var Score
     */
    private Score $score;
    
    /**
     * Assigned archetype.
     *
     * @var Archetype|null
     */
    private ?Archetype $archetype = null;
    
    /**
     * Archetype ID.
     *
     * @var int|null
     */
    private ?int $archetype_id = null;
    
    /**
     * Recommendations.
     *
     * @var array<Recommendation>
     */
    private array $recommendations = [];
    
    /**
     * Calculation timestamp.
     *
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $calculated_at;
    
    /**
     * Additional metadata.
     *
     * @var array
     */
    private array $metadata;
    
    /**
     * Constructor.
     *
     * @param int   $attempt_id Attempt ID.
     * @param int   $quiz_id    Quiz ID.
     * @param int   $user_id    User ID.
     * @param Score $score      Calculated score.
     * @param array $metadata   Additional metadata.
     */
    public function __construct(
        int $attempt_id,
        int $quiz_id,
        int $user_id,
        Score $score,
        array $metadata = []
    ) {
        $this->attempt_id = $attempt_id;
        $this->quiz_id = $quiz_id;
        $this->user_id = $user_id;
        $this->score = $score;
        $this->metadata = $metadata;
        $this->calculated_at = new \DateTimeImmutable('now', wp_timezone());
        
        $this->validate();
        $this->update_timestamps(true);
        
        $this->record_event(new ResultCalculated($this));
    }
    
    /**
     * Assign archetype to result.
     *
     * @param Archetype $archetype The archetype.
     * @return void
     */
    public function assign_archetype(Archetype $archetype): void {
        $this->archetype = $archetype;
        $this->archetype_id = $archetype->get_id();
        
        // Generate recommendations based on archetype
        $this->recommendations = $archetype->generate_recommendations($this->score);
        
        $this->update_timestamps();
        
        $this->record_event(new ArchetypeAssigned($this, $archetype));
    }
    
    /**
     * Convert to array.
     *
     * @return array Result data.
     */
    public function to_array(): array {
        return ResultSerializer::to_array($this);
    }
    
    /**
     * Create from array.
     *
     * @param array $data Result data.
     * @return static Result instance.
     */
    public static function from_array(array $data): self {
        return ResultSerializer::from_array($data);
    }
    
}