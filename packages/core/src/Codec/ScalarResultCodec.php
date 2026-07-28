<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Codec;

use IdemFlow\Core\EncodedResult;
use IdemFlow\Core\Exception\ResultCodecMismatchException;
use IdemFlow\Core\Exception\ResultDecodingFailedException;
use IdemFlow\Core\Exception\ResultEncodingFailedException;
use JsonException;

final readonly class ScalarResultCodec implements ResultCodecInterface {
	public const string ID = 'scalar';

	public const int VERSION = 1;

	public function __construct(private int $maxPayloadBytes = 1_048_576) {
		if ($maxPayloadBytes < 1) {
			throw new ResultEncodingFailedException('Maximum result payload size must be greater than zero.');
		}
	}

	public function decode(EncodedResult $result): bool|float|int|string|null {
		if ($result->codec() !== self::ID) {
			throw new ResultCodecMismatchException(self::ID, $result->codec());
		}

		if ($result->version() !== self::VERSION) {
			throw new ResultDecodingFailedException('Stored scalar result has an unsupported version.');
		}

		if (strlen($result->payload()) > $this->maxPayloadBytes) {
			throw new ResultDecodingFailedException(sprintf(
				'Stored result exceeds the %d-byte limit.',
				$this->maxPayloadBytes,
			));
		}

		try {
			$value = json_decode($result->payload(), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new ResultDecodingFailedException('Stored scalar result is invalid.', previous: $exception);
		}

		$valid = match ($result->type()) {
			'null' => $value === null,
			'bool' => is_bool($value),
			'int' => is_int($value),
			'float' => is_float($value),
			'string' => is_string($value),
			default => false,
		};

		if (!$valid || (!is_scalar($value) && $value !== null)) {
			throw new ResultDecodingFailedException('Stored scalar result type does not match its payload.');
		}

		return $value;
	}

	public function encode(mixed $value): EncodedResult {
		if (!is_scalar($value) && $value !== null) {
			throw new ResultEncodingFailedException('Scalar result codec accepts only scalar values and null.');
		}

		if (is_float($value) && (is_infinite($value) || is_nan($value))) {
			throw new ResultEncodingFailedException('Scalar result cannot contain a non-finite float.');
		}

		$type = match (true) {
			$value === null => 'null',
			is_bool($value) => 'bool',
			is_int($value) => 'int',
			is_float($value) => 'float',
			default => 'string',
		};

		try {
			$payload = json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new ResultEncodingFailedException('Scalar result cannot be encoded.', previous: $exception);
		}

		if (strlen($payload) > $this->maxPayloadBytes) {
			throw new ResultEncodingFailedException(sprintf(
				'Encoded result exceeds the %d-byte limit.',
				$this->maxPayloadBytes,
			));
		}

		return new EncodedResult(self::ID, self::VERSION, $type, $payload);
	}
}
