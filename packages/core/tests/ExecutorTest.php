<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core\Tests;

use DateInterval;
use DateTimeImmutable;
use IdemFlow\Core\Clock\FrozenClock;
use IdemFlow\Core\Codec\JsonResultCodec;
use IdemFlow\Core\Event\FingerprintMismatchDetected;
use IdemFlow\Core\Event\OperationClaimed;
use IdemFlow\Core\Event\OperationCompleted;
use IdemFlow\Core\Event\OperationExecuted;
use IdemFlow\Core\Event\OperationFailed;
use IdemFlow\Core\Event\OperationReplayed;
use IdemFlow\Core\Exception\FingerprintMismatchException;
use IdemFlow\Core\Exception\OperationInProgressException;
use IdemFlow\Core\Exception\ResultEncodingFailedException;
use IdemFlow\Core\Executor;
use IdemFlow\Core\Operation;
use IdemFlow\Core\PayloadFingerprint;
use IdemFlow\Core\Store\InMemoryOperationStore;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use stdClass;

final class ExecutorTest extends TestCase {
	private CollectingEventDispatcher $events;

	private Executor $executor;

	private FrozenClock $clock;

	private InMemoryOperationStore $store;

	protected function setUp(): void {
		$this->clock = new FrozenClock(new DateTimeImmutable('2026-07-24T12:00:00+00:00'));
		$this->store = new InMemoryOperationStore();
		$this->events = new CollectingEventDispatcher();
		$this->executor = new Executor(
			store: $this->store,
			transactionBoundary: $this->store,
			resultCodec: new JsonResultCodec(),
			clock: $this->clock,
			eventDispatcher: $this->events,
		);
	}

	public function testItExecutesOnceAndReplaysTheStoredResult(): void {
		$calls = 0;
		$operation = $this->operation();

		$first = $this->executor->execute($operation, function () use (&$calls): array {
			++$calls;

			return ['orderId' => 42];
		});
		$second = $this->executor->execute($operation, function () use (&$calls): array {
			++$calls;

			return ['orderId' => 99];
		});

		self::assertSame(1, $calls);
		self::assertSame(['orderId' => 42], $first->value());
		self::assertSame(['orderId' => 42], $second->value());
		self::assertTrue($first->wasExecuted());
		self::assertTrue($second->wasReplayed());
		self::assertSame(1, $first->attempt());
		self::assertSame(1, $second->attempt());
		self::assertSame([
			OperationClaimed::class,
			OperationExecuted::class,
			OperationCompleted::class,
			OperationReplayed::class,
		], $this->events->classes());
	}

	public function testItRejectsTheSameKeyWithDifferentInput(): void {
		$this->executor->execute($this->operation(), static fn (): array => ['orderId' => 42]);

		try {
			$this->executor->execute(
				$this->operation(PayloadFingerprint::fromString('different')),
				static fn (): array => ['orderId' => 99],
			);
			self::fail('A reused key with a different payload must be rejected.');
		} catch (FingerprintMismatchException $exception) {
			self::assertSame('orders.create', $exception->identity()->scope());
		}

		self::assertSame(FingerprintMismatchDetected::class, $this->events->classes()[3]);
	}

	public function testCallbackFailureRollsBackTheClaimAndIsNeverHidden(): void {
		$exception = new RuntimeException('business failure');

		try {
			$this->executor->execute($this->operation(), static function () use ($exception): never {
				throw $exception;
			});
			self::fail('The callback exception must be propagated.');
		} catch (RuntimeException $caught) {
			self::assertSame($exception, $caught);
		}

		$result = $this->executor->execute(
			$this->operation(),
			static fn (): array => ['orderId' => 42],
		);

		self::assertTrue($result->wasExecuted());
		self::assertSame(1, $result->attempt());
		self::assertSame([
			OperationClaimed::class,
			OperationFailed::class,
			OperationClaimed::class,
			OperationExecuted::class,
			OperationCompleted::class,
		], $this->events->classes());
	}

	public function testEncodingFailureRollsBackTheClaim(): void {
		try {
			$this->executor->execute($this->operation(), static fn (): object => new stdClass());
			self::fail('Objects must not be serialized implicitly.');
		} catch (ResultEncodingFailedException) {
		}

		$result = $this->executor->execute($this->operation(), static fn (): array => ['safe' => true]);

		self::assertTrue($result->wasExecuted());
		self::assertSame(1, $result->attempt());
	}

	public function testAnExistingProcessingClaimIsReportedAsInProgress(): void {
		$operation = $this->operation();
		$this->store->claim($operation, 'other-owner', $this->clock->now());

		$this->expectException(OperationInProgressException::class);

		$this->executor->execute($operation, static fn (): array => []);
	}

	public function testAnExpiredResultAllowsTheKeyToExecuteAgain(): void {
		$this->executor->execute($this->operation(), static fn (): array => ['version' => 1]);
		$this->clock->advance(new DateInterval('P2D'));

		$result = $this->executor->execute(
			$this->operation(PayloadFingerprint::fromString('new-input')),
			static fn (): array => ['version' => 2],
		);

		self::assertTrue($result->wasExecuted());
		self::assertSame(2, $result->attempt());
		self::assertSame(['version' => 2], $result->value());
	}

	private function operation(?PayloadFingerprint $fingerprint = null): Operation {
		return Operation::atomic(
			scope: 'orders.create',
			key: 'request-123',
			fingerprint: $fingerprint ?? PayloadFingerprint::fromString('input'),
			retention: new DateInterval('P1D'),
		);
	}
}

final class CollectingEventDispatcher implements EventDispatcherInterface {
	/**
	 * @var list<object>
	 */
	private array $events = [];

	/**
	 * @return list<class-string>
	 */
	public function classes(): array {
		return array_map(static fn (object $event): string => $event::class, $this->events);
	}

	public function dispatch(object $event): object {
		$this->events[] = $event;

		return $event;
	}
}
