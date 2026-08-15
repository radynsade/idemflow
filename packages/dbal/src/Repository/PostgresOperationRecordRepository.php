<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Override;
use UnexpectedValueException;

/**
 * Requires PostgreSQL READ COMMITTED transaction isolation.
 *
 * Claim contention is resolved by reading again after INSERT ... ON CONFLICT DO NOTHING. Under
 * REPEATABLE READ or SERIALIZABLE, the transaction may retain an older snapshot or fail with a
 * serialization error. Retrying the entire transaction is unsafe after the operation callback has
 * started because it may execute the callback more than once.
 */
class PostgresOperationRecordRepository extends BaseOperationRecordRepository {
	#[Override]
	public function insertIfAbsent(OperationRecord $record): bool {
		$connection = $this->connection;
		$tableName = $this->tableName;
		$startedAtExpression = $this->dateTimeExpression(':startedAt');
		$completedAtExpression = $this->dateTimeExpression(':completedAt');
		$expiresAtExpression = $this->dateTimeExpression(':expiresAt');

		$sql = <<<SQL
		insert into {$tableName} (
			"scope",
			"key",
			"fingerprint",
			"status",
			"owner_id",
			"attempt",
			"started_at",
			"result_payload",
			"result_codec",
			"result_version",
			"result_type",
			"result_metadata",
			"completed_at",
			"expires_at"
		) values (
			:scope,
			:key,
			:fingerprint,
			:status,
			:ownerId,
			:attempt,
			{$startedAtExpression},
			:resultPayload,
			:resultCodec,
			:resultVersion,
			:resultType,
			:resultMetadata,
			{$completedAtExpression},
			{$expiresAtExpression}
		)
		on conflict ("scope", "key") do nothing
		SQL;

		return $connection->executeStatement($sql, [
			'scope' => $record->identity->scope(),
			'key' => $record->identity->keyHash(),
			'fingerprint' => $record->fingerprint->toString(),
			'status' => $record->status->value,
			'ownerId' => $record->ownerId,
			'attempt' => $record->attempt,
			'startedAt' => $this->convertDateTimeToDatabaseValue($record->startedAt),
			'resultPayload' => $record->result?->payload(),
			'resultCodec' => $record->result?->codec(),
			'resultVersion' => $record->result?->version(),
			'resultType' => $record->result?->type(),
			'resultMetadata' => $record->result?->metadata(),
			'completedAt' => $this->convertNullableDateTimeToDatabaseValue($record->completedAt),
			'expiresAt' => $this->convertNullableDateTimeToDatabaseValue($record->expiresAt),
		], [
			'scope' => Types::STRING,
			'key' => Types::STRING,
			'fingerprint' => Types::STRING,
			'status' => Types::STRING,
			'ownerId' => Types::STRING,
			'attempt' => Types::INTEGER,
			'startedAt' => $this->dateTimeParameterType(),
			'resultPayload' => Types::TEXT,
			'resultCodec' => Types::STRING,
			'resultVersion' => Types::INTEGER,
			'resultType' => Types::STRING,
			'resultMetadata' => Types::JSONB,
			'completedAt' => $this->dateTimeParameterType(),
			'expiresAt' => $this->dateTimeParameterType(),
		]) === 1;
	}

	#[Override]
	protected function convertDateTimeToDatabaseValue(DateTimeImmutable $value): string {
		return $value->format('Y-m-d H:i:s.uP');
	}

	#[Override]
	protected function convertDateTimeToPHPValue(mixed $value): DateTimeImmutable {
		if ($value instanceof DateTimeImmutable) {
			return $value;
		}

		if (!is_string($value)) {
			throw new UnexpectedValueException('PostgreSQL returned an invalid date-time value.');
		}

		foreach (['!Y-m-d H:i:s.uP', '!Y-m-d H:i:sP'] as $format) {
			$dateTime = DateTimeImmutable::createFromFormat($format, $value);
			$errors = DateTimeImmutable::getLastErrors();

			if ($dateTime !== false && $errors === false) {
				return $dateTime;
			}
		}

		throw new UnexpectedValueException('PostgreSQL returned an invalid date-time value.');
	}

	#[Override]
	protected function dateTimeExpression(string $parameter): string {
		return sprintf('cast(%s as timestamp(6) with time zone)', $parameter);
	}

	#[Override]
	protected function dateTimeParameterType(): string {
		return Types::STRING;
	}
}
