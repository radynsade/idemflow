<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Tests\Value;

use DateInterval;
use DateTimeImmutable;
use IdemFlow\Core\Exception\InvalidOperationException;
use IdemFlow\Core\Operation;
use IdemFlow\Core\OperationIdentity;
use IdemFlow\Core\PayloadFingerprint;
use PHPUnit\Framework\TestCase;

final class OperationTest extends TestCase {
	public function testIdentityRejectsAnEmptyScope(): void {
		$this->expectException(InvalidOperationException::class);

		new OperationIdentity('', 'key');
	}

	public function testIdentityRejectsAnEmptyKey(): void {
		$this->expectException(InvalidOperationException::class);

		new OperationIdentity('orders.create', '');
	}

	public function testRetentionMustBePositive(): void {
		$this->expectException(InvalidOperationException::class);

		Operation::atomic(
			scope: 'orders.create',
			key: 'key',
			fingerprint: PayloadFingerprint::fromString('input'),
			retention: new DateInterval('PT0S'),
		);
	}

	public function testRetentionIsDefensivelyCopied(): void {
		$retention = new DateInterval('P1D');
		$operation = Operation::atomic(
			scope: 'orders.create',
			key: 'key',
			fingerprint: PayloadFingerprint::fromString('input'),
			retention: $retention,
		);
		$retention->d = 10;

		self::assertSame(
			'2026-07-25',
			$operation->expiresAt(new DateTimeImmutable('2026-07-24'))->format('Y-m-d'),
		);
	}

	public function testMetadataRejectsNestedValues(): void {
		$this->expectException(InvalidOperationException::class);

		Operation::atomic(
			scope: 'orders.create',
			key: 'key',
			fingerprint: PayloadFingerprint::fromString('input'),
			retention: new DateInterval('P1D'),
			metadata: ['unsafe' => ['nested']],
		);
	}
}
