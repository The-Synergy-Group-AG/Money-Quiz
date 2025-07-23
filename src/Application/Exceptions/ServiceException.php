<?php
/**
 * Service Exception
 *
 * Exception thrown by application services.
 *
 * @package MoneyQuiz\Application\Exceptions
 * @since   7.0.0
 */

namespace MoneyQuiz\Application\Exceptions;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Service exception class.
 *
 * @since 7.0.0
 */
class ServiceException extends \RuntimeException {
    
    /**
     * Service that threw the exception.
     *
     * @var string
     */
    private string $service_name;
    
    /**
     * Operation that failed.
     *
     * @var string
     */
    private string $operation;
    
    /**
     * Additional context data.
     *
     * @var array
     */
    private array $context;
    
    /**
     * Constructor.
     *
     * @param string         $message      Error message.
     * @param int            $code         Error code.
     * @param \Throwable|null $previous    Previous exception.
     * @param string         $service_name Service name.
     * @param string         $operation    Operation name.
     * @param array          $context      Additional context.
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        string $service_name = '',
        string $operation = '',
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->service_name = $service_name;
        $this->operation = $operation;
        $this->context = $context;
    }
    
    /**
     * Get service name.
     *
     * @return string Service name.
     */
    public function get_service_name(): string {
        return $this->service_name;
    }
    
    /**
     * Get operation name.
     *
     * @return string Operation name.
     */
    public function get_operation(): string {
        return $this->operation;
    }
    
    /**
     * Get context data.
     *
     * @return array Context data.
     */
    public function get_context(): array {
        return $this->context;
    }
}