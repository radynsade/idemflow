<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Event;

use DateTimeImmutable;
use IdemFlow\Core\OperationIdentity;

final readonly class OperationReplayed {
	public function __construct(
		public OperationIdentity $identity,
		public int $attempt,
		public DateTimeImmutable $occurredAt,
	) {
	}
}
