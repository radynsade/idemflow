<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL;

use DateTimeImmutable;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\Exception\InvalidOperationException;
use IdemFlow\Core\Exception\StaleOperationClaimException;
use IdemFlow\Core\Operation;
use IdemFlow\Core\OperationClaim;
use IdemFlow\Core\OperationStatus;
use IdemFlow\Core\Store\Claim\Acquired;
use IdemFlow\Core\Store\Claim\FingerprintMismatch;
use IdemFlow\Core\Store\Claim\InProgress;
use IdemFlow\Core\Store\Claim\Replay;
use IdemFlow\Core\Store\ClaimDecision;
use IdemFlow\Core\Store\OperationStoreInterface;
use IdemFlow\Core\StoredOperationResult;
use IdemFlow\DBAL\Repository\OperationRecord;
use IdemFlow\DBAL\Repository\OperationRecordRepositoryInterface;
use Override;

class OperationStore implements OperationStoreInterface {
	public function __construct(private readonly OperationRecordRepositoryInterface $repository) {
	}

	#[Override]
	public function claim(
		Operation $operation,
		string $ownerId,
		DateTimeImmutable $now,
	): ClaimDecision {
		$repository = $this->repository;
		$decision = null;

		while ($decision === null) {
			$record = $repository->queryByIdentity($operation->identity());

			if ($record === null) {
				$attempt = 1;
				$claim = new OperationClaim($operation->identity(), $ownerId, $attempt, $now);

				$inserted = $repository->insertIfAbsent(
					$this->makeProcessingRecord($operation, $ownerId, $attempt, $now),
				);

				if ($inserted) {
					$decision = new Acquired($claim);
				}
			} elseif ($this->isExpired($record, $now)) {
				$attempt = $record->attempt + 1;
				$claim = new OperationClaim($operation->identity(), $ownerId, $attempt, $now);
				$replacement = $this->makeProcessingRecord($operation, $ownerId, $attempt, $now);

				if ($repository->updateIfUnchanged($record, $replacement)) {
					$decision = new Acquired($claim);
				}
			} elseif (!$record->fingerprint->equals($operation->fingerprint())) {
				$decision = new FingerprintMismatch(
					$operation->identity(),
					$record->fingerprint,
					$operation->fingerprint(),
				);
			} elseif ($record->status === OperationStatus::Processing) {
				$decision = new InProgress($record->identity, $record->attempt, $record->startedAt);
			} elseif (
				$record->status === OperationStatus::Completed
				&& $record->result !== null
				&& $record->completedAt !== null
				&& $record->expiresAt !== null
			) {
				$decision = new Replay(new StoredOperationResult(
					$record->result,
					$record->attempt,
					$record->completedAt,
					$record->expiresAt,
				));
			} else {
				throw new StaleOperationClaimException(new OperationClaim(
					$record->identity,
					$record->ownerId,
					$record->attempt,
					$record->startedAt,
				));
			}
		}

		return $decision;
	}

	#[Override]
	public function complete(
		OperationClaim $claim,
		EncodedResult $result,
		DateTimeImmutable $completedAt,
		DateTimeImmutable $expiresAt,
	): void {
		if ($expiresAt <= $completedAt) {
			throw new InvalidOperationException('Operation expiration must be later than completion.');
		}

		$repository = $this->repository;
		$existingRecord = $repository->queryByIdentity($claim->identity());

		if (
			$existingRecord === null
			|| $existingRecord->status !== OperationStatus::Processing
			|| !$existingRecord->identity->equals($claim->identity())
			|| !hash_equals($existingRecord->ownerId, $claim->ownerId())
			|| $existingRecord->attempt !== $claim->attempt()
		) {
			throw new StaleOperationClaimException($claim);
		}

		$updatedRecord = new OperationRecord(
			$existingRecord->identity,
			$existingRecord->fingerprint,
			OperationStatus::Completed,
			$existingRecord->ownerId,
			$existingRecord->attempt,
			$existingRecord->startedAt,
			$result,
			$completedAt,
			$expiresAt,
		);

		if (!$repository->updateIfUnchanged($existingRecord, $updatedRecord)) {
			throw new StaleOperationClaimException($claim);
		}
	}

	private function isExpired(OperationRecord $record, DateTimeImmutable $now): bool {
		return $record->status === OperationStatus::Completed
			&& $record->expiresAt !== null
			&& $record->expiresAt <= $now;
	}

	private function makeProcessingRecord(
		Operation $operation,
		string $ownerId,
		int $attempt,
		DateTimeImmutable $now,
	): OperationRecord {
		return new OperationRecord(
			$operation->identity(),
			$operation->fingerprint(),
			OperationStatus::Processing,
			$ownerId,
			$attempt,
			$now,
		);
	}
}
