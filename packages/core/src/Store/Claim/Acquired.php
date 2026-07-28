<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store\Claim;

use IdemFlow\Core\OperationClaim;
use IdemFlow\Core\Store\ClaimDecision;

final readonly class Acquired implements ClaimDecision {
	public function __construct(private OperationClaim $claim) {
	}

	public function claim(): OperationClaim {
		return $this->claim;
	}
}
