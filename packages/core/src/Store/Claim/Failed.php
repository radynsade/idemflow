<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store\Claim;

use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\Store\ClaimDecision;

final readonly class Failed implements ClaimDecision {
	public function __construct(
		private OperationIdentity $identity,
		private int $attempt,
		private bool $retryable,
	) {
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
