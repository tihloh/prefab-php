# Prefab Core

`prefab-core` is the shared infrastructure layer used by Prefab feature modules.

It is not a business feature module. Applications normally install a feature package such as `tihloh/prefab-users`; Composer installs Core automatically as a dependency. You may also install Core directly when you only need its infrastructure APIs.

## Core responsibilities

Core provides infrastructure shared across Prefab:

- runtime, configuration, capabilities, lifecycle and fluent-extension plumbing
- database connections, PDO adapter, transactions, parameterized SQL and lightweight query building
- session storage through a small session contract and native PHP implementation
- cache contracts plus in-memory and file-backed cache implementations
- diagnostics, tracing, redaction and debug rendering
- shared contracts and interoperability helpers

Core does **not** own user management, authentication rules, permissions, audit logging, routing, validation, file management, messaging or notifications. Those remain separate feature packages.

## Current architecture

```text
prefab-core
├─ Runtime / Config
├─ Database
├─ Session
├─ Cache
├─ Contracts
└─ Diagnostics

Feature packages
├─ prefab-users
├─ prefab-auth
├─ prefab-permissions
├─ prefab-logs
├─ prefab-routes
├─ prefab-input
├─ prefab-files
├─ prefab-messaging
└─ prefab-notifications
```

All feature packages depend on Core for shared infrastructure rather than carrying private copies of the runtime/database bootstrap. Optional integrations still use Core capabilities/contracts at runtime so feature packages remain independently useful.

## Database is now part of Core

The standalone `prefab-database` package has been retired from the monorepo and package splitting. New applications should use Core's database API.

If a feature package is already installed, Core is normally already present. To use Core directly:

```bash
composer require tihloh/prefab-core
```

```php
use Tihloh\Prefab\Core\Database\DatabaseManager;

$db = new DatabaseManager(new PDO('sqlite::memory:'));
$rows = $db->table('users')->where('active', 1)->get();
```

Core also keeps database compatibility aliases for code migrating from the earlier database API, but new documentation and new code should use the `Tihloh\Prefab\Core\Database` namespace.

Core database infrastructure intentionally stays small: named PDO connections, parameterized SQL, transactions and a lightweight query builder. It is not an ORM and does not own models, relations, migrations or schema design.

## Session

```php
use Tihloh\Prefab\Core\Session\NativeSession;

$session = new NativeSession();
$session->set('user_id', 42);
$id = $session->get('user_id');
```

Authentication behavior remains in `prefab-auth`; Core only provides session mechanics.

## Cache

Core now contains the shared cache mechanism:

```php
use Tihloh\Prefab\Core\Cache\FileCache;

$cache = new FileCache(__DIR__ . '/cache');
$users = $cache->remember('users.all', 300, fn () => loadUsers());
```

Available lightweight stores include `ArrayCache` and `FileCache`. Core owns storage mechanics only. A feature module owns its own cache policy: whether caching is enabled, which reads are safe to cache, TTL values and invalidation after writes.

This allows feature APIs to support simple configuration such as:

```php
$users = new UserManager([
    'cache' => true,
]);
```

when that feature's automatic cache integration is implemented, without making application code manually call `remember()` for normal module operations.

## Runtime and diagnostics

Core owns the canonical Prefab runtime and diagnostics. Feature modules use the Core runtime instead of shipping duplicate `prefab.php`, `prefab-diagnostics.php` or database contract copies.

Useful diagnostics include module/resource inspection and tracing through the shared runtime.

## Development

The monorepo is the development source of truth. Canonical shared runtime/diagnostic sources live under `tools/` and are synchronized into Core with:

```bash
php tools/sync-prefab-bootstrap.php
```

The synchronization process now targets Core; it no longer recreates the retired legacy Core/database copies inside feature packages.
