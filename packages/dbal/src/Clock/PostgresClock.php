<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;

class PostgresClock implements ClockInterface {
	public function __construct(private readonly Connection $connection) {
	}

	#[Override]
	public function now(): DateTimeImmutable {
		$result = $this->connection->fetchOne(<<<'SQL'
			select to_char(
				statement_timestamp() at time zone 'UTC',
				'YYYY-MM-DD HH24:MI:SS.US'
			)
			SQL);

		if (!is_string($result)) {
			throw new RuntimeException('Failed to get the current time from the database.');
		}

		$currentTime = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s.u',
			$result,
			new DateTimeZone('UTC'),
		);

		$errors = DateTimeImmutable::getLastErrors();

		if ($currentTime === false || $errors !== false) {
			throw new RuntimeException('The database returned an invalid current time.');
		}

		return $currentTime;
	}
}
