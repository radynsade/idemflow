<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use IdemFlow\Core\Exception\InvalidOperationException;

final readonly class OperationIdentity {
	private const int MAX_KEY_LENGTH = 255;

	private const int MAX_SCOPE_LENGTH = 120;

	private string $keyHash;

	public function __construct(
		private string $scope,
		private string $key,
	) {
		if ($scope === '' || $scope !== trim($scope)) {
			throw new InvalidOperationException('Operation scope must be non-empty and cannot have surrounding whitespace.');
		}

		if (strlen($scope) > self::MAX_SCOPE_LENGTH) {
			throw new InvalidOperationException(sprintf('Operation scope cannot exceed %d bytes.', self::MAX_SCOPE_LENGTH));
		}

		if ($key === '' || $key !== trim($key)) {
			throw new InvalidOperationException('Operation key must be non-empty and cannot have surrounding whitespace.');
		}

		if (strlen($key) > self::MAX_KEY_LENGTH) {
			throw new InvalidOperationException(sprintf('Operation key cannot exceed %d bytes.', self::MAX_KEY_LENGTH));
		}

		if (preg_match('//u', $scope . $key) !== 1) {
			throw new InvalidOperationException('Operation identity must be valid UTF-8.');
		}

		if (preg_match('/[\x00-\x1F\x7F]/u', $scope . $key) === 1) {
			throw new InvalidOperationException('Operation identity cannot contain control characters.');
		}

		$this->keyHash = hash('sha256', $key);
	}

	public function equals(self $other): bool {
		return $this->scope === $other->scope && hash_equals($this->keyHash, $other->keyHash);
	}

	public function key(): string {
		return $this->key;
	}

	public function keyHash(): string {
		return $this->keyHash;
	}

	public function scope(): string {
		return $this->scope;
	}

	public function storageKey(): string {
		return $this->scope . "\0" . $this->keyHash;
	}
}
