<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\OperationStatus;
use IdemFlow\Core\PayloadFingerprint;
use InvalidArgumentException;
use JsonException;
use Override;
use UnexpectedValueException;

abstract class BaseOperationRecordRepository implements OperationRecordRepositoryInterface {
	public function __construct(
		protected readonly Connection $connection,
		protected readonly string $tableName,
	) {
	}

	#[Override]
	public function queryByIdentity(OperationIdentity $identity): ?OperationRecord {
		$connection = $this->connection;
		$tableName = $this->tableName;

		$result = $connection
			->createQueryBuilder()
			->select(
				'fingerprint',
				'status',
				'owner_id',
				'attempt',
				'started_at',
				'result_payload',
				'result_codec',
				'result_version',
				'result_type',
				'result_metadata',
				'completed_at',
				'expires_at',
			)
			->from($tableName)
			->where('scope = ?')
			->andWhere('key = ?')
			->setParameter(0, $identity->scope())
			->setParameter(1, $identity->keyHash())
			->setMaxResults(1)
			->forUpdate()
			->executeQuery()
			->fetchAssociative();

		$record = null;

		if ($result !== false) {
			$fingerprint = $this->stringColumn($result, 'fingerprint');
			$status = $this->stringColumn($result, 'status');
			$ownerId = $this->stringColumn($result, 'owner_id');
			$attempt = $this->positiveIntegerColumn($result, 'attempt');
			$startedAt = $this->column($result, 'started_at');
			$resultPayload = $this->column($result, 'result_payload');
			$completedAt = $this->column($result, 'completed_at');
			$expiresAt = $this->column($result, 'expires_at');
			$encodedResult = null;

			if ($resultPayload !== null) {
				$encodedResult = new EncodedResult(
					$this->stringColumn($result, 'result_codec'),
					$this->positiveIntegerColumn($result, 'result_version'),
					$this->stringColumn($result, 'result_type'),
					$this->stringColumn($result, 'result_payload'),
					$this->metadataColumn($result, 'result_metadata'),
				);
			}

			$record = new OperationRecord(
				identity: $identity,
				fingerprint: PayloadFingerprint::fromHash($fingerprint),
				status: OperationStatus::from($status),
				ownerId: $ownerId,
				attempt: $attempt,
				startedAt: $this->convertDateTimeToPHPValue($startedAt),
				result: $encodedResult,
				completedAt: $completedAt !== null
						? $this->convertDateTimeToPHPValue($completedAt)
						: null,
				expiresAt: $expiresAt !== null
						? $this->convertDateTimeToPHPValue($expiresAt)
						: null,
			);
		}

		return $record;
	}

	#[Override]
	public function updateIfUnchanged(
		OperationRecord $expected,
		OperationRecord $replacement,
	): bool {
		if (!$expected->identity->equals($replacement->identity)) {
			throw new InvalidArgumentException(
				'Expected and replacement records must have the same identity.',
			);
		}

		$connection = $this->connection;
		$tableName = $this->tableName;

		$rows = $connection->createQueryBuilder()
			->update($tableName)
			->set('fingerprint', ':replacementFingerprint')
			->set('status', ':replacementStatus')
			->set('owner_id', ':replacementOwnerId')
			->set('attempt', ':replacementAttempt')
			->set('started_at', $this->dateTimeExpression(':replacementStartedAt'))
			->set('result_payload', ':replacementResultPayload')
			->set('result_codec', ':replacementResultCodec')
			->set('result_version', ':replacementResultVersion')
			->set('result_type', ':replacementResultType')
			->set('result_metadata', ':replacementResultMetadata')
			->set('completed_at', $this->dateTimeExpression(':replacementCompletedAt'))
			->set('expires_at', $this->dateTimeExpression(':replacementExpiresAt'))
			->where('scope = :expectedScope')
			->andWhere('key = :expectedKey')
			->andWhere('fingerprint = :expectedFingerprint')
			->andWhere('status = :expectedStatus')
			->andWhere('owner_id = :expectedOwnerId')
			->andWhere('attempt = :expectedAttempt')
			->andWhere(sprintf(
				'started_at = %s',
				$this->dateTimeExpression(':expectedStartedAt'),
			))
			->setParameters([
				'replacementFingerprint' => $replacement->fingerprint->toString(),
				'replacementStatus' => $replacement->status->value,
				'replacementOwnerId' => $replacement->ownerId,
				'replacementAttempt' => $replacement->attempt,
				'replacementStartedAt' => $this->convertDateTimeToDatabaseValue($replacement->startedAt),
				'replacementResultPayload' => $replacement->result?->payload(),
				'replacementResultCodec' => $replacement->result?->codec(),
				'replacementResultVersion' => $replacement->result?->version(),
				'replacementResultType' => $replacement->result?->type(),
				'replacementResultMetadata' => $replacement->result?->metadata(),
				'replacementCompletedAt' => $this->convertNullableDateTimeToDatabaseValue(
					$replacement->completedAt,
				),
				'replacementExpiresAt' => $this->convertNullableDateTimeToDatabaseValue(
					$replacement->expiresAt,
				),
				'expectedScope' => $expected->identity->scope(),
				'expectedKey' => $expected->identity->keyHash(),
				'expectedFingerprint' => $expected->fingerprint->toString(),
				'expectedStatus' => $expected->status->value,
				'expectedOwnerId' => $expected->ownerId,
				'expectedAttempt' => $expected->attempt,
				'expectedStartedAt' => $this->convertDateTimeToDatabaseValue($expected->startedAt),
			], [
				'replacementFingerprint' => Types::STRING,
				'replacementStatus' => Types::STRING,
				'replacementOwnerId' => Types::STRING,
				'replacementAttempt' => Types::INTEGER,
				'replacementStartedAt' => $this->dateTimeParameterType(),
				'replacementResultPayload' => Types::TEXT,
				'replacementResultCodec' => Types::STRING,
				'replacementResultVersion' => Types::INTEGER,
				'replacementResultType' => Types::STRING,
				'replacementResultMetadata' => Types::JSONB,
				'replacementCompletedAt' => $this->dateTimeParameterType(),
				'replacementExpiresAt' => $this->dateTimeParameterType(),
				'expectedScope' => Types::STRING,
				'expectedKey' => Types::STRING,
				'expectedFingerprint' => Types::STRING,
				'expectedStatus' => Types::STRING,
				'expectedOwnerId' => Types::STRING,
				'expectedAttempt' => Types::INTEGER,
				'expectedStartedAt' => $this->dateTimeParameterType(),
			])
			->executeStatement();

		return $rows === 1;
	}

	protected function convertDateTimeToDatabaseValue(DateTimeImmutable $value): mixed {
		return $value;
	}

	protected function convertDateTimeToPHPValue(mixed $value): DateTimeImmutable {
		$converted = $this->connection->convertToPHPValue($value, Types::DATETIMETZ_IMMUTABLE);

		if (!$converted instanceof DateTimeImmutable) {
			throw new UnexpectedValueException('The database returned an invalid date-time value.');
		}

		return $converted;
	}

	protected function dateTimeExpression(string $parameter): string {
		return $parameter;
	}

	protected function dateTimeParameterType(): string {
		return Types::DATETIMETZ_IMMUTABLE;
	}

	protected function convertNullableDateTimeToDatabaseValue(?DateTimeImmutable $value): mixed {
		return $value === null ? null : $this->convertDateTimeToDatabaseValue($value);
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function column(array $row, string $column): mixed {
		if (!array_key_exists($column, $row)) {
			throw new UnexpectedValueException(sprintf('The database result does not contain column "%s".', $column));
		}

		return $row[$column];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<mixed>
	 */
	private function metadataColumn(array $row, string $column): array {
		$decoded = $this->jsonColumn($row, $column);

		if (!is_array($decoded)) {
			throw new UnexpectedValueException(sprintf('Database column "%s" must contain a JSON array.', $column));
		}

		return $decoded;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function jsonColumn(array $row, string $column): mixed {
		$value = $this->column($row, $column);

		if (is_array($value)) {
			return $value;
		}

		if (!is_string($value)) {
			throw new UnexpectedValueException(sprintf('Database column "%s" must contain JSON.', $column));
		}

		try {
			$decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new UnexpectedValueException(sprintf(
				'Database column "%s" contains invalid JSON.',
				$column,
			), previous: $exception);
		}

		return $decoded;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function positiveIntegerColumn(array $row, string $column): int {
		$value = $this->column($row, $column);

		if (is_int($value) && $value > 0) {
			return $value;
		}

		if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
			$converted = filter_var($value, FILTER_VALIDATE_INT, [
				'options' => ['min_range' => 1],
			]);

			if (is_int($converted)) {
				return $converted;
			}
		}

		throw new UnexpectedValueException(sprintf('Database column "%s" must contain a positive integer.', $column));
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function stringColumn(array $row, string $column): string {
		$value = $this->column($row, $column);

		if (!is_string($value)) {
			throw new UnexpectedValueException(sprintf('Database column "%s" must contain a string.', $column));
		}

		return $value;
	}
}
