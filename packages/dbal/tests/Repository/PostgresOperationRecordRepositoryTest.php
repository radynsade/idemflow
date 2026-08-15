<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Tests\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\OperationStatus;
use IdemFlow\Core\PayloadFingerprint;
use IdemFlow\DBAL\Repository\OperationRecord;
use IdemFlow\DBAL\Repository\PostgresOperationRecordRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostgresOperationRecordRepositoryTest extends TestCase {
	public function testItPreservesMicrosecondsWhenInserting(): void {
		$connection = $this->createMock(Connection::class);

		$connection
			->expects($this->once())
			->method('executeStatement')
			->with(
				self::callback(static fn (string $sql): bool => str_contains(
					$sql,
					'insert into idemflow.operations',
				) && substr_count($sql, 'cast(:') === 3),
				self::callback(static fn (array $parameters): bool => $parameters['startedAt']
					=== '2026-08-14 10:00:00.123456+00:00'
					&& $parameters['completedAt'] === null
					&& $parameters['expiresAt'] === null),
				self::callback(static fn (array $types): bool => $types['startedAt'] === Types::STRING
						&& $types['resultPayload'] === Types::TEXT
					&& $types['completedAt'] === Types::STRING
					&& $types['expiresAt'] === Types::STRING),
			)
			->willReturn(1);

		$record = new OperationRecord(
			new OperationIdentity('orders.create', 'request-123'),
			PayloadFingerprint::fromString('payload'),
			OperationStatus::Processing,
			'owner-1',
			1,
			new DateTimeImmutable('2026-08-14T10:00:00.123456+00:00'),
		);

		$repository = new PostgresOperationRecordRepository($connection, 'idemflow.operations');
		self::assertTrue($repository->insertIfAbsent($record));
	}

	public function testItUsesTheQuotedTableNameWhenReading(): void {
		$connection = $this->getMockBuilder(Connection::class)
			->disableOriginalConstructor()
			->onlyMethods(['executeQuery', 'getDatabasePlatform'])
			->getMock();
		$result = $this->createStub(Result::class);
		$result->method('fetchAssociative')->willReturn(false);

		$connection
			->method('getDatabasePlatform')
			->willReturn(new PostgreSQLPlatform());
		$connection
			->expects($this->once())
			->method('executeQuery')
			->with(self::callback(static fn (string $sql): bool => str_contains(
				$sql,
				'FROM idemflow.operations',
			)))
			->willReturn($result);

		$repository = new PostgresOperationRecordRepository($connection, 'idemflow.operations');

		self::assertNull($repository->queryByIdentity(new OperationIdentity(
			'orders.create',
			'request-123',
		)));
	}

	#[DataProvider('resultPayloads')]
	public function testItHydratesTextResultPayload(string $payload): void {
		$connection = $this->getMockBuilder(Connection::class)
			->disableOriginalConstructor()
			->onlyMethods(['executeQuery', 'getDatabasePlatform'])
			->getMock();
		$result = $this->createStub(Result::class);
		$metadata = ['digest' => 'контрольная сумма', 'ratio' => 1.0];
		$identity = new OperationIdentity('orders.create', 'request-123');

		$result->method('fetchAssociative')->willReturn([
			'fingerprint' => PayloadFingerprint::fromString('payload')->toString(),
			'status' => OperationStatus::Completed->value,
			'owner_id' => 'owner-1',
			'attempt' => '1',
			'started_at' => '2026-08-14 10:00:00+00',
			'result_payload' => $payload,
			'result_codec' => 'custom',
			'result_version' => '1',
			'result_type' => 'text',
			'result_metadata' => json_encode(
				$metadata,
				JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
			),
			'completed_at' => '2026-08-14 10:01:00+00',
			'expires_at' => '2026-08-15 10:01:00+00',
		]);
		$connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
		$connection
			->expects($this->once())
			->method('executeQuery')
			->willReturn($result);

		$record = (new PostgresOperationRecordRepository(
			$connection,
			'idemflow.operations',
		))->queryByIdentity($identity);

		self::assertNotNull($record);
		self::assertNotNull($record->result);
		self::assertSame($payload, $record->result->payload());
		self::assertSame($metadata, $record->result->metadata());
	}

	public function testItPreservesMicrosecondsInConditionalUpdates(): void {
		$connection = $this->createMock(Connection::class);
		$connection
			->method('createQueryBuilder')
			->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($connection));

		$connection
			->expects($this->once())
			->method('executeStatement')
			->with(
				self::callback(static fn (string $sql): bool => str_contains(
					$sql,
					'UPDATE idemflow.operations',
				) && substr_count($sql, 'cast(:') === 4),
				self::callback(static fn (array $parameters): bool => $parameters['replacementStartedAt']
					=== '2026-08-14 10:00:00.123456+00:00'
					&& $parameters['replacementResultPayload'] === 'Результат'
					&& $parameters['replacementResultMetadata'] === ['digest' => 'контрольная сумма']
					&& $parameters['replacementCompletedAt'] === '2026-08-14 10:01:00.234567+00:00'
					&& $parameters['replacementExpiresAt'] === '2026-08-15 10:01:00.345678+00:00'
					&& $parameters['expectedStartedAt'] === '2026-08-14 10:00:00.123456+00:00'),
				self::callback(static fn (array $types): bool => $types['replacementStartedAt'] === Types::STRING
						&& $types['replacementResultPayload'] === Types::TEXT
						&& $types['replacementResultCodec'] === Types::STRING
						&& $types['replacementResultType'] === Types::STRING
						&& $types['replacementResultMetadata'] === Types::JSONB
						&& $types['replacementCompletedAt'] === Types::STRING
					&& $types['replacementExpiresAt'] === Types::STRING
					&& $types['expectedStartedAt'] === Types::STRING),
			)
			->willReturn(1);

		$identity = new OperationIdentity('orders.create', 'request-123');
		$startedAt = new DateTimeImmutable('2026-08-14T10:00:00.123456+00:00');

		$expected = new OperationRecord(
			$identity,
			PayloadFingerprint::fromString('payload'),
			OperationStatus::Processing,
			'owner-1',
			1,
			$startedAt,
		);

		$replacement = new OperationRecord(
			$identity,
			$expected->fingerprint,
			OperationStatus::Completed,
			$expected->ownerId,
			$expected->attempt,
			$expected->startedAt,
			new EncodedResult(
				'custom',
				1,
				'text',
				'Результат',
				['digest' => 'контрольная сумма'],
			),
			new DateTimeImmutable('2026-08-14T10:01:00.234567+00:00'),
			new DateTimeImmutable('2026-08-15T10:01:00.345678+00:00'),
		);

		$repository = new PostgresOperationRecordRepository($connection, 'idemflow.operations');
		self::assertTrue($repository->updateIfUnchanged($expected, $replacement));
	}

	#[DataProvider('postgresDateTimes')]
	public function testItHydratesPostgresDateTimesWithoutLosingPrecision(
		string $stored,
		string $expected,
	): void {
		$repository = new class ($this->createStub(Connection::class), 'operations') extends PostgresOperationRecordRepository {
			public function hydrateDateTime(mixed $value): DateTimeImmutable {
				return $this->convertDateTimeToPHPValue($value);
			}
		};

		self::assertSame($expected, $repository->hydrateDateTime($stored)->format('Y-m-d H:i:s.uP'));
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function postgresDateTimes(): iterable {
		yield 'microseconds' => [
			'2026-08-14 10:00:00.123456+00',
			'2026-08-14 10:00:00.123456+00:00',
		];

		yield 'whole seconds' => [
			'2026-08-14 10:00:00+03',
			'2026-08-14 10:00:00.000000+03:00',
		];
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function resultPayloads(): iterable {
		yield 'empty payload' => [''];
		yield 'UTF-8 payload' => ['Результат'];
		yield 'non-JSON payload' => ['plain text / "quoted"'];
	}
}
