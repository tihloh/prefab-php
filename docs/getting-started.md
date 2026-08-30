# Getting Started with Prefab

> **Prefab is a set of PHP building blocks. Install the feature you need; shared infrastructure comes from Core.**

```text
You choose the feature modules.
Composer brings Prefab Core with them.
Prefab connects compatible infrastructure.
Your application keeps the business logic.
```

## 1. What Prefab is — and is not

Prefab is **not a full-stack framework**. It does not require a project skeleton, MVC structure, ORM, base controller or special deployment model.

Current packages are organized like this:

| Need | Package |
|---|---|
| Shared runtime, database, session and cache infrastructure | `prefab-core` |
| Existing/project-owned users | `prefab-users` |
| Login, logout and current user | `prefab-auth` |
| Roles/groups/permissions | `prefab-permissions` |
| HTTP routes | `prefab-routes` |
| Request input, validation and uploads | `prefab-input` |
| File storage | `prefab-files` |
| Audit/activity logs | `prefab-logs` |
| Email/external messages | `prefab-messaging` |
| Internal user notifications | `prefab-notifications` |

The old standalone `prefab-database` package has been retired. Database infrastructure now lives in `prefab-core`.

You still do **not** install every feature package. Install the feature you need and Composer resolves Core automatically.

## 2. Smallest possible use

Install only the feature you need:

```bash
composer require tihloh/prefab-routes
```

```php
require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();
$routes->get('/', fn () => 'Hello');

echo $routes->dispatch();
```

`prefab-core` is installed as the shared infrastructure dependency; you do not have to require it separately for ordinary feature-package use.

## 3. Use Core directly

If you specifically need Core infrastructure, such as the database API, install it directly:

```bash
composer require tihloh/prefab-core
```

```php
use Tihloh\Prefab\Core\Database\DatabaseManager;

$db = new DatabaseManager(new PDO('sqlite::memory:'));
$rows = $db->table('users')->where('active', 1)->get();
```

Core also provides session and lightweight cache infrastructure. It deliberately does not contain business features.

## 4. Add another block

Later you may need validated input:

```bash
composer require tihloh/prefab-input
```

Your application grows progressively:

```text
Routes
  ↓
Routes + Input
  ↓
Routes + Input + Auth
  ↓
Routes + Input + Auth + Permissions
```

All of those feature packages share the same Core infrastructure instead of carrying duplicated runtime/database bootstrap files.

## 5. Three ways modules work together

### A. Auto-Wiring — automatic infrastructure

When one module can safely provide infrastructure another module needs, Prefab can connect them automatically.

```text
Users provides user_provider
          ↓
Auth discovers it
          ↓
Auth authenticates project users
```

Explicit configuration still wins over automatic resolution.

### B. Fluent Extensions — explicit optional capabilities

Compatible modules can add useful actions without hiding business decisions.

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

The provider owns the extension; Users does not need Messaging or Notifications business code embedded inside it.

### C. Object Interoperability

Sometimes ordinary compatible objects are enough:

```text
Input UploadedFile → Files → Messaging attachment
```

Prefab prefers the simplest integration that makes sense.

## 6. Core database replaces prefab-database

For new code use:

```php
use Tihloh\Prefab\Core\Database\DatabaseManager;
```

not the retired standalone Database package namespace.

Core supports named PDO connections, parameterized SQL, transactions and a lightweight query builder. It is intentionally not an ORM.

## 7. Core cache infrastructure

Core includes lightweight in-memory and file-backed cache implementations. Core supplies the storage mechanism; feature modules decide their own safe cache policy and invalidation rules.

Direct Core use is available when you need it:

```php
use Tihloh\Prefab\Core\Cache\FileCache;

$cache = new FileCache(__DIR__ . '/cache');
$value = $cache->remember('example', 300, fn () => expensiveWork());
```

Feature-level automatic caching can expose simpler configuration such as `cache => true` without requiring normal application code to call `remember()` manually.

## 8. Configuration: simple first

Small applications can configure a feature directly:

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);
```

Larger applications can share configuration through Core's `PrefabConfig` runtime infrastructure.

Resolution remains predictable:

```text
Direct module configuration       highest priority
        ↓
Module-specific PrefabConfig
        ↓
Common PrefabConfig
        ↓
Auto-discovered compatible capability
        ↓
Sensible default
        ↓
Clear error if unresolved
```

## 9. Errors and diagnostics

Prefab should fail clearly instead of silently guessing. Invalid input returns validation errors; missing required resources raise clear exceptions; ambiguous integrations are errors rather than guesses.

Inspect a module:

```php
$auth->explain();
$users->explain();
```

Inspect the assembled runtime:

```php
$info = PrefabRuntime::inspect();
```

## 10. Recommended learning order

```text
1. Install PHP + Composer
2. Pick one feature module
3. Build its smallest working example
4. Add another module only when needed
5. Learn Auto-Wiring / Fluent Extensions when modules cooperate
6. Use Core directly when you need database/session/cache infrastructure
```

## Next

- [Main documentation](../README.md)
- [Prefab Core](../packages/core/README.md)
- [Automatic integration / Auto-Wiring](auto-integration.md)
- [Fluent Extensions](fluent-extensions.md)
- [Users](../packages/users/README.md)
- [Auth](../packages/auth/README.md)
- [Permissions](../packages/permissions/README.md)
- [Routes](../packages/routes/README.md)
- [Input](../packages/input/README.md)
- [Files](../packages/files/README.md)
- [Logs](../packages/logs/README.md)
- [Messaging](../packages/messaging/README.md)
- [Notifications](../packages/notifications/README.md)
