# Tihloh Prefab PHP

Reusable, modular PHP components for rapid Lego-style development. Every module works standalone, but compatible Prefab modules automatically cooperate when they are used together.

## Monorepo model

`prefab-php` is the **single development repository and source of truth** for all Prefab modules.

```text
prefab-php/
├── packages/
│   ├── database/
│   ├── users/
│   ├── auth/
│   ├── permissions/
│   └── logs/
├── examples/
├── docs/
└── tools/
```

All development, issues, pull requests, CI, examples, interoperability changes and releases originate here.

For Packagist, independently installable Composer packages need their own repository root containing that package's `composer.json`. Prefab therefore uses **generated distribution mirrors** for publication only. Those mirrors are produced automatically from `packages/<module>` and are never edited directly.

```text
prefab-php (source of truth)
        │
        ├── packages/database ──────► prefab-database mirror ──────► Packagist
        ├── packages/users ─────────► prefab-users mirror ─────────► Packagist
        ├── packages/auth ──────────► prefab-auth mirror ──────────► Packagist
        ├── packages/permissions ───► prefab-permissions mirror ───► Packagist
        └── packages/logs ──────────► prefab-logs mirror ──────────► Packagist
```

This keeps Prefab a real monorepo for development while still allowing users to install only the module they need.

## Packages

- `tihloh/prefab-database` — optional default/named PDO connection management
- `tihloh/prefab-users` — user management and provider abstraction
- `tihloh/prefab-auth` — authentication and social sign-in building blocks
- `tihloh/prefab-permissions` — permission definitions, group inheritance and user overrides
- `tihloh/prefab-logs` — structured audit/activity logging with technical and human-friendly views

There is no required Core package. Install only the module or modules a project needs.

## Main design goal

Prefab is intended to stay quick and framework-independent while remaining framework-compatible.

Each module follows the same rules:

1. **Standalone first** — no other Prefab module is required.
2. **Automatic cooperation** — compatible modules publish/consume capabilities automatically.
3. **Explicit configuration wins** — a module-local option affects only that module.
4. **Three configuration levels** — direct module config, module-specific `PrefabConfig`, then common `PrefabConfig`.
5. **Transparent magic** — `explain()` and `PrefabRuntime::inspect()` show how automatic decisions were made.
6. **Conflict-aware discovery** — Prefab does not silently guess when equal-priority providers are ambiguous.
7. **No repeated feature-time discovery** — resources are resolved during module declaration/configuration and cached as direct references.
8. **Project data stays project-owned** — Prefab can map to existing project tables without claiming ownership.
9. **Prefab-owned storage is self-contained** — modules create only their own required tables.
10. **Framework compatibility through capabilities/adapters** — framework resources can satisfy Prefab capabilities without replacing the framework.

## Three configuration levels

Every configurable setting follows the same developer-facing hierarchy.

### Level 1 — direct module configuration

Highest priority and local to that module instance only:

```php
$logs = new LogManager([
    'database' => $specialLogDb,
]);
```

### Level 2 — module-specific PrefabConfig

Useful for central application configuration while customizing one module:

```php
PrefabConfig::set([
    'modules' => [
        'logs' => [
            'connection' => 'logs',
            'table' => 'activity_logs',
        ],
    ],
]);
```

### Level 3 — common PrefabConfig

Shared defaults that compatible modules may inherit:

```php
PrefabConfig::set([
    'database' => $mainPdo,
]);
```

These levels are resolved **per setting**, not per whole module. A module can therefore override its table while still inheriting the common database.

```text
Direct module setting
        ↓
PrefabConfig module setting
        ↓
PrefabConfig common setting
        ↓
Compatible Prefab capability
        ↓
Module internal default
        ↓
Clear error when a required resource is still unresolved
```

## Database Connection Manager

Prefab Database is optional. Other modules do not depend on it.

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

The Database module publishes capabilities such as:

```text
database
database_manager
database.connection.main
database.connection.logs
```

A module consumes them only when its three configuration levels did not already provide the required resource.

## Capability-based auto-integration

Prefab modules communicate through small runtime capabilities rather than hard dependencies.

Typical capabilities include:

```text
database
user_provider
actor_provider
permission_store
logger
```

For example:

```text
Prefab Database  -> provides database
Prefab Users     -> consumes database, provides user_provider
Prefab Auth      -> consumes user_provider, provides actor_provider
Prefab Logs      -> consumes database, provides logger
Permissions      -> consumes database
```

Adding a compatible module adds functionality; removing it does not make the remaining modules depend on it.

## Transparent diagnostics

Automatic integration should never become mysterious.

Each main manager exposes:

```php
$database->explain();
$users->explain();
$auth->explain();
$permissions->explain();
$logs->explain();
```

For a complete development-time view:

```php
use Tihloh\Prefab\PrefabRuntime;

$debug = PrefabRuntime::inspect();
```

The runtime report includes registered modules, published capabilities, provider priorities, metadata, and recorded resolution sources. Capability object values themselves are not included in the diagnostic output.

Applications that want an explicit end to startup may optionally call:

```php
PrefabRuntime::ready();
```

This is **not required** for normal Prefab use.

## Conflict handling

If only one compatible capability is available, Prefab uses it automatically.

If multiple providers exist with different priorities, the higher-priority provider wins. If multiple providers have the same highest priority, Prefab throws a clear ambiguity error instead of silently guessing.

The developer can then choose explicitly through direct/module/common configuration.

## Permission definitions and inheritance

Permission definitions may be provided as:

```text
inline PHP array
PHP template file returning an array
JSON template file
```

Effective permission resolution remains:

```text
User override
    ↓
Group permission
    ↓
Permission default
```

A user-level override always takes priority. Clearing the override restores inheritance from the user's groups or the permission default.

## Logging

Prefab Logs stores one structured technical/audit record and can present that record in a compact ordinary-user format.

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

A technical `permission.denied` record can therefore be presented as:

```text
Demo Admin denied View Documents for Test User.
```

without duplicating the stored log.

## Embedded interoperability bootstrap

Every standalone package contains `src/prefab.php`, so installing a single module never requires a separate Core package.

The repository keeps one canonical development source:

```text
tools/prefab-bootstrap.php
```

and provides:

```bash
php tools/sync-prefab-bootstrap.php
```

for synchronizing that bootstrap into all standalone packages before release. This is a repository-maintenance mechanism only; package users do not install or call it.

## Source-code documentation standard

Source documentation is part of the Prefab API:

- public classes describe responsibility and integration behavior with PHPDoc;
- public methods explain important parameters, return values and side effects;
- contracts document what implementers are expected to provide;
- non-obvious configuration/inheritance/discovery behavior has useful comments;
- examples use normal indentation and readable multi-line formatting;
- comments explain why behavior exists rather than merely restating PHP syntax;
- sensitive data such as passwords, hashes, tokens and secrets is excluded from human-friendly logs;
- package READMEs document standalone use, automatic integration, overrides and storage ownership.

## Examples

See `examples/database-integration-test` for a runnable project demonstrating:

- project-owned user and group tables;
- Prefab Users mapping to an existing table;
- automatic Auth integration;
- user creation and user-group membership;
- group permission inheritance and user overrides;
- PHP/JSON permission templates;
- separate Prefab Logs storage;
- technical and human-friendly logs;
- direct and centralized configuration patterns.

See also `docs/auto-integration.md`, `docs/packagist-release.md`, and each package README.
