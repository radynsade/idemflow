<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store\Claim;

use DateTimeImmutable;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\Store\ClaimDecision;

final readonly class InProgress implements ClaimDecision {
	public function __construct(
		private OperationIdentity $identity,
		private int $attempt,
		private DateTimeImmutable $startedAt,
	) {
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
