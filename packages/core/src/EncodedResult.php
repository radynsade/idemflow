<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use IdemFlow\Core\Exception\InvalidOperationException;

final readonly class EncodedResult {
	/**
	 * @var array<string, bool|float|int|string|null>
	 */
	private array $metadata;

	/**
	 * @param array<array-key, mixed> $metadata
	 */
	public function __construct(
		private string $codec,
		private int $version,
		private string $type,
		private string $payload,
		array $metadata = [],
	) {
		if ($codec === '' || strlen($codec) > 100) {
			throw new InvalidOperationException('Result codec identifier must contain between 1 and 100 bytes.');
		}

		if ($version < 1) {
			throw new InvalidOperationException('Result codec version must be greater than zero.');
		}

		if ($type === '') {
			throw new InvalidOperationException('Encoded result type must be non-empty.');
		}

		$normalizedMetadata = [];

		foreach ($metadata as $name => $value) {
			if (!is_string($name) || (!is_scalar($value) && $value !== null)) {
				throw new InvalidOperationException('Result metadata must contain string keys and scalar values.');
			}

			if (is_float($value) && (is_infinite($value) || is_nan($value))) {
				throw new InvalidOperationException('Result metadata cannot contain non-finite floats.');
			}

			$normalizedMetadata[$name] = $value;
		}

		$this->metadata = $normalizedMetadata;
	}

	public function codec(): string {
		return $this->codec;
	}

	/**
	 * @return array<string, bool|float|int|string|null>
	 */
	public function metadata(): array {
		return $this->metadata;
	}

	public function payload(): string {
		return $this->payload;
	}

	public function type(): string {
		return $this->type;
	}

	public function version(): int {
		return $this->version;
	}
}
