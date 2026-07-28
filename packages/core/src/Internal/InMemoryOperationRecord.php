<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Internal;

use DateTimeImmutable;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\OperationStatus;
use IdemFlow\Core\PayloadFingerprint;

final readonly class InMemoryOperationRecord {
	public function __construct(
		public OperationIdentity $identity,
		public PayloadFingerprint $fingerprint,
		public OperationStatus $status,
		public string $ownerId,
		public int $attempt,
		public DateTimeImmutable $startedAt,
		public ?EncodedResult $result = null,
		public ?DateTimeImmutable $completedAt = null,
		public ?DateTimeImmutable $expiresAt = null,
	) {
	}
}
