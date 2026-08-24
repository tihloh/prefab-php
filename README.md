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
| **Input** | Validation, normalization, casting, filtering, uploads and safe input extraction | [Input documentation](packages/input/README.md) |
| **Files** | File storage, retrieval, named disks, metadata and safe filesystem operations | [Files documentation](packages/files/README.md) |

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
Need file storage/organization?          → prefab-files
```

There is **no required Core package**.

---

# 1. Prefab at a glance

Each module has one primary responsibility:

```text
Raw external data / uploads
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
Prefab Files stores/retrieves application files where needed.
Prefab Logs records structured activity where configured.
```

Another way to think about it:

| Module | Main question |
|---|---|
| Database | Where/how do I access structured data? |
| Users | Who are the application's users? |
| Auth | Who is currently authenticated? |
| Permissions | What may this subject do? |
| Logs | What happened, who did it and what changed? |
| Routes | Which application code handles this HTTP request? |
| Input | Which incoming fields/files are valid, normalized and safe to use? |
| Files | Where/how should application files be stored and retrieved? |

These modules can cooperate, but the responsibility boundaries remain intentional.

---

# 2. Small applications stay small

Prefab does not require framework-sized setup.

A tiny router:

```php
use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();
$routes->get('/', fn () => 'Hello');
$result = $routes->dispatch();
```

A tiny input processor:

```php
use Tihloh\Prefab\Input\Input;

$result = Input::from($_POST)->process([
    'name' => 'trim|required|string|max:100',
]);
```

A tiny file store:

```php
use Tihloh\Prefab\Files\FileManager;

$files = new FileManager([
    'root' => __DIR__ . '/storage',
]);

$files->put('notes/hello.txt', 'Hello Prefab');
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
Routes + Input + Files
```

then:

```text
Routes + Input + Files + Database + Users + Auth
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

Database supplies structured persistence.
Files supplies file persistence.
Logs records activity.
```

The application adds Lego blocks rather than replacing its original foundation.

---

# 4. Standalone first

Every Prefab package must remain genuinely useful by itself.

```php
$database = new DatabaseManager([...]);
$users = new UserManager([...]);
$auth = new AuthManager($userProvider, $sessionStore);
$permissions = new PermissionManager(definitions: $definitions, store: $permissionStore);
$logs = new LogManager([...]);
$routes = new RouteManager();
$input = new Input($_POST);
$files = new FileManager(['root' => __DIR__ . '/storage']);
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

Input, Routes and Files remain useful independently and can integrate with neighboring modules through small contracts or capability-style object APIs rather than becoming mandatory dependencies.

---

# 6. Predictable configuration hierarchy

Prefab modules that participate in shared configuration use a consistent precedence model:

```text
1. Direct module configuration
2. Module-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal/default behavior where applicable
6. Clear error if a required resource remains unresolved
```

Explicit configuration always has priority over automatic discovery.

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

See [Prefab Auth](packages/auth/README.md) and [Prefab Permissions](packages/permissions/README.md).

---

# 10. Structured logging

Prefab Logs stores structured facts once and can present the same event as technical audit data or human-friendly activity.

See [Prefab Logs](packages/logs/README.md).

---

# 11. Routing

Prefab Routes can start with simple `get()`/`dispatch()` usage and grow into named routes, constraints, groups, middleware, resource routes, route files and diagnostics.

```php
$routes->get('/users/{id}', 'UserController@show');
$routes->get('/users/{id}', [UserController::class, 'show']);
```

See [Prefab Routes](packages/routes/README.md).

---

# 12. Input processing

Prefab Input handles the trust boundary between raw form/API/upload data and business logic.

```php
$result = Input::fromRequest()->process([
    'email' => 'trim|lowercase|required|email',
    'age' => 'integer|min:18',
    'document' => 'nullable|file|mimes:pdf|max_size:20mb',
]);
```

It supports wildcard arrays, nested input, normalized `$_FILES`, multipart forms and safe schema-whitelisted output.

See [Prefab Input](packages/input/README.md).

---

# 13. File storage

Prefab Files takes validated files or application-generated content and stores them through named storage disks.

```php
$files = new FileManager([
    'default' => 'private',
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => __DIR__ . '/storage/private',
        ],
        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/public/uploads',
            'url' => '/uploads',
        ],
    ],
]);
```

Upload flow:

```text
multipart/form-data
        ↓
Prefab Input
validate + normalize
        ↓
UploadedFile
        ↓
Prefab Files
store + retrieve + organize
```

See [Prefab Files](packages/files/README.md).

---

# 14. Transparent diagnostics

Automatic integration should never become mysterious. Managers participating in Prefab interoperability expose diagnostic APIs such as `explain()`, and `PrefabRuntime::inspect()` provides a runtime-wide view where applicable.

---

# 15. Conflict-aware discovery

If one compatible capability exists, Prefab may use it automatically. If equally preferred providers are ambiguous, Prefab should fail clearly rather than silently guessing.

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
│   ├── input/
│   └── files/
├── examples/
├── docs/
└── tools/
```

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
        ├── packages/input ─────────► prefab-input ─────────► Packagist
        └── packages/files ─────────► prefab-files ─────────► Packagist
```

A mirror is created/published only after that module is ready for distribution.

---

# 17. Embedded interoperability bootstrap

Standalone modules that participate in Prefab runtime interoperability use an embedded bootstrap rather than requiring a separate Core package.

The monorepo maintains the canonical bootstrap in `tools/prefab-bootstrap.php` and synchronizes package copies with `php tools/sync-prefab-bootstrap.php` where applicable.

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
- [Files](packages/files/README.md)

Maintainer/architecture documentation:

- [Automatic integration](docs/auto-integration.md)
- [Packagist/release process](docs/packagist-release.md)

---

# 19. Source documentation standard

Documentation is part of Prefab's API quality standard. Public classes and contracts explain responsibilities, examples remain readable, automatic decisions are diagnosable, package boundaries are explicit, and secrets are not intentionally exposed through diagnostics or human-friendly logs.

---

# 20. Prefab design rules

Every module follows these principles:

1. **Standalone first** — no unrelated Prefab module is required.
2. **Start simple** — advanced features are additive rather than mandatory.
3. **Automatic cooperation where useful** — compatible modules may publish/consume capabilities.
4. **Explicit configuration wins** — automation does not override developer intent.
5. **Predictable configuration hierarchy** — direct, module, common, capability, then default/error where applicable.
6. **Transparent automation** — important automatic decisions are diagnosable.
7. **Conflict-aware discovery** — Prefab does not silently guess between equally preferred providers.
8. **Project data remains project-owned** — existing schemas can be mapped rather than replaced.
9. **Prefab-owned storage stays isolated** — modules create only storage they actually own.
10. **Framework compatibility through contracts/adapters** — Prefab should cooperate with frameworks, not demand replacement.
11. **No repeated runtime discovery when avoidable** — resources are resolved during setup and reused.
12. **Clear module boundaries** — database, users, auth, authorization, logs, routing, input processing and file storage remain separate concerns.

---

# Where to go next

Choose the smallest module that solves the problem in front of you:

**[Database](packages/database/README.md) · [Users](packages/users/README.md) · [Auth](packages/auth/README.md) · [Permissions](packages/permissions/README.md) · [Logs](packages/logs/README.md) · [Routes](packages/routes/README.md) · [Input](packages/input/README.md) · [Files](packages/files/README.md)**

You can add the other modules later without abandoning the ones you already use.
