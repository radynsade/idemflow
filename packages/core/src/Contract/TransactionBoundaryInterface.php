<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Contract;

interface TransactionBoundaryInterface {
	/**
	 * Runs claim, business writes and completion in one transaction.
	 * Implementations must roll back and rethrow the original exception on failure.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public function transactional(callable $callback): mixed;
}
