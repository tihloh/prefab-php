# Tihloh Prefab PHP

**Reusable, modular PHP building blocks for rapid Lego-style application development.**

Prefab is built around a simple rule:

> Install only what you need. Start simple. Add capabilities without replacing the architecture you already started with.

Every module works standalone. Compatible modules can cooperate through small contracts and runtime capabilities without becoming hard dependencies of one another.

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

## Prefab at a glance

```text
External input/uploads
        ↓
      Input
        ↓
      Routes
        ↓
       Auth
        ↓
      Users
        ↓
   Permissions
        ↓
Application/business logic

Database       → structured persistence
Files          → file persistence
Logs           → audit/activity
Messaging      → external outbound communication
Notifications  → internal system-to-user notices
```

The modules answer different questions:

| Module | Main question |
|---|---|
| Database | Where/how do I access structured data? |
| Users | Who are the application's users? |
| Auth | Who is currently authenticated? |
| Permissions | What may this subject do? |
| Logs | What happened, who did it and what changed? |
| Routes | Which code handles this HTTP request? |
| Input | Which incoming data/files are safe and usable? |
| Files | Where/how should files be stored and retrieved? |
| Messaging | How do I deliver a message outside the application? |
| Notifications | What internal notice should this application user see? |

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

Internal notices are separate:

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

## Documentation map

**[Database](packages/database/README.md) · [Users](packages/users/README.md) · [Auth](packages/auth/README.md) · [Permissions](packages/permissions/README.md) · [Logs](packages/logs/README.md) · [Routes](packages/routes/README.md) · [Input](packages/input/README.md) · [Files](packages/files/README.md) · [Messaging](packages/messaging/README.md) · [Notifications](packages/notifications/README.md)**

Choose the smallest module that solves the problem in front of you. Add another block only when the application actually needs it.
