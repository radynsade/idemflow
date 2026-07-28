# IdemFlow Core

Framework-agnostic atomic idempotency primitives for PHP 8.4+.

## MVP scope

The package implements the smallest complete Core execution path:

- immutable operation identity, SHA-256 fingerprint, retention and metadata;
- formal claim decisions: acquired, replay, in progress, fingerprint mismatch, failed and ambiguous;
- an explicit atomic `Executor` API;
- stored result codecs for JSON, scalar and void values;
- owner/attempt checks that reject stale or repeated completion;
- typed public exceptions and lifecycle events;
- a transactional in-memory store for unit tests and examples.

Leases, heartbeat, takeover, reconciliation, pruning and durable failure storage are intentionally not implemented by this MVP. The decision and state types reserve the required semantics without pretending that leased execution is already safe.

## Quick start

```php
use DateInterval;
use IdemFlow\Core\Executor;
use IdemFlow\Core\Operation;
use IdemFlow\Core\PayloadFingerprint;
use IdemFlow\Core\Store\InMemoryOperationStore;

$store = new InMemoryOperationStore();

$executor = new Executor(
	store: $store,
	transactionBoundary: $store,
);

$operation = Operation::atomic(
	scope: 'orders.create',
	key: 'request-123',
	fingerprint: PayloadFingerprint::fromArray([
		'customerId' => 10,
		'items' => [['sku' => 'book', 'quantity' => 2]],
	]),
	retention: new DateInterval('P7D'),
);

$result = $executor->execute(
	$operation,
	fn (): array => ['orderId' => 42],
);

$result->value();       // ['orderId' => 42]
$result->wasExecuted(); // true on the first call
$result->wasReplayed(); // true on later calls
$result->attempt();     // fencing attempt stored with the operation
```

`InMemoryOperationStore` is process-local and intended for tests. It implements both the store and transaction-boundary contracts, but both roles are passed explicitly. It does not provide cross-process exclusion or durable storage.

## Atomic transaction boundary

The executor runs these steps inside one `TransactionBoundaryInterface::transactional()` call:

```text
claim → callback → encode result → conditional completion → commit
```

For a database adapter, the supplied transaction boundary must cover both the IdemFlow record and the application's business writes. If the callback, codec, completion, or commit fails, the transaction must roll back. A transaction boundary that covers only the IdemFlow table cannot make business changes atomic.

The in-memory store implements the transaction contract itself and restores its records when the callback throws.

Do not place network calls or other non-transactional side effects inside atomic mode. A database rollback cannot undo an external request.

## Public API and invariants

The following namespaces are public in the MVP:

- `IdemFlow\Core`: operation and execution value objects plus `Executor`;
- `IdemFlow\Core\Codec`: explicit safe result codecs and their contract;
- `IdemFlow\Core\Contract`: the transaction boundary contract;
- `IdemFlow\Core\Store`: the store contract, claim decisions and in-memory implementation;
- `IdemFlow\Core\Exception`: failures callers are expected to handle;
- `IdemFlow\Core\Event`: lifecycle events and the no-op dispatcher;
- `IdemFlow\Core\Clock`: system and controllable clocks.

`IdemFlow\Core\Internal` is not public API and may change without backward-compatibility guarantees.

Core enforces these invariants:

- identity is `(scope, SHA-256(key))`; scope and key are non-empty valid UTF-8 with bounded byte lengths;
- a retained key cannot be reused with another fingerprint;
- the callback runs only after an `Acquired` decision;
- replay decodes the stored result and never invokes the callback;
- completion must match identity, owner and attempt;
- a completed result cannot be overwritten;
- results use explicit codecs; native PHP object serialization is never used;
- retention expiration permits a new execution, so the application must choose retention carefully.

## Failure behavior

| Failure | Meaning | Safe response |
|---|---|---|
| `OperationInProgressException` | Another owner holds a processing claim | Retry later; do not run the callback independently |
| `FingerprintMismatchException` | The key was reused for different input | Reject the request; retry only with the original input or a new key |
| `OperationPreviouslyFailedException` | A store reports a retained failure | Follow application failure policy; Core does not retry automatically |
| `AmbiguousOperationException` | The side-effect outcome is unknown | Reconcile; never retry a non-idempotent side effect blindly |
| `StaleOperationClaimException` | Owner or attempt no longer matches | Do not write the result; a newer owner may exist |
| `ResultEncodingFailedException` | The result is unsupported or too large | The atomic transaction rolls back; change codec/result before retrying |
| `ResultDecodingFailedException` | A retained result is corrupt or incompatible | Treat as infrastructure/schema failure; do not execute the callback |

Exceptions thrown by the user's callback are rethrown unchanged after rollback. Core performs no hidden callback retries.

## Process-crash outcomes

- Before claim: nothing exists; a later delivery may execute.
- After claim but before callback: the atomic database transaction rolls back when the connection closes.
- During callback: transactional database writes and the claim roll back together. External effects cannot be rolled back.
- After business writes but before completion: writes and claim roll back when they use the same transaction.
- After completion but before commit: everything rolls back.
- After commit but before the caller receives a response: the next delivery replays the stored result.

Lifecycle events for successful execution are dispatched after the transaction returns successfully. Event listeners should be observational and should not throw: at that point the operation may already be committed.

## Development

```bash
composer test
# From the monorepository root:
composer analyse
```
