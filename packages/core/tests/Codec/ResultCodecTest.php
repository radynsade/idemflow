<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Tests\Codec;

use IdemFlow\Core\Codec\JsonResultCodec;
use IdemFlow\Core\Codec\ResultCodecInterface;
use IdemFlow\Core\Codec\ScalarResultCodec;
use IdemFlow\Core\Codec\VoidResultCodec;
use IdemFlow\Core\Exception\ResultCodecMismatchException;
use IdemFlow\Core\Exception\ResultEncodingFailedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ResultCodecTest extends TestCase {
	/**
	 * @return iterable<string, array{ResultCodecInterface, mixed}>
	 */
	public static function values(): iterable {
		yield 'json' => [new JsonResultCodec(), ['answer' => 42, 'active' => true]];
		yield 'string' => [new ScalarResultCodec(), 'value'];
		yield 'integer' => [new ScalarResultCodec(), 42];
		yield 'float' => [new ScalarResultCodec(), 42.0];
		yield 'boolean' => [new ScalarResultCodec(), false];
		yield 'null scalar' => [new ScalarResultCodec(), null];
		yield 'void' => [new VoidResultCodec(), null];
	}

	#[DataProvider('values')]
	public function testRoundTripIsLossless(ResultCodecInterface $codec, mixed $value): void {
		self::assertSame($value, $codec->decode($codec->encode($value)));
	}

	public function testJsonCodecDoesNotSerializeObjectsImplicitly(): void {
		$this->expectException(ResultEncodingFailedException::class);
		(new JsonResultCodec())->encode(new stdClass());
	}

	public function testAResultCannotBeDecodedByAnotherCodec(): void {
		$encoded = (new ScalarResultCodec())->encode('value');
		$this->expectException(ResultCodecMismatchException::class);
		(new JsonResultCodec())->decode($encoded);
	}

	public function testScalarPayloadLimitIsEnforced(): void {
		$this->expectException(ResultEncodingFailedException::class);
		(new ScalarResultCodec(3))->encode('long');
	}
}
