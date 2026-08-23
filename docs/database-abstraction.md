# Prefab database abstraction

Prefab keeps database access framework-independent without making `tihloh/prefab-database` a required dependency.

## Goals

The database architecture follows the same Prefab rules as the rest of the project:

1. every module remains standalone;
2. plain PDO continues to work directly;
3. Prefab Database is an optional convenience block;
4. framework adapters can participate without replacing the framework;
5. automatic integration is overridable and visible through diagnostics.

## Shared interoperability contract

Database-consuming modules use `Tihloh\Prefab\DatabaseInterface` internally:

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

The contract is intentionally small. It represents the minimum common behavior needed by Prefab modules and future framework adapters.

The richer Laravel-like query builder is provided by Prefab Database itself and is not required by every standalone module.

## Plain PDO remains valid

Existing code does not need Prefab Database:

```php
$pdo = new PDO($dsn, $username, $password);

$users = new UserManager([
    'database' => $pdo,
]);
```

The module automatically normalizes PDO through `PdoDatabaseAdapter`:

```text
PDO
 ↓
PdoDatabaseAdapter
 ↓
DatabaseInterface
 ↓
Prefab module
```

No extra package or configuration is required.

## Prefab Database

When installed, `DatabaseManager` implements `DatabaseInterface` directly and publishes the default database capability:

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

The runtime publishes:

```text
database                    -> DatabaseManager
database.connection.main    -> PdoDatabaseAdapter
database.connection.logs    -> PdoDatabaseAdapter
```

Consumers only depend on `DatabaseInterface`, not the concrete provider.

## Configuration priority

Database resources follow the normal three-level Prefab configuration rule:

```text
1. direct module database / connection
2. module-specific PrefabConfig
3. common PrefabConfig
4. compatible database capability
5. clear configuration error when required
```

A direct PDO value and a `DatabaseInterface` implementation are both accepted.

## Module behavior

### Users

`UserManager` still works through `UserProviderInterface`. When database-backed, its built-in provider consumes `DatabaseInterface` rather than depending directly on PDO.

### Permissions

`PermissionManager` still works through `PermissionStoreInterface`. Its built-in database store accepts either `DatabaseInterface` or PDO.

### Logs

`LogManager` still works through `LogRepositoryInterface`. Its built-in database repository accepts either `DatabaseInterface` or PDO.

### Auth

Auth does not directly use a database. It consumes an authentication user provider, normally supplied by Prefab Users or a project/framework adapter.

## Framework compatibility

A framework integration only needs to adapt its database service to `DatabaseInterface`.

Conceptually:

```text
Laravel DB / Doctrine DBAL / custom database layer
                    ↓
             framework adapter
                    ↓
            DatabaseInterface
                    ↓
       Users / Permissions / Logs
```

Prefab modules therefore do not need to know which framework supplied the resource.

## Database-specific SQL

The interface removes connection/provider coupling, but database engines still differ in DDL and some SQL operations. Current built-in repositories isolate those remaining differences locally for MySQL/MariaDB, PostgreSQL, SQLite, and SQL Server.

A future optional schema abstraction can centralize DDL differences without expanding the minimal `DatabaseInterface` prematurely.

## Embedded contract maintenance

There is no Core package. The repository keeps the canonical database interoperability source at:

```text
tools/database-contracts.php
```

and embeds guarded copies at:

```text
packages/database/src/database.php
packages/users/src/database.php
packages/permissions/src/database.php
packages/logs/src/database.php
```

Run from the repository root before release:

```bash
php tools/sync-prefab-bootstrap.php
```

The same maintenance script synchronizes both `prefab.php` and `database.php` interoperability files. Published packages remain self-contained.
