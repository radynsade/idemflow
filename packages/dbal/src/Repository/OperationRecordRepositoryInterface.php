<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Repository;

use IdemFlow\Core\OperationIdentity;
use InvalidArgumentException;

interface OperationRecordRepositoryInterface {
	public function queryByIdentity(OperationIdentity $identity): ?OperationRecord;

	public function insertIfAbsent(OperationRecord $record): bool;

	/**
	 * Atomically updates a record when its fingerprint, status, owner, attempt and start time still
	 * match the expected state.
	 *
	 * @throws InvalidArgumentException When the records have different identities.
	 */
	public function updateIfUnchanged(OperationRecord $expected, OperationRecord $replacement): bool;
}
