<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use InvalidArgumentException;

/**
 * Thrown when an operation or one of its value objects contains invalid input.
 */
final class InvalidOperationException extends InvalidArgumentException {
}
