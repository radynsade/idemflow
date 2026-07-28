<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Codec;

use IdemFlow\Core\EncodedResult;

interface ResultCodecInterface {
	public function decode(EncodedResult $result): mixed;

	public function encode(mixed $value): EncodedResult;
}
