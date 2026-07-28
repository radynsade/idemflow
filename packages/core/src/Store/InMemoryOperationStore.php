<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Store;

use DateTimeImmutable;
use IdemFlow\Core\Contract\TransactionBoundaryInterface;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\Exception\InvalidOperationException;
use IdemFlow\Core\Exception\StaleOperationClaimException;
use IdemFlow\Core\Internal\InMemoryOperationRecord;
use IdemFlow\Core\Operation;
use IdemFlow\Core\OperationClaim;
use IdemFlow\Core\OperationStatus;
use IdemFlow\Core\Store\Claim\Acquired;
use IdemFlow\Core\Store\Claim\FingerprintMismatch;
use IdemFlow\Core\Store\Claim\InProgress;
use IdemFlow\Core\Store\Claim\Replay;
use IdemFlow\Core\StoredOperationResult;
use Throwable;

final class InMemoryOperationStore implements OperationStoreInterface, TransactionBoundaryInterface {
	/**
	 * @var array<string, InMemoryOperationRecord>
	 */
	private array $records = [];

	public function claim(Operation $operation, string $ownerId, DateTimeImmutable $now): ClaimDecision {
		$storageKey = $operation->identity()->storageKey();
		$record = $this->records[$storageKey] ?? null;

		if (
			$record !== null
			&& $record->status === OperationStatus::Completed
			&& $record->expiresAt !== null
			&& $record->expiresAt <= $now
		) {
			$record = null;
		}

		if ($record === null) {
			$attempt = isset($this->records[$storageKey]) ? $this->records[$storageKey]->attempt + 1 : 1;
			$claim = new OperationClaim($operation->identity(), $ownerId, $attempt, $now);

			$this->records[$storageKey] = new InMemoryOperationRecord(
				$operation->identity(),
				$operation->fingerprint(),
				OperationStatus::Processing,
				$ownerId,
				$attempt,
				$now,
			);

			return new Acquired($claim);
		}

		if (!$record->fingerprint->equals($operation->fingerprint())) {
			return new FingerprintMismatch(
				$operation->identity(),
				$record->fingerprint,
				$operation->fingerprint(),
			);
		}

		if ($record->status === OperationStatus::Processing) {
			return new InProgress($record->identity, $record->attempt, $record->startedAt);
		}

		if (
			$record->status === OperationStatus::Completed
			&& $record->result !== null
			&& $record->completedAt !== null
			&& $record->expiresAt !== null
		) {
			return new Replay(new StoredOperationResult(
				$record->result,
				$record->attempt,
				$record->completedAt,
				$record->expiresAt,
			));
		}

		throw new StaleOperationClaimException(new OperationClaim(
			$record->identity,
			$record->ownerId,
			$record->attempt,
			$record->startedAt,
		));
	}

	public function complete(
		OperationClaim $claim,
		EncodedResult $result,
		DateTimeImmutable $completedAt,
		DateTimeImmutable $expiresAt,
	): void {
		if ($expiresAt <= $completedAt) {
			throw new InvalidOperationException('Operation expiration must be later than completion.');
		}

		$record = $this->records[$claim->identity()->storageKey()] ?? null;

		if (
			$record === null
			|| $record->status !== OperationStatus::Processing
			|| !$record->identity->equals($claim->identity())
			|| !hash_equals($record->ownerId, $claim->ownerId())
			|| $record->attempt !== $claim->attempt()
		) {
			throw new StaleOperationClaimException($claim);
		}

		$this->records[$claim->identity()->storageKey()] = new InMemoryOperationRecord(
			$record->identity,
			$record->fingerprint,
			OperationStatus::Completed,
			$record->ownerId,
			$record->attempt,
			$record->startedAt,
			$result,
			$completedAt,
			$expiresAt,
		);
	}

	public function count(): int {
		return count($this->records);
	}

	public function transactional(callable $callback): mixed {
		$snapshot = $this->records;

		try {
			return $callback();
		} catch (Throwable $exception) {
			$this->records = $snapshot;

			throw $exception;
		}
	}
}
