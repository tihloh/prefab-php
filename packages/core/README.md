# Prefab Core

`prefab-core` is the shared infrastructure layer used by Prefab feature modules.

It is not a business feature module. Applications normally install a feature package such as `tihloh/prefab-users`; Composer installs Core automatically as a dependency. You may also install Core directly when you only need its infrastructure APIs.

## Core responsibilities

Core provides infrastructure shared across Prefab:

- runtime, configuration, capabilities, lifecycle and fluent-extension plumbing
- automatic `.env` loading and environment-value access
- universal fluent values through `val()`
- date/time convenience utilities
- database connections, PDO adapter, transactions, parameterized SQL and lightweight query building
- session storage through a small session contract and native PHP implementation
- cache contracts plus in-memory and file-backed cache implementations
- lightweight CLI command registration, argument/options parsing and terminal output
- diagnostics, tracing, redaction and debug rendering
- shared contracts and interoperability helpers

Core does **not** own user management, authentication rules, permissions, audit logging, routing, validation, file management, messaging or notifications. Those remain separate feature packages.

## Universal values

`val()` wraps an ordinary PHP value with small fluent operations while keeping native PHP underneath.

```php
$name = val(' Christian ')->trim()->upper();
$age = val('42')->toInt();
$active = val('true')->toBool()->fallback(false);
```

The wrapper deliberately remains fluent so an operation can be extended safely:

```php
$age = val($input)
    ->toInt()
    ->fallback(0);
```

Use `value()` only when external code specifically requires the native scalar/array value:

```php
$age = val('42')->toInt()->value();
```

Nested data uses `get()`:

```php
$name = val($data)
    ->get('user.profile.name')
    ->default('Guest');
```

Formatting is terminal and returns display text directly:

```php
echo val(12500)->format('currency', ['currency' => 'PHP']);
echo val('09171234567')->format('phone');
```

Prefab-owned database boundaries understand `Value` objects and unwrap them automatically:

```php
$db->table('users')->insert([
    'name' => val($_POST['name'] ?? null)->trim()->default('Unknown'),
    'age' => val($_POST['age'] ?? null)->toInt()->fallback(0),
]);

$user = $db->table('users')
    ->where('age', val($_GET['age'] ?? null)->toInt()->fallback(0))
    ->first();
```

An unresolved failed conversion is not silently written to the database; the boundary propagates the failure.

## Environment (.env)

Core automatically looks for a `.env` file when Composer loads Prefab. Existing process/server values win over `.env` values.

```dotenv
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DEBUG=true
```

Prefer Prefab's collision-safe accessor in reusable/framework-integrated code:

```php
$host = prefab_env('DB_HOST');
$port = prefab_env('DB_PORT', 3306);
$debug = prefab_env('DEBUG', false);
```

Values are also available through `getenv()` and `$_ENV`. A global `env()` convenience helper may be available when another library/application has not already defined it, but reusable Prefab code should prefer `prefab_env()` to avoid global-helper ownership conflicts.

A specific dotenv file can be loaded explicitly:

```php
use Tihloh\Prefab\Core\Environment\Env;

Env::load(__DIR__ . '/.env');
```

## Date and time

Use Prefab's explicit helper when reusable code must avoid collisions with application/framework globals:

```php
$when = prefab_datetime('2026-09-03 08:30:00');
echo $when->format('datetime');
```

Date/time values can also be reached through `val()`:

```php
$when = val('2026-09-03')->toDateTime();
```

Common global names such as `now()` can conflict with helpers declared by an application or framework depending on Composer load order. Prefer the Prefab-prefixed API in reusable code.

## Database

Database infrastructure is part of Core. New applications should use the Core namespace:

```php
use Tihloh\Prefab\Core\Database\DatabaseManager;

$db = new DatabaseManager(new PDO('sqlite::memory:'));
$rows = $db->table('users')->where('active', 1)->get();
```

Core intentionally stays small: named PDO connections, parameterized SQL, transactions and a lightweight query builder. It is not an ORM and does not own models, relations, migrations or schema design.

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

Available lightweight stores include `ArrayCache` and `FileCache`.

## CLI

Composer exposes Core's console executable as `vendor/bin/prefab`.

Linux/macOS:

```bash
./vendor/bin/prefab list
./vendor/bin/prefab help init
./vendor/bin/prefab init
```

Windows:

```powershell
.\vendor\bin\prefab list
.\vendor\bin\prefab help init
.\vendor\bin\prefab init
```

Register commands with:

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

When `bootstrap/prefab.php` exists in the current project directory, the CLI loads it before dispatching a command.

## Runtime and diagnostics

Core owns the canonical Prefab runtime and diagnostics. Feature modules use the Core runtime instead of carrying duplicate infrastructure.

Normal trace:

```php
prefab_trace();
```

Detailed trace:

```php
prefab_trace_detailed();
```

Tracing is temporary developer diagnostics; persistent application/audit history belongs to Prefab Logs.

## Development

The monorepo is the development source of truth. Package splitting publishes `packages/core` to `tihloh/prefab-core`.
