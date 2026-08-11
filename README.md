# IdemFlow

**Durable idempotency for PHP business operations.**

IdemFlow is an open-source PHP 8.4+ library for the durable, idempotent execution of business operations. It helps backend applications prevent duplicate effects when the same request, message, or event is delivered more than once or processed concurrently.

## The problem

Retries are unavoidable in distributed systems. Clients retry timed-out requests, message brokers redeliver unacknowledged messages, webhook providers resend events, and multiple application instances may process the same input concurrently.

Without reliable idempotency, these situations can create duplicate orders, repeated payments, multiple inventory reservations, and other unintended side effects.

Storing an idempotency key is not enough. Applications must also handle concurrency, keys reused with different input, process failures, completed operations, and external side effects with uncertain outcomes.

## The solution

IdemFlow gives each logical business operation a stable identity and provides consistent handling for repeated attempts. It makes conflicts, concurrent processing, failures, and uncertain outcomes explicit instead of promising universal exactly-once execution.

## Use cases

- API requests and form submissions
- Webhooks and message queue deliveries
- Orders, payments, refunds, and inventory reservations
- Background jobs and scheduled tasks
- Provisioning, imports, and synchronization

## Packages

- [`radynsade/idemflow-core`](packages/core) — framework-independent operation model, atomic executor, result codecs, store contracts, lifecycle events, and in-memory test implementation.

## License

IdemFlow is open-source software licensed under the MIT License.
