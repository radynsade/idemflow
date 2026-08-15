# IdemFlow DBAL

Doctrine DBAL persistence adapter for IdemFlow Core on PHP 8.4+.

The package currently supports PostgreSQL through a database-backed operation store, a PostgreSQL
clock, a transaction boundary and an executable schema migration.

## Installation

```bash
composer require radynsade/idemflow-dbal
```

Requirements:

- PHP 8.4 or later;
- Doctrine DBAL 4.4;
- PostgreSQL;
- the `READ COMMITTED` transaction isolation level;
- auto-commit enabled and no transaction active when `Executor::execute()` is called.

`REPEATABLE READ`, `SERIALIZABLE` and nested or caller-owned transactions are not supported by the
provided `TransactionBoundary`. The repository may need to re-read a row after a concurrent
`insert ... on conflict do nothing`; that row must become visible inside the same transaction, which
is the behavior provided by PostgreSQL `READ COMMITTED`.

## Database schema

Run the migration during deployment, not during application request handling:

```php
<?php

use Doctrine\DBAL\DriverManager;
use IdemFlow\DBAL\Migration\PostgresMigration;

$connection = DriverManager::getConnection([
    'driver' => 'pdo_pgsql',
    'host' => '127.0.0.1',
    'port' => 5432,
    'dbname' => 'application',
    'user' => 'application',
    'password' => 'secret',
]);

$connection->executeStatement('create schema if not exists idemflow');
(new PostgresMigration($connection, 'idemflow.operations'))->up();
```

## Runtime configuration

Use the exact same `Connection` instance for the repository, transaction boundary, PostgreSQL clock
and every business write performed by the callback:

```php
<?php

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use IdemFlow\Core\Codec\JsonResultCodec;
use IdemFlow\DBAL\Factory\PostgresExecutorFactory;

/** @var Connection $connection */
$connection = $container->get(Connection::class);
$connection->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);

$executor = (new PostgresExecutorFactory($connection, 'idemflow.operations'))->create(
	resultCodec: new JsonResultCodec(),
);
```

`PostgresClock` reads `statement_timestamp()` from PostgreSQL with microsecond precision. Using the
database clock prevents clock skew between application processes from changing retention and expiry
decisions.

Do not begin a transaction before calling the executor and do not configure the connection with
auto-commit disabled. The transaction boundary owns the transaction and rejects both cases.

If the callback, result codec, completion or commit fails, the transaction boundary attempts a
rollback and rethrows the original failure. A rollback failure never replaces that failure, and the
unusable connection is closed.

## Executing an operation

The operation key must be stable across retries of the same logical request. The fingerprint must be
derived from the inputs that determine the operation's behavior:

```php
<?php

use DateInterval;
use IdemFlow\Core\Operation;
use IdemFlow\Core\PayloadFingerprint;

$command = [
    'orderId' => 'order-42',
    'amount' => 1250,
];

$operation = Operation::atomic(
    scope: 'orders.create',
    key: 'request-7f16a93d',
    fingerprint: PayloadFingerprint::fromArray($command),
    retention: new DateInterval('P30D'),
);

$result = $executor->execute($operation, function () use ($connection, $command): array {
    // Use the same Connection instance passed to every DBAL component above.
    $connection->executeStatement(
        'insert into orders (id, amount) values (:id, :amount)',
        ['id' => $command['orderId'], 'amount' => $command['amount']],
    );

    return ['orderId' => $command['orderId']];
});

$value = $result->value();
$wasReplayed = $result->wasReplayed();
$attempt = $result->attempt();
```
