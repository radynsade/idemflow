<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use DateTimeImmutable;
use IdemFlow\Core\OperationIdentity;

/**
 * Thrown when another owner is currently executing the operation.
 */
final class OperationInProgressException extends IdemFlowException {
	public function __construct(
		private readonly OperationIdentity $identity,
		private readonly int $attempt,
		private readonly DateTimeImmutable $startedAt,
	) {
		parent::__construct(sprintf(
			'Operation "%s" (%s) is already in progress.',
			$identity->scope(),
			$identity->keyHash(),
		));
	}

	public function attempt(): int {
		return $this->attempt;
	}

	public function identity(): OperationIdentity {
		return $this->identity;
	}

	public function startedAt(): DateTimeImmutable {
		return $this->startedAt;
	}
}
