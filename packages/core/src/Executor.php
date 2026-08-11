<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

use IdemFlow\Core\Clock\SystemClock;
use IdemFlow\Core\Codec\JsonResultCodec;
use IdemFlow\Core\Codec\ResultCodecInterface;
use IdemFlow\Core\Contract\TransactionBoundaryInterface;
use IdemFlow\Core\Event\FingerprintMismatchDetected;
use IdemFlow\Core\Event\NullEventDispatcher;
use IdemFlow\Core\Event\OperationClaimed;
use IdemFlow\Core\Event\OperationCompleted;
use IdemFlow\Core\Event\OperationExecuted;
use IdemFlow\Core\Event\OperationFailed;
use IdemFlow\Core\Event\OperationReplayed;
use IdemFlow\Core\Exception\AmbiguousOperationException;
use IdemFlow\Core\Exception\FingerprintMismatchException;
use IdemFlow\Core\Exception\OperationInProgressException;
use IdemFlow\Core\Exception\OperationPreviouslyFailedException;
use IdemFlow\Core\Exception\ResultCodecMismatchException;
use IdemFlow\Core\Exception\ResultDecodingFailedException;
use IdemFlow\Core\Exception\ResultEncodingFailedException;
use IdemFlow\Core\Exception\StaleOperationClaimException;
use IdemFlow\Core\Internal\CallbackExecutionFailed;
use IdemFlow\Core\Store\Claim\Acquired;
use IdemFlow\Core\Store\Claim\Ambiguous;
use IdemFlow\Core\Store\Claim\Failed;
use IdemFlow\Core\Store\Claim\FingerprintMismatch;
use IdemFlow\Core\Store\Claim\InProgress;
use IdemFlow\Core\Store\Claim\Replay;
use IdemFlow\Core\Store\OperationStoreInterface;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class Executor {
	private readonly ClockInterface $clock;

	private readonly EventDispatcherInterface $eventDispatcher;

	private readonly ResultCodecInterface $resultCodec;

	public function __construct(
		private readonly OperationStoreInterface $store,
		private readonly TransactionBoundaryInterface $transactionBoundary,
		?ResultCodecInterface $resultCodec = null,
		?ClockInterface $clock = null,
		?EventDispatcherInterface $eventDispatcher = null,
	) {
		$this->resultCodec = $resultCodec ?? new JsonResultCodec();
		$this->clock = $clock ?? new SystemClock();
		$this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher();
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return ExecutionResult<T>
	 * @throws AmbiguousOperationException When the retained operation outcome is ambiguous.
	 * @throws FingerprintMismatchException When the operation key is reused with a different fingerprint.
	 * @throws OperationInProgressException When another owner is currently executing the operation.
	 * @throws OperationPreviouslyFailedException When the operation has a retained failure.
	 * @throws ResultCodecMismatchException When the retained result uses a different codec.
	 * @throws ResultDecodingFailedException When the retained result cannot be decoded.
	 * @throws ResultEncodingFailedException When the callback result cannot be encoded.
	 * @throws StaleOperationClaimException When the claim loses ownership before completion.
	 */
	public function execute(Operation $operation, callable $callback): ExecutionResult {
		/** @var list<object> $pendingEvents */
		$pendingEvents = [];

		try {
			/** @var ExecutionResult<T> $result */
			$result = $this->transactionBoundary->transactional(function () use (
				$operation,
				$callback,
				&$pendingEvents,
			): ExecutionResult {
				$now = $this->clock->now();
				$decision = $this->store->claim($operation, self::newOwnerId(), $now);

				if ($decision instanceof Replay) {
					$stored = $decision->storedResult();

					$pendingEvents[] = new OperationReplayed(
						$operation->identity(),
						$stored->attempt(),
						$now,
					);

					$result = ExecutionResult::replayed(
						$this->resultCodec->decode($stored->result()),
						$stored->attempt(),
					);
				} else {
					if ($decision instanceof InProgress) {
						throw new OperationInProgressException(
							$decision->identity(),
							$decision->attempt(),
							$decision->startedAt(),
						);
					}

					if ($decision instanceof FingerprintMismatch) {
						$pendingEvents[] = new FingerprintMismatchDetected(
							$decision->identity(),
							$decision->stored(),
							$decision->received(),
							$now,
						);

						throw new FingerprintMismatchException(
							$decision->identity(),
							$decision->stored(),
							$decision->received(),
						);
					}

					if ($decision instanceof Failed) {
						throw new OperationPreviouslyFailedException(
							$decision->identity(),
							$decision->attempt(),
							$decision->isRetryable(),
						);
					}

					if ($decision instanceof Ambiguous) {
						throw new AmbiguousOperationException($decision->identity(), $decision->attempt());
					}

					if (!$decision instanceof Acquired) {
						throw new LogicException(sprintf('Unsupported claim decision %s.', $decision::class));
					}

					$claim = $decision->claim();

					try {
						$value = $callback();
					} catch (Throwable $exception) {
						throw new CallbackExecutionFailed($claim, $this->clock->now(), $exception);
					}

					$completedAt = $this->clock->now();
					$encoded = $this->resultCodec->encode($value);

					$this->store->complete(
						$claim,
						$encoded,
						$completedAt,
						$operation->expiresAt($completedAt),
					);

					$pendingEvents = [
						new OperationClaimed($operation->identity(), $claim->attempt(), $claim->startedAt()),
						new OperationExecuted($operation->identity(), $claim->attempt(), $completedAt),
						new OperationCompleted($operation->identity(), $claim->attempt(), $completedAt),
					];

					$result = ExecutionResult::executed($value, $claim->attempt());
				}

				return $result;
			});
		} catch (CallbackExecutionFailed $exception) {
			$previous = $exception->getPrevious();

			if ($previous === null) {
				throw new LogicException('Callback failure lost its original exception.', previous: $exception);
			}

			$this->eventDispatcher->dispatch(new OperationClaimed(
				$exception->claim->identity(),
				$exception->claim->attempt(),
				$exception->claim->startedAt(),
			));

			$this->eventDispatcher->dispatch(new OperationFailed(
				$exception->claim->identity(),
				$exception->claim->attempt(),
				$previous::class,
				$exception->failedAt,
			));

			throw $previous;
		} catch (FingerprintMismatchException $exception) {
			$this->dispatchAll($pendingEvents);
			throw $exception;
		}

		$this->dispatchAll($pendingEvents);
		return $result;
	}

	/**
	 * @param list<object> $events
	 */
	private function dispatchAll(array $events): void {
		foreach ($events as $event) {
			$this->eventDispatcher->dispatch($event);
		}
	}

	private static function newOwnerId(): string {
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
		$hex = bin2hex($bytes);

		return sprintf(
			'%s-%s-%s-%s-%s',
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20),
		);
	}
}
