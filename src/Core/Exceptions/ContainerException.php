<?php
/**
 * Container Exception
 *
 * @package MoneyQuiz\Core\Exceptions
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Container exception class.
 *
 * @since 7.0.0
 */
class ContainerException extends \Exception implements ContainerExceptionInterface {
}