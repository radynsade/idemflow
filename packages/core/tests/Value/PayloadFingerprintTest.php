<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Tests\Value;

use IdemFlow\Core\Exception\InvalidOperationException;
use IdemFlow\Core\PayloadFingerprint;
use PHPUnit\Framework\TestCase;

final class PayloadFingerprintTest extends TestCase {
	public function testObjectKeyOrderDoesNotChangeTheFingerprint(): void {
		$first = PayloadFingerprint::fromArray([
			'customer' => ['name' => 'Ada', 'id' => 10],
			'items' => [['quantity' => 2, 'sku' => 'book']],
		]);

		$second = PayloadFingerprint::fromArray([
			'items' => [['sku' => 'book', 'quantity' => 2]],
			'customer' => ['id' => 10, 'name' => 'Ada'],
		]);

		self::assertTrue($first->equals($second));
	}

	public function testListOrderRemainsSignificant(): void {
		$first = PayloadFingerprint::fromArray(['items' => ['a', 'b']]);
		$second = PayloadFingerprint::fromArray(['items' => ['b', 'a']]);
		self::assertFalse($first->equals($second));
	}

	public function testAHashMustBeASha256Digest(): void {
		$this->expectException(InvalidOperationException::class);
		PayloadFingerprint::fromHash('not-a-hash');
	}
}
