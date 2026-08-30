# Prefab Core

`prefab-core` is the shared infrastructure layer used by Prefab feature modules.

It is not a business feature module. Applications normally install a feature package such as `tihloh/prefab-users`; Composer installs Core automatically as a dependency. You may also install Core directly when you only need its infrastructure APIs.

## Core responsibilities

Core provides infrastructure shared across Prefab:

- runtime, configuration, capabilities, lifecycle and fluent-extension plumbing
- database connections, PDO adapter, transactions, parameterized SQL and lightweight query building
- session storage through a small session contract and native PHP implementation
- cache contracts plus in-memory and file-backed cache implementations
- lightweight CLI command registration, argument/options parsing and terminal output
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
├─ Console
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

```php
use Tihloh\Prefab\Core\Cache\FileCache;

$cache = new FileCache(__DIR__ . '/cache');
$users = $cache->remember('users.all', 300, fn () => loadUsers());
```

Available lightweight stores include `ArrayCache` and `FileCache`. Core owns storage mechanics only. A feature module owns its own cache policy: whether caching is enabled, which reads are safe to cache, TTL values and invalidation after writes.

## CLI

Composer exposes Core's console executable as `vendor/bin/prefab`.

```bash
php vendor/bin/prefab list
php vendor/bin/prefab help init
php vendor/bin/prefab about
php vendor/bin/prefab init
```

`init` creates optional project directories only when explicitly requested:

```text
config/
bootstrap/
storage/
app/Console/
```

You can choose another project path:

```bash
php vendor/bin/prefab init --path=/path/to/project
```

Register application or feature commands with the small helper API:

```php
use Tihloh\Prefab\Core\Console\Input;
use Tihloh\Prefab\Core\Console\Output;

prefab_command('user:create', function (Input $input, Output $output) {
    $name = $input->argument(0);
    $email = $input->option('email');
    $admin = $input->flag('admin');

    $output->info("Creating {$name}");
}, 'Create a user');
```

Run it with:

```bash
php vendor/bin/prefab user:create Juan --admin --email=juan@example.com
```

When `bootstrap/prefab.php` exists in the current project directory, the CLI loads it automatically before dispatching a command. This gives applications one place to register commands and initialize Prefab services.

Core provides the command infrastructure; feature packages remain responsible for their own feature-specific commands.

## Runtime and diagnostics

Core owns the canonical Prefab runtime and diagnostics. Feature modules use the Core runtime instead of shipping duplicate runtime, diagnostics or database-contract files.

Useful diagnostics include module/resource inspection and tracing through the shared runtime.

## Development

The monorepo is the development source of truth. Core itself is now the canonical owner of the shared runtime, diagnostics, database infrastructure, session, cache and console code. Package splitting publishes `packages/core` to `tihloh/prefab-core`.
