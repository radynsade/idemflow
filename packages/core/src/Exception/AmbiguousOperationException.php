<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use IdemFlow\Core\OperationIdentity;

final class AmbiguousOperationException extends IdemFlowException {
	public function __construct(
		private readonly OperationIdentity $identity,
		private readonly int $attempt,
	) {
		parent::__construct(sprintf(
			'The outcome of operation "%s" (%s) is ambiguous.',
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
}
