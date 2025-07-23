<?php
/**
 * Value Object Exception
 *
 * Exception thrown by value objects.
 *
 * @package MoneyQuiz\Domain\Exceptions
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Exceptions;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Value object exception class.
 *
 * @since 7.0.0
 */
class ValueObjectException extends \DomainException {
    
    /**
     * Field that caused the exception.
     *
     * @var string
     */
    private string $field;
    
    /**
     * Invalid value.
     *
     * @var mixed
     */
    private $invalid_value;
    
    /**
     * Constructor.
     *
     * @param string         $message       Error message.
     * @param string         $field         Field name.
     * @param mixed          $invalid_value Invalid value.
     * @param int            $code          Error code.
     * @param \Throwable|null $previous     Previous exception.
     */
    public function __construct(
        string $message,
        string $field = '',
        $invalid_value = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->field = $field;
        $this->invalid_value = $invalid_value;
    }
    
    /**
     * Get field name.
     *
     * @return string Field name.
     */
    public function get_field(): string {
        return $this->field;
    }
    
    /**
     * Get invalid value.
     *
     * @return mixed Invalid value.
     */
    public function get_invalid_value() {
        return $this->invalid_value;
    }
}