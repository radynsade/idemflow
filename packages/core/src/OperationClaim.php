<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use DateTimeImmutable;
use IdemFlow\Core\Exception\InvalidOperationException;

final readonly class OperationClaim {
	public function __construct(
		private OperationIdentity $identity,
		private string $ownerId,
		private int $attempt,
		private DateTimeImmutable $startedAt,
	) {
		if ($ownerId === '') {
			throw new InvalidOperationException('Claim owner ID must be non-empty.');
		}

		if ($attempt < 1) {
			throw new InvalidOperationException('Claim attempt must be greater than zero.');
		}
	}

	public function attempt(): int {
		return $this->attempt;
	}

	public function identity(): OperationIdentity {
		return $this->identity;
	}

	public function ownerId(): string {
		return $this->ownerId;
	}

	public function startedAt(): DateTimeImmutable {
		return $this->startedAt;
	}
}
