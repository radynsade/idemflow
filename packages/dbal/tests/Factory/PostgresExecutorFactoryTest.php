<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Tests\Factory;

use Doctrine\DBAL\Connection;
use IdemFlow\Core\Codec\ResultCodecInterface;
use IdemFlow\Core\Executor;
use IdemFlow\DBAL\Factory\PostgresExecutorFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class PostgresExecutorFactoryTest extends TestCase {
	public function testItBuildsExecutorWithoutUsingConnection(): void {
		$connection = $this->createMock(Connection::class);
		$connection->expects($this->never())->method('setTransactionIsolation');
		$connection->expects($this->never())->method('getTransactionIsolation');
		$connection->expects($this->never())->method('beginTransaction');
		$connection->expects($this->never())->method('executeStatement');
		$connection->expects($this->never())->method('getDatabasePlatform');

		$executor = (new PostgresExecutorFactory($connection, 'idemflow.operations'))->create(
			resultCodec: $this->createStub(ResultCodecInterface::class),
			eventDispatcher: $this->createStub(EventDispatcherInterface::class),
		);

		self::assertInstanceOf(Executor::class, $executor);
	}
}
