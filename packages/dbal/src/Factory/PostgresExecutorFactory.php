<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Factory;

use Doctrine\DBAL\Connection;
use IdemFlow\Core\Codec\ResultCodecInterface;
use IdemFlow\Core\Executor;
use IdemFlow\DBAL\Clock\PostgresClock;
use IdemFlow\DBAL\OperationStore;
use IdemFlow\DBAL\Repository\PostgresOperationRecordRepository;
use IdemFlow\DBAL\TransactionBoundary;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class PostgresExecutorFactory {
	public function __construct(
		private Connection $connection,
		private string $tableName,
	) {
	}

	public function create(
		?ResultCodecInterface $resultCodec = null,
		?EventDispatcherInterface $eventDispatcher = null,
	): Executor {
		$repository = new PostgresOperationRecordRepository($this->connection, $this->tableName);

		return new Executor(
			store: new OperationStore($repository),
			transactionBoundary: new TransactionBoundary($this->connection),
			resultCodec: $resultCodec,
			clock: new PostgresClock($this->connection),
			eventDispatcher: $eventDispatcher,
		);
	}
}
