<?php
/**
 * Attempt Entity
 *
 * Represents a quiz attempt by a user.
 *
 * @package MoneyQuiz\Domain\Entities
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\Serializers\AttemptSerializer;
use MoneyQuiz\Domain\Traits\AttemptMethods;
use MoneyQuiz\Domain\Helpers\AttemptInitializer;
use MoneyQuiz\Domain\Contracts\AttemptInterface;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt entity class.
 *
 * @since 7.0.0
 */
class Attempt extends Entity implements AttemptInterface {
    use AttemptMethods;
    
    /** @var int Quiz ID. */
    private int $quiz_id;
    
    /** @var int|null User ID (null for anonymous). */
    private ?int $user_id;
    
    /** @var string|null User email (for anonymous users). */
    private ?string $user_email;
    
    /** @var string Attempt status. */
    private string $status;
    
    /** @var \DateTimeInterface Started timestamp. */
    private \DateTimeInterface $started_at;
    
    /** @var \DateTimeInterface|null Completed timestamp. */
    private ?\DateTimeInterface $completed_at = null;
    
    /** @var array Answers submitted. */
    private array $answers = [];
    
    /** @var array Questions for this attempt. */
    private array $questions = [];
    
    /** @var int|null Time taken in seconds. */
    private ?int $time_taken = null;
    
    /** @var int|null Result ID if completed. */
    private ?int $result_id = null;
    
    /** @var string|null Session token for anonymous users. */
    private ?string $session_token = null;
    
    /** @var string|null IP address. */
    private ?string $ip_address = null;
    
    /** @var string|null User agent. */
    private ?string $user_agent = null;
    
    /** @var array Additional metadata. */
    private array $metadata;
    
    /**
     * Constructor.
     *
     * @param int         $quiz_id    Quiz ID.
     * @param int|null    $user_id    User ID or null for anonymous.
     * @param string|null $user_email User email for anonymous users.
     * @param array       $questions  Questions for this attempt.
     * @param array       $metadata   Additional metadata.
     */
    public function __construct(
        int $quiz_id,
        ?int $user_id = null,
        ?string $user_email = null,
        array $questions = [],
        array $metadata = []
    ) {
        AttemptInitializer::initialize($this, $quiz_id, $user_id, $user_email, $questions, $metadata);
    }
    
    /**
     * Convert to array.
     *
     * @return array Attempt data.
     */
    public function to_array(): array {
        return AttemptSerializer::to_array($this);
    }
    
    /**
     * Create from array.
     *
     * @param array $data Attempt data.
     * @return static Attempt instance.
     */
    public static function from_array(array $data): self {
        return AttemptSerializer::from_array($data);
    }
}