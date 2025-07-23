<?php
/**
 * Entity Exception
 *
 * Exception thrown by domain entities.
 *
 * @package MoneyQuiz\Domain\Exceptions
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Exceptions;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Entity exception class.
 *
 * @since 7.0.0
 */
class EntityException extends \DomainException {
    
    /**
     * Entity type that caused the exception.
     *
     * @var string
     */
    private string $entity_type;
    
    /**
     * Entity ID if available.
     *
     * @var int|null
     */
    private ?int $entity_id;
    
    /**
     * Constructor.
     *
     * @param string         $message     Error message.
     * @param string         $entity_type Entity type.
     * @param int|null       $entity_id   Entity ID.
     * @param int            $code        Error code.
     * @param \Throwable|null $previous   Previous exception.
     */
    public function __construct(
        string $message,
        string $entity_type = '',
        ?int $entity_id = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->entity_type = $entity_type;
        $this->entity_id = $entity_id;
    }
    
    /**
     * Get entity type.
     *
     * @return string Entity type.
     */
    public function get_entity_type(): string {
        return $this->entity_type;
    }
    
    /**
     * Get entity ID.
     *
     * @return int|null Entity ID or null.
     */
    public function get_entity_id(): ?int {
        return $this->entity_id;
    }
}