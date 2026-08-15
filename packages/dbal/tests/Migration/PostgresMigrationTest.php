<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Tests\Migration;

use Doctrine\DBAL\Connection;
use IdemFlow\DBAL\Migration\PostgresMigration;
use PHPUnit\Framework\TestCase;

final class PostgresMigrationTest extends TestCase {
	public function testItUsesTheSchemaQualifiedTableNameAsProvided(): void {
		$connection = $this->createMock(Connection::class);

		$connection
			->expects($this->once())
			->method('executeStatement')
			->with(self::callback(static fn (string $sql): bool => str_contains(
				$sql,
				'create table idemflow.operations',
			) && str_contains($sql, '"result_payload" text')
				&& str_contains($sql, '"result_metadata" jsonb')))
			->willReturn(0);

		(new PostgresMigration($connection, 'idemflow.operations'))->up();
	}

	public function testItUsesTheSchemaQualifiedTableNameWhenRollingBack(): void {
		$connection = $this->createMock(Connection::class);
		$connection
			->expects($this->once())
			->method('executeStatement')
			->with('drop table idemflow.operations')
			->willReturn(0);

		(new PostgresMigration($connection, 'idemflow.operations'))->down();
	}
}
