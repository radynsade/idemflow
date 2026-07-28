<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use DateInterval;
use DateTimeImmutable;
use IdemFlow\Core\Exception\InvalidOperationException;

final readonly class Operation {
	/**
	 * @var array<string, bool|float|int|string|null>
	 */
	private array $metadata;

	private DateInterval $retention;

	/**
	 * @param array<array-key, mixed> $metadata
	 */
	private function __construct(
		private OperationIdentity $identity,
		private PayloadFingerprint $fingerprint,
		DateInterval $retention,
		private ExecutionMode $mode,
		array $metadata,
	) {
		$epoch = new DateTimeImmutable('@0');

		if ($retention->invert === 1 || $epoch->add($retention) <= $epoch) {
			throw new InvalidOperationException('Operation retention must be a positive interval.');
		}

		$normalizedMetadata = [];

		foreach ($metadata as $name => $value) {
			if (!is_string($name) || (!is_scalar($value) && $value !== null)) {
				throw new InvalidOperationException('Operation metadata must contain string keys and scalar values.');
			}

			if (is_float($value) && (is_infinite($value) || is_nan($value))) {
				throw new InvalidOperationException('Operation metadata cannot contain non-finite floats.');
			}

			$normalizedMetadata[$name] = $value;
		}

		$this->metadata = $normalizedMetadata;
		$this->retention = clone $retention;
	}

	/**
	 * @param array<array-key, mixed> $metadata
	 */
	public static function atomic(
		string $scope,
		string $key,
		PayloadFingerprint $fingerprint,
		DateInterval $retention,
		array $metadata = [],
	): self {
		return new self(
			new OperationIdentity($scope, $key),
			$fingerprint,
			$retention,
			ExecutionMode::Atomic,
			$metadata,
		);
	}

	public function expiresAt(DateTimeImmutable $completedAt): DateTimeImmutable {
		return $completedAt->add($this->retention);
	}

	public function fingerprint(): PayloadFingerprint {
		return $this->fingerprint;
	}

	public function identity(): OperationIdentity {
		return $this->identity;
	}

	/**
	 * @return array<string, bool|float|int|string|null>
	 */
	public function metadata(): array {
		return $this->metadata;
	}

	public function mode(): ExecutionMode {
		return $this->mode;
	}

	public function retention(): DateInterval {
		return clone $this->retention;
	}
}
