<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Tests\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\OperationStatus;
use IdemFlow\Core\PayloadFingerprint;
use IdemFlow\DBAL\Repository\BaseOperationRecordRepository;
use IdemFlow\DBAL\Repository\OperationRecord;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BaseOperationRecordRepositoryTest extends TestCase {
	private Connection $connection;

	private TestOperationRecordRepository $repository;

	protected function setUp(): void {
		$this->connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'memory' => true,
		]);

		$this->connection->executeStatement(<<<'SQL'
			create table operations (
				scope text not null,
				key text not null,
				fingerprint text not null,
				status text not null,
				owner_id text not null,
				attempt integer not null,
				started_at text not null,
				result_payload text,
				result_codec text,
				result_version integer,
				result_type text,
				result_metadata text,
				completed_at text,
				expires_at text,
				primary key (scope, key)
			)
			SQL);

		$this->repository = new TestOperationRecordRepository(
			$this->connection,
			'operations',
		);
	}

	public function testItReplacesOnlyTheExpectedRecordState(): void {
		$identity = new OperationIdentity('orders.create', 'request-123');
		$startedAt = new DateTimeImmutable('2026-08-14T10:00:00+00:00');
		$metadata = [
			'digest' => 'контрольная сумма',
			'empty' => '',
			'integer' => 42,
			'float' => 42.0,
			'true' => true,
			'false' => false,
			'null' => null,
		];

		$expected = new OperationRecord(
			$identity,
			PayloadFingerprint::fromString('first payload'),
			OperationStatus::Processing,
			'owner-1',
			1,
			$startedAt,
		);

		$this->insert($expected);
		$completedAt = new DateTimeImmutable('2026-08-14T10:01:00+00:00');

		$replacement = new OperationRecord(
			$identity,
			$expected->fingerprint,
			OperationStatus::Completed,
			$expected->ownerId,
			$expected->attempt,
			$expected->startedAt,
			new EncodedResult('custom', 1, 'text', 'Результат', $metadata),
			$completedAt,
			new DateTimeImmutable('2026-08-15T10:01:00+00:00'),
		);

		self::assertTrue($this->repository->updateIfUnchanged($expected, $replacement));

		$stored = $this->connection->fetchAssociative(
			'select status, result_payload, result_metadata from operations',
		);

		self::assertIsArray($stored);
		self::assertSame(OperationStatus::Completed->value, $stored['status']);
		self::assertIsString($stored['result_payload']);
		self::assertSame('Результат', json_decode($stored['result_payload'], true));
		self::assertIsString($stored['result_metadata']);
		self::assertSame($metadata, json_decode($stored['result_metadata'], true));

		self::assertFalse($this->repository->updateIfUnchanged($expected, new OperationRecord(
			$identity,
			PayloadFingerprint::fromString('second payload'),
			OperationStatus::Processing,
			'owner-2',
			2,
			$completedAt,
		)));
	}

	public function testItRejectsAReplacementWithAnotherIdentity(): void {
		$now = new DateTimeImmutable('2026-08-14T10:00:00+00:00');

		$expected = new OperationRecord(
			new OperationIdentity('orders.create', 'request-123'),
			PayloadFingerprint::fromString('payload'),
			OperationStatus::Processing,
			'owner-1',
			1,
			$now,
		);

		$replacement = new OperationRecord(
			new OperationIdentity('orders.create', 'request-456'),
			$expected->fingerprint,
			OperationStatus::Processing,
			'owner-2',
			2,
			$now,
		);

		$this->expectException(InvalidArgumentException::class);
		$this->repository->updateIfUnchanged($expected, $replacement);
	}

	private function insert(OperationRecord $record): void {
		$this->connection->insert('operations', [
			'scope' => $record->identity->scope(),
			'key' => $record->identity->keyHash(),
			'fingerprint' => $record->fingerprint->toString(),
			'status' => $record->status->value,
			'owner_id' => $record->ownerId,
			'attempt' => $record->attempt,
			'started_at' => $record->startedAt,
		], [
			'scope' => Types::STRING,
			'key' => Types::STRING,
			'fingerprint' => Types::STRING,
			'status' => Types::STRING,
			'owner_id' => Types::STRING,
			'attempt' => Types::INTEGER,
			'started_at' => Types::DATETIMETZ_IMMUTABLE,
		]);
	}
}

final class TestOperationRecordRepository extends BaseOperationRecordRepository {
	public function insertIfAbsent(OperationRecord $record): bool {
		throw new LogicException('Not used by this test.');
	}
}
