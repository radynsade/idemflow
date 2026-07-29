<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use RuntimeException;

/**
 * Base exception for durable operation execution failures reported by IdemFlow.
 */
abstract class IdemFlowException extends RuntimeException {
}
