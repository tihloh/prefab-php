# Tihloh Prefab PHP

**Reusable, modular PHP building blocks for rapid Lego-style application development.**

Prefab is designed around a simple idea:

> Install only what you need. Start simple. Let the same modules grow with the application.

Every module works standalone. When compatible Prefab modules are used together, they can cooperate through shared capabilities without becoming hard dependencies of one another.

## Documentation

Start here, then open the module that matches the problem you are solving.

| Module | What it does | Documentation |
|---|---|---|
| **Database** | PDO connection management, named connections and lightweight queries | [Database documentation](packages/database/README.md) |
| **Users** | Maps existing project users and provides reusable user management | [Users documentation](packages/users/README.md) |
| **Auth** | Password authentication, sessions and optional social sign-in | [Auth documentation](packages/auth/README.md) |
| **Permissions** | Authorization, groups, inheritance and user overrides | [Permissions documentation](packages/permissions/README.md) |
| **Logs** | Structured audit/activity logging and human-friendly activity | [Logs documentation](packages/logs/README.md) |
| **Routes** | HTTP routing, controllers, middleware, groups and route inspection | [Routes documentation](packages/routes/README.md) |

Architecture and maintainer documentation:

- [Automatic integration](docs/auto-integration.md)
- [Packagist and release process](docs/packagist-release.md)

## Which package should I install?

Choose by responsibility:

```text
Need database connections/query helpers?  → prefab-database
Need reusable project users?              → prefab-users
Need login/current-user support?          → prefab-auth
Need authorization/access control?        → prefab-permissions
Need audit/activity history?              → prefab-logs
Need application routing?                 → prefab-routes
```

Install one package:

```bash
composer require tihloh/prefab-routes
```

Or combine only the capabilities an application needs:

```bash
composer require tihloh/prefab-database tihloh/prefab-users tihloh/prefab-auth tihloh/prefab-permissions tihloh/prefab-logs tihloh/prefab-routes
```

There is **no required Core package**.

---

# 1. Prefab at a glance

The six modules have deliberately separate responsibilities:

```text
HTTP request
     ↓
Routes ───────────────────────────────┐
     ↓                               │
Auth                                 │
     ↓                               │
Users                                │
     ↓                               │
Permissions                          │
     ↓                               │
Application / business logic         │
                                     │
Database ← shared persistence ───────┤
Logs     ← structured activity ──────┘
```

Another way to think about them:

| Module | Main question |
|---|---|
| Database | Where/how do I access data? |
| Users | Who are the application's users? |
| Auth | Who is currently authenticated? |
| Permissions | What is this subject allowed to do? |
| Logs | What happened, who did it and what changed? |
| Routes | Which application code handles this HTTP request? |

The modules can cooperate, but those boundaries remain intentional.

---

# 2. Small applications stay small

Prefab does not require a full framework-style application bootstrap.

For routing alone:

```php
use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();

$routes->get('/', fn () => 'Hello');
$routes->get('/about', fn () => 'About');

$result = $routes->dispatch();

if (is_string($result)) {
    echo $result;
}
```

For database access alone:

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

A project does not need to install Users, Auth, Permissions or Logs merely because it uses Database or Routes.

---

# 3. Applications can grow without changing architecture

A project may begin with:

```text
Routes
```

then later become:

```text
Routes + Database
```

then:

```text
Routes + Database + Users + Auth
```

and eventually:

```text
Routes
  ↓
Auth
  ↓
Users
  ↓
Permissions

Database supplies persistence
Logs records activity
```

The application adds capabilities instead of replacing its original modules.

---

# 4. Standalone first

Every Prefab package must remain useful by itself.

Examples:

```php
$database = new DatabaseManager([...]);
```

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $userMap,
]);
```

```php
$auth = new AuthManager(
    $userProvider,
    $sessionStore,
);
```

```php
$permissions = new PermissionManager(
    definitions: $definitions,
    store: $permissionStore,
);
```

```php
$logs = new LogManager([
    'database' => $pdo,
]);
```

```php
$routes = new RouteManager();
```

Using Prefab never means an application must install the entire ecosystem.

---

# 5. Automatic cooperation

When several compatible modules exist, Prefab can connect them through small runtime capabilities.

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
Prefab Database
      ↓ provides database
Prefab Users
      ↓ provides user_provider
Prefab Auth
      ↓ provides actor/current-user capability
Prefab Permissions

Prefab Logs ← compatible modules may publish activity
```

Modules communicate through contracts/capabilities rather than directly requiring every neighboring package.

Removing one optional module therefore does not turn the others into broken hard dependencies.

---

# 6. Configuration hierarchy

Prefab follows one predictable configuration model.

```text
1. Direct module configuration
2. Module-specific PrefabConfig
3. Common PrefabConfig
4. Compatible Prefab capability
5. Module internal default where applicable
6. Clear error if a required resource remains unresolved
```

## Direct module configuration

Highest priority and local to one instance:

```php
$logs = new LogManager([
    'database' => $specialLogDb,
]);
```

## Module-specific PrefabConfig

Central configuration for one module:

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

## Common PrefabConfig

Shared defaults:

```php
PrefabConfig::set([
    'database' => $mainPdo,
]);
```

Resolution happens **per setting**, not per entire module. A module can override its table while still inheriting the common database.

---

# 7. Database architecture

Prefab Database is optional. Database-consuming modules use a small common database contract rather than requiring `DatabaseManager` itself.

```text
Plain PDO
    ↓
PdoDatabaseAdapter
    ↓
DatabaseInterface
    ↓
Prefab module
```

Or:

```text
Prefab Database
      ↓
DatabaseInterface
      ↓
Prefab module
```

This is why a module can accept plain PDO when used standalone and still inherit a named Prefab Database connection in a larger application.

See the [Database documentation](packages/database/README.md) for connection management, query building and database interoperability.

---

# 8. User ownership

Prefab does not require projects to replace existing user tables.

An application may already have:

```text
employees
accounts
members
users
```

Prefab Users maps that project-owned schema into a common user abstraction.

```text
Existing project table
        ↓
      UserMap
        ↓
   Prefab Users
        ↓
Auth / Permissions / Logs
```

See the [Users documentation](packages/users/README.md) for mapping, CRUD, custom user classes and provider integration.

---

# 9. Authentication vs authorization

Prefab deliberately separates these responsibilities.

```text
Auth
"Who is logged in?"
       ↓
Permissions
"What may this user do?"
```

Authentication success does not automatically mean the user is authorized to perform every action.

See the [Auth documentation](packages/auth/README.md) and [Permissions documentation](packages/permissions/README.md).

---

# 10. Permission inheritance

The default permission model is:

```text
User override
    ↓
Group permission
    ↓
Permission default
```

This supports explicit allow, explicit deny and inheritance. Clearing a user override restores group/default resolution rather than meaning the same thing as deny.

See the [Permissions documentation](packages/permissions/README.md) for definitions, storage, subjects and integration.

---

# 11. Structured logging

Prefab Logs stores structured technical/audit information and can render the same record as human-friendly activity.

```text
Application action
       ↓
structured log
       ↓
stored once
   ┌───┴────┐
   ↓        ↓
technical  human-friendly
view       view
```

For example, a technical permission record can later be displayed as:

```text
Demo Admin denied View Documents for Test User.
```

without storing a second duplicate log.

See the [Logs documentation](packages/logs/README.md).

---

# 12. Routing

Prefab Routes controls application HTTP routing without taking ownership of static assets or forcing a framework architecture.

```php
$routes->get('/users/{id}', 'UserController@show')
    ->name('users.show')
    ->where('id', '\d+');
```

Native PHP callable syntax is also supported:

```php
$routes->get('/users/{id}', [UserController::class, 'show']);
```

As the application grows, routing can add groups, middleware, resource routes, metadata, route files and diagnostics without changing the basic API.

See the [Routes documentation](packages/routes/README.md).

---

# 13. Diagnostics and transparent automation

Automatic integration should not become invisible magic.

Managers that participate in Prefab interoperability expose diagnostics such as:

```php
$database->explain();
$users->explain();
$auth->explain();
$permissions->explain();
$logs->explain();
```

For the runtime-wide view:

```php
use Tihloh\Prefab\PrefabRuntime;

$debug = PrefabRuntime::inspect();
```

The report can describe registered modules, capabilities, priorities and resolution sources without intentionally exposing actual database credentials or capability objects.

Applications may optionally mark the end of startup:

```php
PrefabRuntime::ready();
```

This is not required for normal Prefab usage.

---

# 14. Conflict-aware discovery

If one compatible capability exists, Prefab can use it automatically.

If several providers exist with different priorities, the higher-priority provider wins.

If equally preferred providers are ambiguous, Prefab should fail clearly rather than silently guessing.

The developer can then resolve the ambiguity through explicit direct/module/common configuration.

---

# 15. Monorepo model

`prefab-php` is the **single development repository and source of truth**.

```text
prefab-php/
├── packages/
│   ├── database/
│   ├── users/
│   ├── auth/
│   ├── permissions/
│   ├── logs/
│   └── routes/
├── examples/
├── docs/
└── tools/
```

Development, issues, pull requests, CI, examples, interoperability changes and releases originate here.

Packagist packages need their own repository roots. Prefab therefore generates distribution mirrors from the monorepo packages. Those mirrors are publication targets and should not be edited directly.

```text
prefab-php (source of truth)
        │
        ├── packages/database ──────► prefab-database ──────► Packagist
        ├── packages/users ─────────► prefab-users ─────────► Packagist
        ├── packages/auth ──────────► prefab-auth ──────────► Packagist
        ├── packages/permissions ───► prefab-permissions ───► Packagist
        ├── packages/logs ──────────► prefab-logs ──────────► Packagist
        └── packages/routes ────────► prefab-routes ────────► Packagist
```

The mirror for a module is created/published only when that package is ready for distribution.

---

# 16. Embedded interoperability bootstrap

Every standalone package contains `src/prefab.php`, so installing a single module does not require a separate Core package.

The monorepo keeps one canonical maintenance source:

```text
tools/prefab-bootstrap.php
```

and synchronizes it into the packages with:

```bash
php tools/sync-prefab-bootstrap.php
```

This is a repository-maintenance/release mechanism. Package users do not need to run it.

---

# 17. Repository documentation map

For users:

```text
README.md
   ↓
choose a module
   ↓
packages/<module>/README.md
```

For contributors/maintainers:

```text
README.md
├── docs/auto-integration.md
├── docs/packagist-release.md
├── tools/prefab-bootstrap.php
└── tools/sync-prefab-bootstrap.php
```

Direct links:

- [Database](packages/database/README.md)
- [Users](packages/users/README.md)
- [Auth](packages/auth/README.md)
- [Permissions](packages/permissions/README.md)
- [Logs](packages/logs/README.md)
- [Routes](packages/routes/README.md)
- [Automatic integration](docs/auto-integration.md)
- [Release process](docs/packagist-release.md)

---

# 18. Source-code documentation standard

Documentation is part of the Prefab API quality standard:

- public classes describe their responsibility and integration behavior with PHPDoc;
- public methods explain important parameters, return values and side effects;
- contracts document what implementers are expected to provide;
- non-obvious configuration, inheritance and discovery behavior is explained;
- examples use readable formatting;
- comments explain why behavior exists rather than merely restating PHP syntax;
- sensitive data such as passwords, hashes, tokens and secrets is excluded from human-friendly logs;
- package READMEs document standalone use, automatic integration, overrides and storage ownership.

---

# 19. Examples

`examples/database-integration-test` demonstrates a larger integration scenario including project-owned user/group tables, Prefab Users mapping, Auth integration, group permission inheritance, user overrides, permission templates, separate Logs storage, technical/human-friendly logs and centralized/direct configuration patterns.

The package READMEs contain smaller examples that are usually the best starting point when learning one module.

---

# 20. Prefab design rules

Every module follows these rules:

1. **Standalone first** — no unrelated Prefab module is required.
2. **Start simple** — advanced features are additive rather than mandatory.
3. **Automatic cooperation** — compatible modules can publish/consume capabilities.
4. **Explicit configuration wins** — local intent is never silently overridden by automation.
5. **Predictable configuration** — direct, module-specific, common, capability, then default/error.
6. **Transparent automation** — diagnostics explain important automatic decisions.
7. **Conflict-aware discovery** — Prefab does not silently guess between equally preferred providers.
8. **Project data stays project-owned** — existing application schemas can be mapped rather than replaced.
9. **Prefab-owned storage stays isolated** — a module creates only storage it actually owns.
10. **Framework compatibility through contracts/adapters** — Prefab consumes capabilities rather than demanding framework replacement.
11. **No unnecessary runtime discovery** — resources are resolved during setup and retained as direct references where appropriate.
12. **Modules keep clear responsibilities** — routing, authentication, authorization, users, logging and database infrastructure remain separate concerns.

---

# Where to go next

If you are new to Prefab, choose the smallest module that solves your immediate problem and open its documentation:

**[Database](packages/database/README.md) · [Users](packages/users/README.md) · [Auth](packages/auth/README.md) · [Permissions](packages/permissions/README.md) · [Logs](packages/logs/README.md) · [Routes](packages/routes/README.md)**

You can add the other modules later without abandoning the one you started with.
