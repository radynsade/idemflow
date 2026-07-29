<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use IdemFlow\Core\Exception\InvalidOperationException;
use JsonException;

final readonly class PayloadFingerprint {
	private function __construct(private string $hash) {
	}

	/**
	 * @param array<array-key, mixed> $payload
	 */
	public static function fromArray(array $payload): self {
		try {
			$json = json_encode(
				self::canonicalize($payload),
				JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
			);
		} catch (JsonException $exception) {
			throw new InvalidOperationException('Payload cannot be fingerprinted as canonical JSON.', previous: $exception);
		}

		return self::fromString($json);
	}

	public static function fromHash(string $hash): self {
		$normalized = strtolower($hash);

		if (preg_match('/^[a-f0-9]{64}$/D', $normalized) !== 1) {
			throw new InvalidOperationException('Payload fingerprint must be a SHA-256 hexadecimal digest.');
		}

		return new self($normalized);
	}

	public static function fromString(string $payload): self {
		return new self(hash('sha256', $payload));
	}

	public function equals(self $other): bool {
		return hash_equals($this->hash, $other->hash);
	}

	public function toString(): string {
		return $this->hash;
	}

	private static function canonicalize(mixed $value): mixed {
		if (is_array($value)) {
			if (!array_is_list($value)) {
				ksort($value, SORT_STRING);
			}

			foreach ($value as $key => $item) {
				$value[$key] = self::canonicalize($item);
			}
		} else {
			if (is_object($value) || is_resource($value)) {
				throw new InvalidOperationException('Fingerprint payload can contain only JSON-compatible values.');
			}

			if (is_float($value) && (is_infinite($value) || is_nan($value))) {
				throw new InvalidOperationException('Fingerprint payload cannot contain non-finite floats.');
			}
		}

		return $value;
	}
}
