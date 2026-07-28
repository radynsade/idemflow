<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Event;

use DateTimeImmutable;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\PayloadFingerprint;

final readonly class FingerprintMismatchDetected {
	public function __construct(
		public OperationIdentity $identity,
		public PayloadFingerprint $stored,
		public PayloadFingerprint $received,
		public DateTimeImmutable $occurredAt,
	) {
	}
}
