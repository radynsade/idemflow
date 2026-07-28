<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store\Claim;

use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\Store\ClaimDecision;

final readonly class Ambiguous implements ClaimDecision {
	public function __construct(
		private OperationIdentity $identity,
		private int $attempt,
	) {
	}

	public function attempt(): int {
		return $this->attempt;
	}

	public function identity(): OperationIdentity {
		return $this->identity;
	}
}
