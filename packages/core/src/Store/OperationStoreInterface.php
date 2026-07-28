<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store;

use DateTimeImmutable;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\Exception\StaleOperationClaimException;
use IdemFlow\Core\Operation;
use IdemFlow\Core\OperationClaim;

interface OperationStoreInterface {
	/**
	 * Atomically creates a processing record or reports the retained record's state.
	 * Implementations must compare the fingerprint before returning replay or in-progress.
	 */
	public function claim(
		Operation $operation,
		string $ownerId,
		DateTimeImmutable $now,
	): ClaimDecision;

	/**
	 * Completes only a processing record matching identity, owner and attempt.
	 *
	 * @throws StaleOperationClaimException
	 */
	public function complete(
		OperationClaim $claim,
		EncodedResult $result,
		DateTimeImmutable $completedAt,
		DateTimeImmutable $expiresAt,
	): void;
}
