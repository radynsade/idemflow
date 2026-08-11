<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use IdemFlow\Core\OperationIdentity;

/**
 * Thrown when the operation has a retained failure.
 */
final class OperationPreviouslyFailedException extends IdemFlowException {
	public function __construct(
		private readonly OperationIdentity $identity,
		private readonly int $attempt,
		private readonly bool $retryable,
	) {
		parent::__construct(sprintf(
			'Operation "%s" (%s) has a stored failure.',
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

	public function isRetryable(): bool {
		return $this->retryable;
	}
}
