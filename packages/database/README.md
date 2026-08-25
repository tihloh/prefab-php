# Prefab Database

**Prefab Database** provides framework-independent database connection management and a lightweight query builder for PHP applications.

> Use PDO directly when you want it. Use the query builder when it makes common application CRUD clearer.

Prefab Database is optional. Other Prefab modules do not require it; installing it simply provides a shared database capability that compatible modules may inherit automatically.

## Requirements

- PHP 8.1 or newer
- PDO
- the appropriate PDO driver for the target database
- Composer when installed as a package

## Installation

```bash
composer require tihloh/prefab-database
```

## Supported database targets

First-class PDO targets are:

| Database | Driver |
|---|---|
| MySQL | `mysql` |
| MariaDB | `mariadb` / MySQL PDO driver |
| PostgreSQL | `pgsql` |
| SQLite | `sqlite` |
| SQL Server | `sqlsrv` |

Any usable PDO connection may still be supplied directly. First-class support means Prefab intentionally handles common SQL differences needed by its query API and built-in module repositories.

---

# 1. Quick start

```php
use Tihloh\Prefab\Database\Services\DatabaseManager;

$database = new DatabaseManager([
    'connections' => [
        'main' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'app',
            'username' => 'app',
            'password' => 'secret',
        ],
    ],
]);
```

Then:

```php
$users = $database
    ->table('users')
    ->where('active', true)
    ->get();
```

Prefab Database is not an ORM. It intentionally stays close to SQL and PDO.

---

# 2. Multiple connections

A manager may own several named connections:

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => [
            'driver' => 'mysql',
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

Conceptually:

```text
DatabaseManager
├── main   → MySQL/MariaDB
└── logs   → SQLite
```

Select a connection:

```php
$main = $database->connection('main');
$logs = $database->connection('logs');
```

---

# 3. Existing PDO connections

Prefab does not require it to create the PDO connection.

```php
$pdo = new PDO($dsn, $username, $password);

$database = new DatabaseManager([
    'connections' => [
        'main' => $pdo,
    ],
]);
```

This is useful when adding Prefab Database to an existing project that already owns connection creation.

---

# 4. Database interoperability contract

Database-consuming Prefab modules use a small framework-independent contract internally:

```php
interface DatabaseInterface
{
    public function select(string $sql, array $bindings = []): array;
    public function statement(string $sql, array $bindings = []): bool;
    public function transaction(callable $callback): mixed;
    public function driver(): string;
    public function lastInsertId(?string $name = null): string|false;
    public function pdo(): PDO;
}
```

`DatabaseManager` implements this contract directly.

A plain PDO object is normalized automatically:

```text
PDO
 ↓
PdoDatabaseAdapter
 ↓
DatabaseInterface
 ↓
Prefab module
```

Therefore this remains valid in a standalone module:

```php
$users = new UserManager([
    'database' => $pdo,
]);
```

The module does not need to know whether the capability ultimately came from Prefab Database, a PDO adapter, or a future framework adapter.

---

# 5. Why the shared interface is small

The richer `table()` query builder deliberately does not belong to the tiny interoperability contract.

That distinction keeps modules lightweight:

```text
DatabaseInterface
    ↓
small common contract
(select / statement / transaction / driver / PDO escape hatch)

Prefab Database
    ↓
optional richer conveniences
(connections + query builder + diagnostics)
```

A module should not have to depend on an entire database toolkit just to execute a query.

---

# 6. Query builder: select

Find a record:

```php
$user = $database
    ->table('users')
    ->where('id', 10)
    ->first();
```

Retrieve several records:

```php
$activeUsers = $database
    ->table('users')
    ->where('active', true)
    ->orderBy('name')
    ->limit(20)
    ->get();
```

The builder provides a common API for routine CRUD while leaving raw SQL available for anything more specialized.

---

# 7. Query builder: insert

```php
$id = $database
    ->table('users')
    ->insertGetId([
        'name' => 'Demo User',
        'email' => 'demo@example.com',
    ]);
```

Use the returned ID where the database/driver supports generated identifiers through the normal abstraction.

---

# 8. Query builder: update

```php
$database
    ->table('users')
    ->where('id', $id)
    ->update([
        'name' => 'Updated User',
    ]);
```

---

# 9. Query builder: delete

```php
$database
    ->table('users')
    ->where('id', $id)
    ->delete();
```

The query builder intentionally remains small rather than attempting to reproduce a full ORM.

---

# 10. Raw SQL

When SQL is clearer or database-specific functionality is needed, use raw SQL directly:

```php
$rows = $database->select(
    'SELECT * FROM users WHERE active = ?',
    [1],
);
```

Statements:

```php
$success = $database->statement(
    'UPDATE users SET active = ? WHERE id = ?',
    [false, 10],
);
```

Prefab does not hide SQL merely for the sake of abstraction.

---

# 11. Transactions

```php
$database->transaction(function ($db) {
    $db->table('users')->insert([
        'name' => 'Transactional User',
    ]);
});
```

Transactions allow several related operations to be treated as one database unit according to the underlying driver behavior.

---

# 12. Querying a named connection

A table operation can target a named connection:

```php
$rows = $database
    ->table('prefab_logs', 'logs')
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get();
```

This makes patterns such as a separate audit database straightforward.

---

# 13. Raw PDO escape hatch

Raw PDO remains intentionally accessible:

```php
$pdo = $database->connection('main');
$defaultPdo = $database->pdo();
```

Prefab Database is a convenience layer, not a barrier between the application and PDO.

Use raw PDO when the database feature you need is outside the lightweight Prefab API.

---

# 14. Runtime connection management

Useful connection operations include:

```php
$database->default();
$database->defaultName();
$database->connection('main');
$database->driver('main');
$database->has('main');
$database->names();
$database->ping('main');
```

A connection can also be registered dynamically:

```php
$database->set('archive', $archivePdo);
```

Change the default:

```php
$database->useDefault('archive');
```

---

# 15. Automatic Prefab integration

A modular application can define shared connections once:

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
Users        → main
Permissions  → main
Logs         → logs
```

Compatible modules consume `DatabaseInterface`, not a raw implementation-specific dependency.

---

# 16. Configuration levels

Prefab modules use a consistent precedence model:

```text
1. Direct module constructor configuration
2. Module-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal/default behavior where applicable
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

This allows one common application default while preserving per-module and per-instance overrides.

---

# 17. Direct vs module vs common configuration

Suppose the application has a common database but Logs should use another connection:

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

Then an individual instance can still override that:

```php
$logs = new LogManager([
    'database' => $temporaryLogPdo,
]);
```

Conceptually:

```text
Direct instance config      highest
        ↓
Module PrefabConfig
        ↓
Common PrefabConfig
        ↓
Auto-discovered capability
        ↓
Default/error               lowest
```

---

# 18. Prefab Users integration

Users may consume the default database capability automatically:

```text
Prefab Database
      ↓
DatabaseInterface
      ↓
Prefab Users
```

Users remains independently installable and can still receive PDO directly.

---

# 19. Prefab Permissions integration

Permissions can use the same common database or select a named security connection.

```text
DatabaseManager
├── main      → application
├── security  → permissions
└── logs      → audit
```

This makes infrastructure sharing optional rather than mandatory.

---

# 20. Prefab Logs integration

Logs commonly benefits from a dedicated connection:

```php
$logs = new LogManager([
    'connection' => 'logs',
]);
```

If the named connection exists in Prefab Database, Logs can resolve it through the shared capability system.

---

# 21. Framework adapters

Because modules depend on `DatabaseInterface` rather than `DatabaseManager`, a future framework adapter can expose the same operations:

```text
Laravel / Doctrine / framework DB
             ↓
       adapter
             ↓
     DatabaseInterface
             ↓
      Prefab modules
```

Prefab Database itself therefore remains an optional convenience package rather than a mandatory core dependency.

---

# 22. Diagnostics

Inspect the database manager:

```php
$database->explain();
```

Inspect broader Prefab capability resolution:

```php
PrefabRuntime::inspect();
```

Diagnostics are intended to answer questions such as:

```text
Which connection is default?
Which connections exist?
Where did this capability come from?
Which module inherited which database?
```

without exposing actual credentials or connection objects unnecessarily.

---

# 23. Practical small application

```php
$database = new DatabaseManager([
    'connections' => [
        'main' => $pdo,
    ],
]);

$user = $database
    ->table('users')
    ->where('id', 25)
    ->first();
```

No other Prefab package is required.

---

# 24. Practical multi-database application

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
        'security' => $securityPdo,
        'logs' => $logPdo,
    ],
]);
```

Conceptually:

```text
Application
    ↓
DatabaseManager
├── main      → business data
├── security  → authorization data
└── logs      → audit data
```

Modules may select the connection appropriate to their responsibility.

---

# 25. API quick reference

Connection management:

| API | Purpose |
|---|---|
| `default()` | Return the default connection |
| `defaultName()` | Return its configured name |
| `connection()` | Return a named connection |
| `driver()` | Return the connection driver |
| `has()` | Test whether a connection exists |
| `names()` | Return connection names |
| `ping()` | Test a connection |
| `set()` | Register/replace a runtime connection |
| `useDefault()` | Change the default connection |
| `pdo()` | Return raw PDO |

Database operations:

| API | Purpose |
|---|---|
| `table()` | Start a lightweight query |
| `select()` | Execute a SELECT/query and return rows |
| `statement()` | Execute a modifying/raw statement |
| `transaction()` | Run work in a transaction |
| `lastInsertId()` | Return the last generated identifier |

Common query-builder operations demonstrated by the package include `where()`, `orderBy()`, `limit()`, `get()`, `first()`, `insertGetId()`, `update()` and `delete()`.

---

# 26. Design philosophy

Prefab Database is intentionally positioned between raw PDO and a full database framework:

```text
Raw PDO
   ↓
Prefab Database
   ↓
connections + lightweight query builder
   ↓
optional shared Prefab capability
```

It does not try to become an ORM and it does not become a mandatory dependency of other modules.

A small application can use one PDO connection. A larger system can manage multiple connections and let compatible modules inherit them automatically.

The core principle is: **provide useful database infrastructure without taking ownership of the application's database architecture.**
