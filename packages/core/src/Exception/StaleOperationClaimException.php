<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

use IdemFlow\Core\OperationClaim;

/**
 * Thrown when a claim no longer owns the operation state it attempts to mutate.
 */
final class StaleOperationClaimException extends IdemFlowException {
	public function __construct(private readonly OperationClaim $claim) {
		parent::__construct(sprintf(
			'Claim attempt %d no longer owns operation "%s" (%s).',
			$claim->attempt(),
			$claim->identity()->scope(),
			$claim->identity()->keyHash(),
		));
	}

	public function claim(): OperationClaim {
		return $this->claim;
	}
}
