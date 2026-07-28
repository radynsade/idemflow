<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\PayloadFingerprint;

final class FingerprintMismatchException extends IdemFlowException {
	public function __construct(
		private readonly OperationIdentity $identity,
		private readonly PayloadFingerprint $stored,
		private readonly PayloadFingerprint $received,
	) {
		parent::__construct(sprintf(
			'Operation key for scope "%s" was reused with a different fingerprint.',
			$identity->scope(),
		));
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
