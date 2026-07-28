<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Tests\Store;

use DateInterval;
use DateTimeImmutable;
use IdemFlow\Core\Codec\JsonResultCodec;
use IdemFlow\Core\Exception\StaleOperationClaimException;
use IdemFlow\Core\Operation;
use IdemFlow\Core\OperationClaim;
use IdemFlow\Core\PayloadFingerprint;
use IdemFlow\Core\Store\Claim\Acquired;
use IdemFlow\Core\Store\Claim\Replay;
use IdemFlow\Core\Store\InMemoryOperationStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InMemoryOperationStoreTest extends TestCase {
	public function testAStaleOwnerCannotCompleteAClaim(): void {
		$store = new InMemoryOperationStore();
		$now = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
		$operation = $this->operation();
		$decision = $store->claim($operation, 'actual-owner', $now);
		self::assertInstanceOf(Acquired::class, $decision);
		$stale = new OperationClaim($operation->identity(), 'stale-owner', 1, $now);

		$this->expectException(StaleOperationClaimException::class);

		$store->complete(
			$stale,
			(new JsonResultCodec())->encode(['ok' => true]),
			$now,
			$now->add(new DateInterval('P1D')),
		);
	}

	public function testACompletedResultCannotBeOverwritten(): void {
		$store = new InMemoryOperationStore();
		$now = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
		$decision = $store->claim($this->operation(), 'owner', $now);
		self::assertInstanceOf(Acquired::class, $decision);
		$result = (new JsonResultCodec())->encode(['ok' => true]);
		$store->complete($decision->claim(), $result, $now, $now->add(new DateInterval('P1D')));

		try {
			$store->complete($decision->claim(), $result, $now, $now->add(new DateInterval('P1D')));
			self::fail('Completed result must be immutable.');
		} catch (StaleOperationClaimException) {
		}

		self::assertInstanceOf(Replay::class, $store->claim($this->operation(), 'next-owner', $now));
	}

	public function testTransactionRollbackRestoresThePreviousState(): void {
		$store = new InMemoryOperationStore();

		try {
			$store->transactional(function () use ($store): never {
				$store->claim(
					$this->operation(),
					'owner',
					new DateTimeImmutable('2026-07-24T12:00:00+00:00'),
				);

				throw new RuntimeException('rollback');
			});
		} catch (RuntimeException) {
		}

		self::assertSame(0, $store->count());
	}

	private function operation(): Operation {
		return Operation::atomic(
			scope: 'orders.create',
			key: 'request-123',
			fingerprint: PayloadFingerprint::fromString('input'),
			retention: new DateInterval('P1D'),
		);
	}
}
