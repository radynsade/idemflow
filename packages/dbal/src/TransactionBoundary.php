<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use IdemFlow\Core\Contract\TransactionBoundaryInterface;
use LogicException;
use Override;
use Throwable;

class TransactionBoundary implements TransactionBoundaryInterface {
	public function __construct(protected readonly Connection $connection) {
	}

	#[Override]
	public function transactional(callable $callback): mixed {
		$connection = $this->connection;

		if (
			!$connection->isAutoCommit()
			|| $connection->isTransactionActive()
		) {
			throw new LogicException('Nested transactions are forbidden.');
		}

		if ($connection->getTransactionIsolation() !== TransactionIsolationLevel::READ_COMMITTED) {
			throw new LogicException(
				'The connection must be configured with "READ COMMITTED" isolation.',
			);
		}

		try {
			$connection->beginTransaction();
			$result = $callback();
			$connection->commit();
		} catch (Throwable $exception) {
			if ($connection->isTransactionActive()) {
				try {
					$connection->rollBack();
				} catch (Throwable) {
					try {
						$connection->close();
					} catch (Throwable) {
						// Closing an unusable connection must not replace the original failure.
					}
				}
			}

			throw $exception;
		}

		return $result;
	}
}
