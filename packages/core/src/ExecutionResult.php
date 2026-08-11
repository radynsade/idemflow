<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

/**
 * @template T
 */
final readonly class ExecutionResult {
	/**
	 * @param T $value
	 */
	private function __construct(
		private mixed $value,
		private bool $executed,
		private int $attempt,
	) {
	}

	/**
	 * @template TValue
	 * @param TValue $value
	 * @return self<TValue>
	 */
	public static function executed(mixed $value, int $attempt): self {
		return new self($value, true, $attempt);
	}

	/**
	 * @template TValue
	 * @param TValue $value
	 * @return self<TValue>
	 */
	public static function replayed(mixed $value, int $attempt): self {
		return new self($value, false, $attempt);
	}

	public function attempt(): int {
		return $this->attempt;
	}

	/**
	 * @return T
	 */
	public function value(): mixed {
		return $this->value;
	}

	public function wasExecuted(): bool {
		return $this->executed;
	}

	public function wasReplayed(): bool {
		return !$this->executed;
	}
}
