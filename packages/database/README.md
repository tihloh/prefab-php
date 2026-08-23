# Tihloh Prefab Database

Standalone, framework-independent database connection management and lightweight query building for Tihloh Prefab PHP.

Prefab Database is **optional**. Users, Auth, Permissions and Logs do not require it. Installing it simply gives compatible modules a shared database capability they may inherit automatically.

## Supported database targets

The first-class PDO targets are:

- MySQL / MariaDB
- PostgreSQL
- SQLite
- SQL Server

Any PDO connection can still be supplied directly. First-class support means Prefab intentionally handles the common SQL differences needed by its query API and built-in module repositories.

## Quick configuration

Connection definitions may use a raw PDO DSN or a convenient driver-based configuration:

```php
use Tihloh\Prefab\Database\Services\DatabaseManager;

$database = new DatabaseManager([
    'default' => 'main',

    'connections' => [
        'main' => [
            'driver' => 'mysql', // mariadb is accepted too
            'host' => '127.0.0.1',
            'database' => 'app',
            'username' => 'app',
            'password' => 'secret',
        ],

        'logs' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/logs.sqlite',
        ],
    ],
]);
```

PostgreSQL uses `driver => pgsql`; SQL Server uses `driver => sqlsrv`.

A ready-made PDO remains valid:

```php
$database = new DatabaseManager([
    'connections' => [
        'main' => $pdo,
    ],
]);
```

## Unified query API

Common application CRUD does not need database-specific SQL:

```php
$user = $database
    ->table('users')
    ->where('id', 10)
    ->first();

$activeUsers = $database
    ->table('users')
    ->where('active', true)
    ->orderBy('name')
    ->limit(20)
    ->get();

$id = $database
    ->table('users')
    ->insertGetId([
        'name' => 'Demo User',
        'email' => 'demo@example.com',
    ]);

$database
    ->table('users')
    ->where('id', $id)
    ->update(['name' => 'Updated User']);

$database
    ->table('users')
    ->where('id', $id)
    ->delete();
```

The query builder intentionally stays small. It is not an ORM and does not try to reproduce every Laravel database feature.

## Raw SQL and transactions

Raw SQL remains available when a project needs database-specific functionality:

```php
$rows = $database->select(
    'SELECT * FROM users WHERE active = ?',
    [1],
);

$affected = $database->statement(
    'UPDATE users SET active = ? WHERE id = ?',
    [false, 10],
);

$database->transaction(function ($db) {
    $db->table('users')->insert([
        'name' => 'Transactional User',
    ]);
});
```

## Multiple named connections

```php
$main = $database->connection('main');
$logs = $database->connection('logs');

$rows = $database
    ->table('prefab_logs', 'logs')
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get();
```

## Automatic Prefab integration

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
        'logs' => $logPdo,
    ],
]);

$users = new UserManager();
$permissions = new PermissionManager();
$logs = new LogManager([
    'connection' => 'logs',
]);
```

Resolved behavior:

```text
Users        -> main
Permissions  -> main
Logs         -> logs
```

The Logs override affects Logs only.

## Three configuration levels

All Prefab modules keep the same priority:

```text
1. Direct module constructor configuration
2. Module-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal default
6. Clear error if a required resource is missing
```

Example:

```php
PrefabConfig::set([
    'database' => $mainPdo,

    'modules' => [
        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);
```

## Diagnostics

```php
$database->explain();
PrefabRuntime::inspect();
```

These expose where automatic configuration came from without exposing the actual connection objects.

## Public API

```php
$database->default();
$database->defaultName();
$database->connection('main');
$database->driver('main');
$database->has('main');
$database->names();
$database->ping('main');
$database->set('archive', $archivePdo);
$database->useDefault('archive');

$database->table('users');
$database->select($sql, $bindings);
$database->statement($sql, $bindings);
$database->transaction($callback);
```

Prefab Database remains a convenience block, never a Core dependency.
