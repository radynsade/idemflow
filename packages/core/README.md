# IdemFlow Core

Framework-agnostic atomic idempotency primitives for PHP 8.4+.

## Implemented scope

The package implements the Core execution path:

- immutable operation identity, SHA-256 fingerprint, retention and metadata;
- formal claim decisions: acquired, replay, in progress, fingerprint mismatch, failed and ambiguous;
- an explicit atomic `Executor` API;
- stored result codecs for JSON, scalar and void values;
- owner/attempt checks that reject stale or repeated completion;
- typed public exceptions and lifecycle events;
- a transactional in-memory store for unit tests and examples.

Leases, heartbeat, takeover, reconciliation, pruning and durable failure storage are not implemented. The decision and state types reserve the required semantics without pretending that leased execution is already safe.

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

## Process-crash outcomes

- Before claim: nothing exists; a later delivery may execute.
- After claim but before callback: the atomic database transaction rolls back when the connection closes.
- During callback: transactional database writes and the claim roll back together. External effects cannot be rolled back.
- After business writes but before completion: writes and claim roll back when they use the same transaction.
- After completion but before commit: everything rolls back.
- After commit but before the caller receives a response: the next delivery replays the stored result.

Lifecycle events for successful execution are dispatched after the transaction returns successfully. Event listeners should be observational and should not throw: at that point the operation may already be committed.
