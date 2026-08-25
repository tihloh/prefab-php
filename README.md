# Tihloh Prefab PHP

> **PHP building blocks. Your architecture.**

**Stop rebuilding the same PHP plumbing—or adopting an entire framework just to get a few conveniences.**

Prefab is a collection of standalone, framework-independent PHP building blocks for the parts applications repeatedly need: routing, input validation, authentication, permissions, files, logging, messaging, notifications, and more.

```bash
composer require tihloh/prefab-routes
composer require tihloh/prefab-input
```

Start with one block. Add another only when you need it. **Keep your application, database, directory structure, deployment model, and architectural decisions.**

```text
Plain PHP
   │
   ├── + Routes
   ├── + Input
   ├── + Auth
   ├── + Permissions
   ├── + Files
   └── + whatever the application actually needs
   ↓
Structured application — without a framework takeover
```

## Why Prefab?

A small PHP project often begins by rebuilding routing, validation, sessions, permissions, uploads and other infrastructure. A full framework solves those problems, but also brings its own application structure and conventions.

Prefab provides a third option:

- **Install only what you need.** Every package is independently useful.
- **Start simple and grow without rewriting.** Advanced capabilities are additive.
- **Modernize existing PHP incrementally.** Existing PDO connections, tables, sessions, pages and libraries remain usable.
- **Keep control of the architecture.** Prefab does not require base controllers, models, an ORM, a project skeleton or a prescribed folder structure.
- **Mix Prefab with other libraries.** Small contracts and adapters are preferred over ecosystem lock-in.
- **Get a consistent toolbox instead of unrelated utilities.** Prefab modules follow common design and error-handling conventions.

> **Start with one block. Add more when you need them. Never surrender your architecture.**

Prefab is deliberately **not another full-stack framework**. Your application remains the architecture; Prefab handles reusable plumbing.

## Documentation

| Module | Main responsibility | Documentation |
|---|---|---|
| **Database** | PDO connections, named connections and lightweight queries | [Database](packages/database/README.md) |
| **Users** | Mapping and managing project-owned users | [Users](packages/users/README.md) |
| **Auth** | Authentication and current-user/session concerns | [Auth](packages/auth/README.md) |
| **Permissions** | Authorization, groups, inheritance and overrides | [Permissions](packages/permissions/README.md) |
| **Logs** | Structured audit/activity logging | [Logs](packages/logs/README.md) |
| **Routes** | HTTP routing, middleware, groups and diagnostics | [Routes](packages/routes/README.md) |
| **Input** | Validation, normalization, casting, filtering and uploads | [Input](packages/input/README.md) |
| **Files** | File storage, retrieval, disks and safe filesystem operations | [Files](packages/files/README.md) |
| **Messaging** | External outbound communication such as email, SMTP, SMS/push adapters | [Messaging](packages/messaging/README.md) |
| **Notifications** | Internal system-to-user notices, unread state and notification inbox data | [Notifications](packages/notifications/README.md) |

Architecture/maintainer docs: [Automatic integration](docs/auto-integration.md) · [Packagist/release process](docs/packagist-release.md)

## Which package should I use?

```text
Database/storage of structured data?     → prefab-database
Project-owned users?                     → prefab-users
Login/current-user support?              → prefab-auth
Authorization/access control?            → prefab-permissions
Audit/activity history?                  → prefab-logs
Application routing?                     → prefab-routes
Clean/validated request data?            → prefab-input
File storage/organization?               → prefab-files
Email/SMS/external communication?        → prefab-messaging
Internal bell/inbox notifications?       → prefab-notifications
```

There is **no required Core package**.

## Start small

Each module is independently useful:

```php
$routes = new RouteManager();
$result = Input::from($_POST)->process(['name' => 'trim|required|string']);
$files = new FileManager(['root' => __DIR__ . '/storage']);
```

External email remains simple:

```php
$messaging->mail(
    'user@example.com',
    'Annual Report',
    'Your report is ready.',
);
```

Internal notices remain separate:

```php
$notifications->send(
    25,
    'Document Approved',
    'OBR-2026-001 has been approved.',
);
```

A single business event can intentionally use both:

```text
Document Approved
       │
       ├── Notifications → internal bell/inbox
       └── Messaging     → email/SMS/external push
```

Neither package requires the other.

## Grow without replacing the foundation

Prefab is intended to remain useful as the application grows:

```text
Day 1                    PHP + Routes
Day 10                   PHP + Routes + Input
Later                    + Auth + Permissions
Larger application       + Files + Logs + other needed blocks
```

Adding a package should add a capability—not force a migration to a new application architecture.

## Existing applications are first-class

Prefab should adapt to what already works. An existing application may keep its own PDO connection, user schema, session strategy, controllers, templates and third-party packages.

```text
Existing PHP application
        ↓ add one Prefab block
Existing PHP + improved capability
        ↓ add another when useful
Incrementally modernized application
```

Prefab packages should avoid unnecessary inheritance and global ownership so removing or replacing one block does not require rebuilding the entire application.

## Error handling

**Errors must be useful, predictable and catchable.** Prefab should not hide failures or turn ordinary runtime problems into mysterious behavior.

Use validation/result objects for expected user-data failures:

```php
$result = Input::from($_POST)->process([
    'email' => 'required|email',
]);

if ($result->fails()) {
    $errors = $result->errors();
}
```

Use delivery/result objects when failure is a normal operational outcome:

```php
$result = $messaging->mail(
    'user@example.com',
    'Report Ready',
    'Your report is ready.',
);

if ($result->failed()) {
    error_log($result->error ?? 'Message delivery failed.');
}
```

Use exceptions for invalid configuration, unsafe operations, unavailable required resources, programmer mistakes and failures where execution cannot safely continue:

```php
try {
    $files->put('../unsafe.txt', 'data');
} catch (Throwable $e) {
    // Log, report, convert to an HTTP response, or handle at the application boundary.
}
```

Prefab libraries should **not** unexpectedly terminate the process with `die()`/`exit()`, print errors directly, or expose secrets in exception messages. Applications decide how errors are logged, displayed or converted into HTTP/API responses.

The general convention is:

```text
Expected invalid input          → validation errors/result
Expected delivery outcome       → result object
Missing optional capability     → capability check / clear fallback
Invalid API/configuration       → exception
Unsafe/impossible operation     → exception
Unexpected infrastructure error → exception or documented failure result
```

Each module's documentation should describe its specific errors, failure results and exceptions with examples.

## Automatic cooperation

Compatible modules may cooperate through small contracts/capabilities rather than hard dependencies. Explicit application configuration always wins over automatic behavior.

Typical optional integrations:

```text
Users ─────────→ recipient/user resolution
Files ─────────→ Messaging attachments
Messaging ─────→ Logs delivery hooks
Notifications ─→ Logs notification activity
future Jobs ───→ asynchronous external delivery
```

## Input and Files

Uploads intentionally cross two modules:

```text
multipart/form-data
        ↓
Prefab Input
validate + normalize
        ↓
UploadedFile
        ↓
Prefab Files
store + retrieve
```

Input owns trust/validation. Files owns persistent file storage.

## Authentication and authorization

These remain separate:

```text
Auth
"Who is logged in?"
       ↓
Permissions
"What may this subject do?"
```

## Configuration philosophy

Modules participating in shared Prefab configuration use this precedence:

```text
1. Direct module configuration
2. Module-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal/default behavior where applicable
6. Clear error if a required resource remains unresolved
```

Explicit configuration wins.

## Monorepo model

`prefab-php` is the development source of truth:

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
│   ├── files/
│   ├── messaging/
│   └── notifications/
├── examples/
├── docs/
└── tools/
```

Distribution mirrors are created only when an individual module is ready for Packagist. Mirrors are publication targets; development remains in the monorepo.

## Prefab design rules

1. **Standalone first** — no unrelated module is required.
2. **Start simple** — advanced features are additive.
3. **One coherent responsibility per module** — combine closely related capabilities, not unrelated subsystems.
4. **Automatic cooperation where useful** — through small contracts/capabilities.
5. **Explicit configuration wins** — developer intent beats discovery.
6. **Transparent behavior** — automatic decisions should be diagnosable.
7. **Project data remains project-owned** — existing schemas can be mapped rather than replaced.
8. **Provider/framework neutral** — adapters integrate external implementations without defining the core API.
9. **No unnecessary package fragmentation** — email belongs to Messaging; validation belongs to Input.
10. **No framework creep** — a module should remain useful as a small Composer library.
11. **Internal and external communication stay distinct** — Notifications handles in-app notices; Messaging handles outbound delivery.
12. **Failures are explicit** — expected failures use inspectable results; exceptional failures use catchable exceptions; libraries do not unexpectedly terminate the application.
13. **Keep the exit door open** — avoid architecture lock-in and unnecessary inheritance.

## Documentation standard

Every public Prefab package should document, in roughly this order:

```text
1. What problem does this package solve?
2. Installation
3. 1-minute quick start
4. Common usage
5. Error handling
6. Advanced usage
7. Integration with other Prefab blocks
8. Extension/adapters
9. Diagnostics/troubleshooting
10. Responsibility boundary — what does NOT belong here?
```

Examples should show real failure handling where an operation can reasonably fail. Public methods should have predictable behavior, and advanced features should not make the simple path harder to understand.

## Documentation map

**[Database](packages/database/README.md) · [Users](packages/users/README.md) · [Auth](packages/auth/README.md) · [Permissions](packages/permissions/README.md) · [Logs](packages/logs/README.md) · [Routes](packages/routes/README.md) · [Input](packages/input/README.md) · [Files](packages/files/README.md) · [Messaging](packages/messaging/README.md) · [Notifications](packages/notifications/README.md)**

> **Start with one block. Add more when you need them. Keep your architecture.**
