# Prefab Core

`prefab-core` is the shared infrastructure layer used by Prefab feature modules.

It is not a feature module. Applications normally install a feature package such as `tihloh/prefab-users`; Composer installs Core automatically as a dependency.

## Core responsibilities

Core provides infrastructure that multiple modules can share without depending on one another:

- runtime, configuration, capabilities, and lifecycle
- database connections, PDO adapter, transactions, basic SQL, and lightweight query building
- session storage through a small session contract and native PHP implementation
- cache contracts plus lightweight in-memory and file-backed implementations
- diagnostics, tracing, redaction, and debug rendering
- shared contracts and interoperability helpers

Feature modules decide **how** they use this infrastructure. For example, Core provides cache storage, but `prefab-users` or `prefab-permissions` decides whether caching is enabled, which values are cacheable, their TTL, and when cache entries must be invalidated.

Core must not contain business features. User management, authentication rules, permissions, application/audit logging, routing, validation, file management, messaging, and notifications remain separate packages.

## Architecture

```text
prefab-core
├─ Runtime / Config
├─ Database
├─ Session
├─ Cache
├─ Contracts
└─ Diagnostics

prefab-users
prefab-auth
prefab-permissions
prefab-logs
prefab-routes
prefab-input
prefab-files
prefab-messaging
prefab-notifications
```

Feature modules should depend on Core rather than on other feature modules unless a hard feature dependency is genuinely required. Optional integrations use Core capabilities/contracts at runtime.

## Database

Core database infrastructure is intentionally small. It supports named PDO connections, parameterized SQL, transactions, and the lightweight query builder. It is not an ORM and should not grow into models, relations, migrations, or schema-design features.

```php
use Tihloh\Prefab\Core\Database\DatabaseManager;

$db = new DatabaseManager(new PDO('sqlite::memory:'));
$rows = $db->table('users')->where('active', 1)->get();
```

The existing `prefab-database` package remains temporarily during migration for backward compatibility. The target architecture is for basic database infrastructure to live in Core rather than requiring a separate Database feature package.

## Session

```php
use Tihloh\Prefab\Core\Session\NativeSession;

$session = new NativeSession();
$session->set('user_id', 42);
$id = $session->get('user_id');
```

Authentication behavior remains in `prefab-auth`; Core only provides session mechanics.

## Cache

```php
use Tihloh\Prefab\Core\Cache\FileCache;

$cache = new FileCache(__DIR__ . '/cache');
$users = $cache->remember('users.all', 300, fn () => loadUsers());
```

Caching remains opt-in at the feature-module level. Core supplies the mechanism, while each module owns its cache policy and invalidation rules.

## Development

The monorepo remains the source of truth. Canonical shared runtime/diagnostic files currently live under `tools/` and are synchronized into `packages/core/src/` by:

```bash
php tools/sync-prefab-bootstrap.php
```

During migration the same script also keeps legacy shared copies inside existing feature packages synchronized. Those copies can be removed after `prefab-core` is published and all feature packages require it.
