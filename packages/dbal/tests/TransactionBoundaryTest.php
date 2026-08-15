<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use IdemFlow\DBAL\TransactionBoundary;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class TransactionBoundaryTest extends TestCase {
	public function testItReturnsTheResultWithoutAQueryAfterCommit(): void {
		$connection = $this->createMock(Connection::class);
		$connection->method('isAutoCommit')->willReturn(true);
		$connection->method('isTransactionActive')->willReturn(false);

		$connection
			->expects($this->once())
			->method('getTransactionIsolation')
			->willReturn(TransactionIsolationLevel::READ_COMMITTED);

		$connection
			->expects($this->never())
			->method('setTransactionIsolation');

		$connection
			->expects($this->once())
			->method('beginTransaction');

		$connection
			->expects($this->once())
			->method('commit');

		$connection
			->expects($this->never())
			->method('rollBack');

		$connection
			->expects($this->never())
			->method('close');

		$result = (new TransactionBoundary($connection))->transactional(
			static fn (): string => 'result',
		);

		self::assertSame('result', $result);
	}

	public function testRollbackFailureDoesNotReplaceTheCallbackFailure(): void {
		$connection = $this->createMock(Connection::class);
		$connection->method('isAutoCommit')->willReturn(true);

		$connection
			->expects($this->exactly(2))
			->method('isTransactionActive')
			->willReturnOnConsecutiveCalls(false, true);

		$connection
			->expects($this->once())
			->method('getTransactionIsolation')
			->willReturn(TransactionIsolationLevel::READ_COMMITTED);

		$connection
			->expects($this->never())
			->method('setTransactionIsolation');

		$connection
			->expects($this->once())
			->method('beginTransaction');

		$connection
			->expects($this->never())
			->method('commit');

		$connection
			->expects($this->once())
			->method('rollBack')
			->willThrowException(new RuntimeException('rollback failed'));

		$connection
			->expects($this->once())
			->method('close');

		$callbackFailure = new RuntimeException('callback failed');
		$caught = null;

		try {
			(new TransactionBoundary($connection))->transactional(
				static function () use ($callbackFailure): mixed {
					throw $callbackFailure;
				},
			);
		} catch (Throwable $exception) {
			$caught = $exception;
		}

		self::assertSame($callbackFailure, $caught);
	}

	public function testItRejectsAConnectionWithoutReadCommittedIsolation(): void {
		$connection = $this->createMock(Connection::class);
		$connection->method('isAutoCommit')->willReturn(true);
		$connection->method('isTransactionActive')->willReturn(false);

		$connection
			->expects($this->once())
			->method('getTransactionIsolation')
			->willReturn(TransactionIsolationLevel::SERIALIZABLE);

		$connection
			->expects($this->never())
			->method('setTransactionIsolation');

		$connection
			->expects($this->never())
			->method('beginTransaction');

		$this->expectException(LogicException::class);

		(new TransactionBoundary($connection))->transactional(
			static fn (): string => 'result',
		);
	}
}
