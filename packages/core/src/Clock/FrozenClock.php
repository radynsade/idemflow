<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Clock;

use DateInterval;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FrozenClock implements ClockInterface {
	public function __construct(private DateTimeImmutable $currentTime) {
	}

	public function advance(DateInterval $interval): void {
		$this->currentTime = $this->currentTime->add($interval);
	}

	public function now(): DateTimeImmutable {
		return $this->currentTime;
	}

	public function set(DateTimeImmutable $currentTime): void {
		$this->currentTime = $currentTime;
	}
}
