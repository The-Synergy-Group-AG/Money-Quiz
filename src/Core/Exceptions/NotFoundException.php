<?php
/**
 * Not Found Exception
 *
 * @package MoneyQuiz\Core\Exceptions
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Not found exception class.
 *
 * @since 7.0.0
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface {
}