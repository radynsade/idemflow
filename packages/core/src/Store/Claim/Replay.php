<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store\Claim;

use IdemFlow\Core\Store\ClaimDecision;
use IdemFlow\Core\StoredOperationResult;

final readonly class Replay implements ClaimDecision {
	public function __construct(private StoredOperationResult $storedResult) {
	}

	public function storedResult(): StoredOperationResult {
		return $this->storedResult;
	}
}
