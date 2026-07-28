<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Internal;

use DateTimeImmutable;
use IdemFlow\Core\OperationClaim;
use RuntimeException;
use Throwable;

final class CallbackExecutionFailed extends RuntimeException {
	public function __construct(
		public readonly OperationClaim $claim,
		public readonly DateTimeImmutable $failedAt,
		Throwable $previous,
	) {
		parent::__construct('Operation callback failed.', previous: $previous);
	}
}
