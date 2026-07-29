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

final readonly class JsonResultCodec implements ResultCodecInterface {
	public const string ID = 'json';

	public const int VERSION = 1;

	public function __construct(private int $maxPayloadBytes = 1_048_576) {
		if ($maxPayloadBytes < 1) {
			throw new ResultEncodingFailedException('Maximum result payload size must be greater than zero.');
		}
	}

	public function decode(EncodedResult $result): mixed {
		$this->assertCompatible($result);

		try {
			$value = json_decode($result->payload(), true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new ResultDecodingFailedException('Stored JSON result is invalid.', previous: $exception);
		}

		return $value;
	}

	public function encode(mixed $value): EncodedResult {
		self::assertJsonValue($value);

		try {
			$payload = json_encode(
				$value,
				JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
			);
		} catch (JsonException $exception) {
			throw new ResultEncodingFailedException('Result cannot be encoded as JSON.', previous: $exception);
		}

		if (strlen($payload) > $this->maxPayloadBytes) {
			throw new ResultEncodingFailedException(sprintf(
				'Encoded result exceeds the %d-byte limit.',
				$this->maxPayloadBytes,
			));
		}

		return new EncodedResult(self::ID, self::VERSION, 'mixed', $payload);
	}

	private static function assertJsonValue(mixed $value): void {
		if (is_object($value) || is_resource($value)) {
			throw new ResultEncodingFailedException('JSON result codec accepts only explicit JSON-compatible values.');
		}

		if (is_float($value) && (is_infinite($value) || is_nan($value))) {
			throw new ResultEncodingFailedException('JSON result cannot contain non-finite floats.');
		}

		if (is_array($value)) {
			foreach ($value as $item) {
				self::assertJsonValue($item);
			}
		}
	}

	private function assertCompatible(EncodedResult $result): void {
		if ($result->codec() !== self::ID) {
			throw new ResultCodecMismatchException(self::ID, $result->codec());
		}

		if ($result->version() !== self::VERSION || $result->type() !== 'mixed') {
			throw new ResultDecodingFailedException('Stored JSON result has an unsupported version or type.');
		}

		if (strlen($result->payload()) > $this->maxPayloadBytes) {
			throw new ResultDecodingFailedException(sprintf(
				'Stored result exceeds the %d-byte limit.',
				$this->maxPayloadBytes,
			));
		}
	}
}
