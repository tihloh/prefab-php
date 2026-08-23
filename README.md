# Tihloh Prefab PHP

Reusable, modular PHP components that work standalone and automatically cooperate when compatible Prefab modules are used together.

## Packages

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

## Optional shared configuration

A project may declare common resources before constructing modules:

```php
use Tihloh\Prefab\PrefabConfig;

PrefabConfig::set([
    'database' => $mainPdo,

    'modules' => [
        'logs' => [
            'database' => $logPdo,
        ],
    ],
]);
```

Then modules can be declared normally:

```php
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Logs\Services\LogManager;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Services\UserManager;

$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

In the example above, Logs uses `$logPdo`, while Users and Permissions can continue using `$mainPdo`. The Logs override does not modify the shared configuration or affect other modules.

## Configuration resolution

Configuration is resolved per resource/setting rather than per entire module.

Typical priority:

```text
Explicit module constructor/configuration
              ↓
Module-specific shared configuration
              ↓
Module's internal sensible default
              ↓
Shared project resource
              ↓
Compatible Prefab resource
              ↓
Clear configuration error if still unresolved
```

This allows partial overrides. A module may override only its table name while still using the shared database, for example.

## Permission inheritance

Prefab Permissions resolves effective values in this order:

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
// Technical/audit data.
$technicalLogs = $logs->recent(50);

// Compact ordinary-user view.
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

A technical record such as `permission.denied` can therefore be shown to ordinary users as:

```text
Demo Admin denied View Documents for Test User.
```

without duplicating the underlying log entry.

## Source-code documentation standard

Source documentation is part of the Prefab API. New and updated code should follow these rules:

- public classes must describe their responsibility and integration behavior with PHPDoc;
- public methods must explain important parameters, return values and side effects;
- contracts/interfaces must document what implementers are expected to provide;
- DTOs should document the meaning of non-obvious fields;
- non-obvious configuration/inheritance/discovery behavior should have concise inline comments;
- examples must use normal indentation and readable multi-line formatting rather than compressed one-line PHP;
- comments should explain **why** behavior exists, not repeat obvious PHP syntax;
- sensitive information such as password hashes, tokens and secrets must not be exposed in human-friendly logs;
- package READMEs should document setup, standalone use, automatic integration, configuration overrides, storage ownership and common examples.

## Examples

See `examples/` for runnable projects, especially:

```text
examples/database-integration-test
```

That example demonstrates:

- project-owned user/group tables;
- Prefab Users mapping to an existing user table;
- automatic Auth integration;
- user creation;
- groups and user-group relationships;
- permission inheritance and user overrides;
- Prefab Permissions-owned storage;
- a separate database for Prefab Logs;
- technical and human-friendly activity views.

See also `docs/auto-integration.md` and each package's own README for module-specific details.
