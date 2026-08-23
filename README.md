# Tihloh Prefab PHP

Reusable, modular PHP components that work standalone and automatically cooperate when compatible Prefab modules are used together.

## Packages

- `tihloh/prefab-database` — shared/default and named PDO connection management
- `tihloh/prefab-users` — user management and provider abstraction
- `tihloh/prefab-auth` — authentication and social sign-in building blocks
- `tihloh/prefab-permissions` — permission definitions, group inheritance and user overrides
- `tihloh/prefab-logs` — structured audit/activity logging with technical and human-friendly views

There is no required Core package. Install only the module or modules a project needs.

## Design principles

Each Prefab module follows the same rules:

1. **Standalone first** — a module must work without the other Prefabs.
2. **Automatic cooperation** — compatible modules connect automatically when present.
3. **Explicit configuration wins** — a module-local option affects only that module.
4. **Shared configuration is optional** — common resources can be declared once before module construction.
5. **No repeated discovery during normal feature calls** — integration is resolved during module declaration/configuration passes.
6. **Project data stays project-owned** — Prefab may map to an existing project table without taking ownership of it.
7. **Prefab-owned storage is self-contained** — modules such as Permissions and Logs may create only their own required tables.

## Database Connection Manager

Prefab Database is optional. It is useful when an application has one shared database connection or several named connections that should be reused by multiple modules.

```php
use Tihloh\Prefab\Database\Services\DatabaseManager;

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

Users and Permissions inherit the Database Manager's default connection because they were not given their own database. Logs explicitly selects the named `logs` connection, and that choice affects Logs only.

The manager can also create PDO connections from configuration arrays containing `dsn`, `username`, `password`, and `options`.

## Optional shared configuration

A project may declare common resources before constructing modules:

```php
use Tihloh\Prefab\PrefabConfig;

PrefabConfig::set([
    'modules' => [
        'database' => [
            'default' => 'main',
            'connections' => [
                'main' => $mainPdo,
                'logs' => $logPdo,
            ],
        ],

        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);
```

Then modules can be declared normally:

```php
$database = new DatabaseManager();
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

## Configuration resolution

Configuration is resolved per resource/setting rather than per entire module.

Typical database/resource priority:

```text
Explicit module constructor/configuration
              ↓
Module-specific shared configuration
              ↓
Named Prefab Database connection
              ↓
Prefab Database default connection
              ↓
Other compatible Prefab resource
              ↓
Clear configuration error if still unresolved
```

This allows partial overrides. A module may override only its table name or connection name while still inheriting everything else.

## Permission definitions and inheritance

Permission definitions may be provided as:

```text
inline PHP array
PHP template file returning an array
JSON template file
```

Effective permission resolution is:

```text
User override
    ↓
Group permission
    ↓
Permission default
```

A user-level override always takes priority. Clearing that override restores inheritance from the user's groups or the permission default.

## Logging

Prefab Logs stores one structured technical/audit record. It can be displayed in two ways:

```php
$technicalLogs = $logs->recent(50);

$humanLogs = $logs->humanRecent(
    50,
    actorResolver: fn ($id) => $users->find($id)?->name,
    subjectResolver: function ($type, $id) use ($users) {
        if ($type === 'user') {
            return $users->find($id)?->name;
        }

        return null;
    },
);
```

A technical record such as `permission.denied` can therefore be shown as:

```text
Demo Admin denied View Documents for Test User.
```

without duplicating the underlying log entry.

## Source-code documentation standard

Source documentation is part of the Prefab API. New and updated code should follow these rules:

- public classes describe their responsibility and integration behavior with PHPDoc;
- public methods explain important parameters, return values and side effects;
- contracts/interfaces document what implementers are expected to provide;
- DTOs document the meaning of non-obvious fields;
- non-obvious configuration, inheritance and discovery behavior has concise inline comments;
- examples use normal indentation and readable multi-line formatting rather than compressed one-line PHP;
- comments explain why behavior exists rather than repeating obvious PHP syntax;
- sensitive information such as password hashes, tokens and secrets is not exposed in human-friendly logs;
- package READMEs document setup, standalone use, automatic integration, configuration overrides, storage ownership and common examples.

## Examples

See `examples/` for runnable projects, especially `examples/database-integration-test`.

That example demonstrates project-owned users/groups, Prefab Users mapping, Auth integration, permission inheritance, separate log storage, and human/technical logs.

See also `docs/auto-integration.md` and each package's own README for module-specific details.
