<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Tests\Clock;

use Doctrine\DBAL\Connection;
use IdemFlow\DBAL\Clock\PostgresClock;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgresClockTest extends TestCase {
	public function testItPreservesPostgresMicroseconds(): void {
		$connection = $this->createMock(Connection::class);

		$connection
			->expects($this->once())
			->method('fetchOne')
			->willReturn('2026-08-14 10:00:00.123456');

		$currentTime = (new PostgresClock($connection))->now();
		self::assertSame('2026-08-14 10:00:00.123456+00:00', $currentTime->format('Y-m-d H:i:s.uP'));
	}

	public function testItRejectsAnInvalidDatabaseValue(): void {
		$connection = $this->createMock(Connection::class);

		$connection
			->expects($this->once())
			->method('fetchOne')
			->willReturn('invalid');

		$this->expectException(RuntimeException::class);
		(new PostgresClock($connection))->now();
	}
}
