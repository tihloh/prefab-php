# Tihloh Prefab PHP

**Reusable, modular PHP building blocks for rapid Lego-style application development.**

Prefab is built around a simple rule:

> Install only what you need. Start simple. Add capabilities without replacing the architecture you already started with.

Every module works standalone. Compatible modules can cooperate through small contracts and runtime capabilities without becoming hard dependencies of one another.

## Documentation

Start here, then open the module that matches the problem you are solving.

| Module | Main responsibility | Documentation |
|---|---|---|
| **Database** | PDO connections, named connections and lightweight queries | [Database documentation](packages/database/README.md) |
| **Users** | Mapping and managing project-owned users | [Users documentation](packages/users/README.md) |
| **Auth** | Authentication, sessions and optional social sign-in | [Auth documentation](packages/auth/README.md) |
| **Permissions** | Authorization, groups, inheritance and user overrides | [Permissions documentation](packages/permissions/README.md) |
| **Logs** | Structured audit/activity logging and human-friendly activity | [Logs documentation](packages/logs/README.md) |
| **Routes** | HTTP routing, handlers, middleware, groups and diagnostics | [Routes documentation](packages/routes/README.md) |
| **Input** | Validation, normalization, casting, filtering and safe input extraction | [Input documentation](packages/input/README.md) |

Architecture and maintainer documentation:

- [Automatic integration](docs/auto-integration.md)
- [Packagist and release process](docs/packagist-release.md)

## Which package should I use?

```text
Need database infrastructure?           → prefab-database
Need reusable project users?            → prefab-users
Need login/current-user support?         → prefab-auth
Need authorization/access control?       → prefab-permissions
Need audit/activity history?             → prefab-logs
Need application routing?                → prefab-routes
Need clean/validated request data?       → prefab-input
```

There is **no required Core package**.

---

# 1. Prefab at a glance

Each module has one primary responsibility:

```text
Raw external data
      ↓
Prefab Input
      ↓
HTTP request
      ↓
Prefab Routes
      ↓
Prefab Auth
      ↓
Prefab Users
      ↓
Prefab Permissions
      ↓
Application / business logic

Prefab Database supplies persistence where needed.
Prefab Logs records structured activity where configured.
```

Another way to think about it:

| Module | Main question |
|---|---|
| Database | Where/how do I access data? |
| Users | Who are the application's users? |
| Auth | Who is currently authenticated? |
| Permissions | What may this subject do? |
| Logs | What happened, who did it and what changed? |
| Routes | Which application code handles this HTTP request? |
| Input | Which incoming fields are valid, normalized and safe to use? |

These modules can cooperate, but the responsibility boundaries remain intentional.

---

# 2. Small applications stay small

Prefab does not require framework-sized setup.

A tiny router:

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

A tiny input processor:

```php
use Tihloh\Prefab\Input\Input;

$result = Input::from($_POST)->process([
    'name' => 'trim|required|string|max:100',
    'email' => 'trim|lowercase|required|email',
]);

$data = $result->validated();
```

A tiny database setup:

```php
$database = new DatabaseManager([
    'connections' => [
        'main' => $pdo,
    ],
]);
```

Using one module never requires installing the rest.

---

# 3. Applications can grow without changing architecture

A project may begin with:

```text
Routes
```

then add:

```text
Routes + Input
```

then:

```text
Routes + Input + Database + Users + Auth
```

and later:

```text
Input
  ↓
Routes
  ↓
Auth
  ↓
Users
  ↓
Permissions

Database supplies persistence.
Logs records activity.
```

The application adds Lego blocks rather than replacing its original foundation.

---

# 4. Standalone first

Every Prefab package must remain genuinely useful by itself.

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
$auth = new AuthManager($userProvider, $sessionStore);
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

```php
$input = new Input($_POST);
```

No unrelated Prefab package is required merely because one module is installed.

---

# 5. Automatic cooperation

Compatible modules may communicate through small runtime capabilities rather than hard package dependencies.

Typical capabilities include:

```text
database
user_provider
actor_provider
permission_store
logger
```

Conceptually:

```text
Prefab Database
      ↓ provides database capability
Prefab Users
      ↓ provides user provider
Prefab Auth
      ↓ provides current actor/user
Prefab Permissions

Prefab Logs ← compatible modules may publish structured activity
```

Input and Routes remain useful independently and can integrate with these capabilities through middleware/adapters when appropriate.

---

# 6. Predictable configuration hierarchy

Prefab modules use a consistent precedence model:

```text
1. Direct module configuration
2. Module-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal/default behavior where applicable
6. Clear error if a required resource remains unresolved
```

Direct configuration:

```php
$logs = new LogManager([
    'database' => $specialLogDb,
]);
```

Module-specific configuration:

```php
PrefabConfig::set([
    'modules' => [
        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);
```

Common configuration:

```php
PrefabConfig::set([
    'database' => $mainPdo,
]);
```

Resolution happens per setting, so a module can override one option while inheriting another.

---

# 7. Database architecture

Prefab Database is optional. Database-consuming modules use a small shared contract rather than requiring `DatabaseManager` directly.

```text
Plain PDO
    ↓
PdoDatabaseAdapter
    ↓
DatabaseInterface
    ↓
Prefab module
```

or:

```text
Prefab Database
      ↓
DatabaseInterface
      ↓
Prefab module
```

This preserves both standalone PDO usage and automatic named-connection integration.

See [Prefab Database](packages/database/README.md).

---

# 8. User ownership

Prefab does not require projects to replace their user tables.

```text
Existing users / employees / accounts table
                  ↓
                UserMap
                  ↓
             Prefab Users
                  ↓
        Auth / Permissions / Logs
```

The project remains the owner of its schema and domain-specific fields.

See [Prefab Users](packages/users/README.md).

---

# 9. Authentication and authorization stay separate

```text
Auth
"Who is logged in?"
       ↓
Permissions
"What may this user do?"
```

Successful authentication does not automatically grant authorization.

See [Prefab Auth](packages/auth/README.md) and [Prefab Permissions](packages/permissions/README.md).

---

# 10. Permission inheritance

The default authorization hierarchy is:

```text
User override
    ↓
Group permission
    ↓
Permission definition default
```

This supports explicit allow, explicit deny and inheritance.

See [Prefab Permissions](packages/permissions/README.md).

---

# 11. Structured logging

Prefab Logs stores structured facts once and can present the same event as either technical audit data or human-friendly activity.

```text
Application event
       ↓
structured log
       ↓
stored once
   ┌───┴────┐
   ↓        ↓
technical  human-friendly
view       view
```

See [Prefab Logs](packages/logs/README.md).

---

# 12. Routing

Prefab Routes supports both compact and native PHP handlers:

```php
$routes->get('/users/{id}', 'UserController@show');
```

```php
$routes->get('/users/{id}', [UserController::class, 'show']);
```

It can grow from simple `get()`/`dispatch()` usage into named routes, constraints, groups, middleware, resource routes, route files and diagnostics.

Static files such as CSS, JavaScript, fonts and images remain the web server's responsibility.

See [Prefab Routes](packages/routes/README.md).

---

# 13. Input processing

Prefab Input handles the trust boundary between raw request/form/API data and business logic.

```php
$result = Input::from([
    'email' => '  ADMIN@EXAMPLE.COM ',
    'age' => '35',
    'admin' => true,
])->process([
    'email' => 'trim|lowercase|required|email',
    'age' => 'integer|min:18',
]);
```

Validated output:

```php
[
    'email' => 'admin@example.com',
    'age' => 35,
]
```

The undeclared `admin` field is not included. Validation, normalization, casting and whitelisting happen in one schema-driven pipeline.

See [Prefab Input](packages/input/README.md).

---

# 14. Transparent diagnostics

Automatic integration should never become mysterious.

Managers participating in Prefab interoperability expose diagnostic APIs such as:

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

Applications may optionally mark startup complete:

```php
PrefabRuntime::ready();
```

Normal usage does not require that call.

---

# 15. Conflict-aware discovery

If one compatible capability exists, Prefab may use it automatically.

If multiple providers exist with different priorities, the higher-priority provider wins.

If equally preferred providers are ambiguous, Prefab should fail clearly rather than silently guessing. The developer can then resolve the ambiguity explicitly.

---

# 16. Monorepo model

`prefab-php` is the **single development repository and source of truth**.

```text
prefab-php/
├── packages/
│   ├── database/
│   ├── users/
│   ├── auth/
│   ├── permissions/
│   ├── logs/
│   ├── routes/
│   └── input/
├── examples/
├── docs/
└── tools/
```

Development, issues, CI, examples, interoperability changes and releases originate here.

Packagist distribution uses generated mirrors because each independently installable Composer package needs its own repository-root `composer.json`.

```text
prefab-php (source of truth)
        │
        ├── packages/database ──────► prefab-database ──────► Packagist
        ├── packages/users ─────────► prefab-users ─────────► Packagist
        ├── packages/auth ──────────► prefab-auth ──────────► Packagist
        ├── packages/permissions ───► prefab-permissions ───► Packagist
        ├── packages/logs ──────────► prefab-logs ──────────► Packagist
        ├── packages/routes ────────► prefab-routes ────────► Packagist
        └── packages/input ─────────► prefab-input ─────────► Packagist
```

A mirror is created/published only after that module is ready for distribution. Distribution mirrors are not development sources and should not be edited manually.

---

# 17. Embedded interoperability bootstrap

Standalone modules that participate in Prefab runtime interoperability use an embedded bootstrap rather than requiring a separate Core package.

The monorepo maintains the canonical bootstrap in:

```text
tools/prefab-bootstrap.php
```

and synchronizes package copies with:

```bash
php tools/sync-prefab-bootstrap.php
```

This is a maintainer/release mechanism, not something ordinary package users need to run.

---

# 18. Documentation map

User documentation:

- [Database](packages/database/README.md)
- [Users](packages/users/README.md)
- [Auth](packages/auth/README.md)
- [Permissions](packages/permissions/README.md)
- [Logs](packages/logs/README.md)
- [Routes](packages/routes/README.md)
- [Input](packages/input/README.md)

Maintainer/architecture documentation:

- [Automatic integration](docs/auto-integration.md)
- [Packagist/release process](docs/packagist-release.md)

---

# 19. Source documentation standard

Documentation is part of Prefab's API quality standard:

- public classes explain responsibility and integration behavior;
- public methods document non-obvious parameters, return values and side effects;
- contracts explain what implementers must provide;
- examples use readable PHP formatting and indentation;
- comments explain why behavior exists, not merely what PHP syntax does;
- diagnostics explain automatic decisions;
- package READMEs document standalone use, advanced use and integration boundaries;
- sensitive secrets are not intentionally exposed through human-friendly diagnostics/logs.

---

# 20. Prefab design rules

Every module follows these principles:

1. **Standalone first** — no unrelated Prefab module is required.
2. **Start simple** — advanced features are additive rather than mandatory.
3. **Automatic cooperation where useful** — compatible modules may publish/consume capabilities.
4. **Explicit configuration wins** — automation does not override developer intent.
5. **Predictable configuration hierarchy** — direct, module, common, capability, then default/error.
6. **Transparent automation** — important automatic decisions are diagnosable.
7. **Conflict-aware discovery** — Prefab does not silently guess between equally preferred providers.
8. **Project data remains project-owned** — existing schemas can be mapped rather than replaced.
9. **Prefab-owned storage stays isolated** — modules create only storage they actually own.
10. **Framework compatibility through contracts/adapters** — Prefab should cooperate with frameworks, not demand replacement.
11. **No repeated runtime discovery when avoidable** — resources are resolved during setup and reused.
12. **Clear module boundaries** — database, users, auth, authorization, logs, routing and input processing remain separate concerns.

---

# Where to go next

Choose the smallest module that solves the problem in front of you:

**[Database](packages/database/README.md) · [Users](packages/users/README.md) · [Auth](packages/auth/README.md) · [Permissions](packages/permissions/README.md) · [Logs](packages/logs/README.md) · [Routes](packages/routes/README.md) · [Input](packages/input/README.md)**

You can add the other modules later without abandoning the ones you already use.
