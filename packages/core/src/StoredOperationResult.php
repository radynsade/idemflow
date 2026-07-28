<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use DateTimeImmutable;
use IdemFlow\Core\Exception\InvalidOperationException;

final readonly class StoredOperationResult {
	public function __construct(
		private EncodedResult $result,
		private int $attempt,
		private DateTimeImmutable $completedAt,
		private DateTimeImmutable $expiresAt,
	) {
		if ($attempt < 1) {
			throw new InvalidOperationException('Stored result attempt must be greater than zero.');
		}

		if ($expiresAt <= $completedAt) {
			throw new InvalidOperationException('Stored result expiration must be later than completion.');
		}
	}

	public function attempt(): int {
		return $this->attempt;
	}

	public function completedAt(): DateTimeImmutable {
		return $this->completedAt;
	}

	public function expiresAt(): DateTimeImmutable {
		return $this->expiresAt;
	}

	public function result(): EncodedResult {
		return $this->result;
	}
}
