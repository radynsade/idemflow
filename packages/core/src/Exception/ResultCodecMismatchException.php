<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Exception;

final class ResultCodecMismatchException extends ResultDecodingFailedException {
	public function __construct(string $expected, string $actual) {
		parent::__construct(sprintf('Result uses codec "%s" but "%s" was configured.', $actual, $expected));
	}
}
