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

final class VoidResultCodec implements ResultCodecInterface {
	public const string ID = 'void';

	public const int VERSION = 1;

	public function decode(EncodedResult $result): null {
		if ($result->codec() !== self::ID) {
			throw new ResultCodecMismatchException(self::ID, $result->codec());
		}

		if ($result->version() !== self::VERSION || $result->type() !== 'void' || $result->payload() !== '') {
			throw new ResultDecodingFailedException('Stored void result is invalid.');
		}

		return null;
	}

	public function encode(mixed $value): EncodedResult {
		if ($value !== null) {
			throw new ResultEncodingFailedException('Void result codec accepts only null.');
		}

		return new EncodedResult(self::ID, self::VERSION, 'void', '');
	}
}
