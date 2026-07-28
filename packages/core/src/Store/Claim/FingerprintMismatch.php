<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store\Claim;

use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\PayloadFingerprint;
use IdemFlow\Core\Store\ClaimDecision;

final readonly class FingerprintMismatch implements ClaimDecision {
	public function __construct(
		private OperationIdentity $identity,
		private PayloadFingerprint $stored,
		private PayloadFingerprint $received,
	) {
	}

	public function identity(): OperationIdentity {
		return $this->identity;
	}

	public function received(): PayloadFingerprint {
		return $this->received;
	}

	public function stored(): PayloadFingerprint {
		return $this->stored;
	}
}
