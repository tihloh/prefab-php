# Tihloh Prefab PHP

> ## **PHP building blocks. Your architecture.**

### **Modular** · **Reusable** · *Independent* · **Adaptive** · ***Better Together***

Start with one block. Add another. **Gain capabilities.**  
Improve the blocks you already have.

**Stop rebuilding the same PHP plumbing—or adopting an entire framework just to get a few conveniences.**

Prefab is a collection of standalone, framework-independent PHP building blocks for routing, input, authentication, permissions, files, logging, messaging, notifications and other common application infrastructure.

```bash
composer require tihloh/prefab-routes
composer require tihloh/prefab-input
```

Start with one block. Add another only when you need it. **Keep your application, database, directory structure, deployment model and architectural decisions.**

## The Prefab difference

Prefab blocks are useful alone, but **adding a compatible block can improve the blocks you already have**.

```text
Install Users
    ↓
Users works

+ Auth
    ↓
Auth can automatically use Users

+ Logs
    ↓
Authentication/user activity can be audited

+ Notifications
    ↓
Compatible operations can gain ->notify()

+ Messaging
    ↓
Compatible operations can gain ->email()
```

This is built around three integration mechanisms:

### Auto-Wiring

> **Add a block. Prefab connects what makes sense.**

Infrastructure relationships can be discovered automatically without forcing package dependencies. For example, Auth can discover Users, Permissions can discover the authenticated actor, and Logs can receive standard infrastructure events.

### Fluent Extensions

> **Add a block. Gain a capability. Improve the blocks you already have.**

Optional modules can register methods on compatible Prefab objects without modifying or becoming dependencies of the target package:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

Conceptually:

```text
Users only                 update()
+ Notifications            update()->notify()
+ Messaging                update()->notify()->email()
```

The provider owns the extension. Removing the optional provider removes only that capability; the base operation continues to work.

### Object Interoperability

Some integrations need no new method at all. Compatible Prefab objects simply pass naturally between blocks:

```text
Input UploadedFile → Files
Files attachment   → Messaging
Auth actor         → Permissions / Logs
```

**Infrastructure is auto-wired. Business decisions remain explicit.** Prefab may automatically audit a login; it will not decide that every user update should send an email. Your application expresses that intent with `->email()` or `->notify()`.

See [Automatic integration](docs/auto-integration.md) and [Fluent Extensions](docs/fluent-extensions.md).

## Why Prefab?

A small PHP project often begins by rebuilding routing, validation, sessions, permissions, uploads and other infrastructure. A full framework solves those problems, but also brings its own application structure and conventions.

Prefab provides a third option:

- **Install only what you need.** Every package is independently useful.
- **Add blocks and gain integrations.** Compatible modules can auto-wire and contribute fluent capabilities.
- **Start simple and grow without rewriting.** Advanced capabilities are additive.
- **Modernize existing PHP incrementally.** Existing PDO connections, tables, sessions, pages and libraries remain usable.
- **Keep control of the architecture.** No required base controllers, models, ORM, project skeleton or prescribed folder structure.
- **Mix Prefab with other libraries.** Small contracts and adapters are preferred over ecosystem lock-in.
- **Understand the magic.** Runtime inspection exposes capabilities, extensions and resolution decisions.

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
| **Messaging** | External outbound communication such as email, SMTP and provider adapters | [Messaging](packages/messaging/README.md) |
| **Notifications** | Internal system-to-user notices and unread state | [Notifications](packages/notifications/README.md) |

Architecture docs: [Automatic integration](docs/auto-integration.md) · [Fluent Extensions](docs/fluent-extensions.md) · [Packagist/release process](docs/packagist-release.md)

## Which package should I use?

```text
Structured data/database?                → prefab-database
Project-owned users?                     → prefab-users
Login/current-user support?              → prefab-auth
Authorization/access control?            → prefab-permissions
Audit/activity history?                  → prefab-logs
Application routing?                     → prefab-routes
Clean/validated request data?            → prefab-input
File storage/organization?               → prefab-files
Email/external communication?            → prefab-messaging
Internal bell/inbox notifications?       → prefab-notifications
```

There is **no required Core package**.

## Start small

```php
$routes = new RouteManager();
$result = Input::from($_POST)->process(['name' => 'trim|required|string']);
$files = new FileManager(['root' => __DIR__ . '/storage']);
```

External and internal communication remain separate:

```php
$messaging->mail('user@example.com', 'Annual Report', 'Your report is ready.');
$notifications->send(25, 'Document Approved', 'OBR-2026-001 has been approved.');
```

A business operation may intentionally use both through compatible extensions:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

## Extension availability and errors

Fluent extensions are optional capabilities. Applications may inspect them:

```php
if ($operation->hasExtension('notify')) {
    $operation->notify();
}

$available = $operation->extensions();
```

Calling an extension that has not been registered throws a catchable `BadMethodCallException`; it is never silently ignored. Equal-priority extension conflicts produce a clear ambiguity error instead of Prefab guessing.

Global diagnostics expose the assembled application:

```php
$diagnostics = PrefabRuntime::inspect();

$diagnostics['modules'];
$diagnostics['capabilities'];
$diagnostics['extensions'];
$diagnostics['resolutions'];
```

## Error handling

**Errors must be useful, predictable and catchable.** Prefab should not hide failures or turn ordinary runtime problems into mysterious behavior.

```text
Expected invalid input          → validation errors/result
Expected delivery outcome       → result object
Missing optional extension      → capability check / BadMethodCallException
Ambiguous integration           → clear RuntimeException
Invalid API/configuration       → exception
Unsafe/impossible operation     → exception
Unexpected infrastructure error → exception or documented failure result
```

Prefab libraries should not unexpectedly `die()`/`exit()`, print errors directly or expose secrets in exception messages. Applications decide how errors are logged, displayed or converted into HTTP/API responses.

## Grow without replacing the foundation

```text
Day 1                    PHP + Routes
Day 10                   + Input
Later                    + Auth + Permissions
Larger application       + Files + Logs + other needed blocks
```

Adding a package should add capability—not force migration to a new application architecture.

## Existing applications are first-class

Prefab adapts to what already works. Existing applications may keep their PDO connection, user schema, session strategy, controllers, templates and third-party packages.

```text
Existing PHP application
        ↓ add one Prefab block
Existing PHP + improved capability
        ↓ add another
More capability + compatible integrations
```

## Integration rules

Prefab keeps automation useful without making it mysterious:

1. **Auto-wire infrastructure, not business policy.**
2. **The provider owns its fluent extension.** Users does not contain Messaging code just to expose `email()`.
3. **The target remains standalone.** Removing an extension provider does not break its base API.
4. **Extensions must make semantic sense.** Not every method belongs on every object.
5. **Prefer interoperability when no fluent method is necessary.**
6. **Explicit configuration always wins over discovery.**
7. **Ambiguity is an error, never a guess.**
8. **Automatic behavior must be inspectable.**

Initial extension direction:

| Provider | Enhances | Fluent capability |
|---|---|---|
| Auth | Routes | `auth()` |
| Permissions | Routes | `can()` |
| Input | Routes | `validate()` |
| Notifications | compatible operations/results | `notify()` |
| Messaging | compatible operations/results | `email()` |
| Logs | auditable operations/results | `audit()` |

## Configuration philosophy

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

`prefab-php` is the development source of truth. Individual distribution repositories are publication targets when modules are ready for Packagist.

## Prefab design rules

1. **Standalone first.**
2. **Start simple; advanced features are additive.**
3. **One coherent responsibility per module.**
4. **Compatible blocks should improve one another where useful.**
5. **Auto-wire infrastructure, not application business decisions.**
6. **Fluent extensions belong to the capability provider, not the target.**
7. **Explicit configuration wins.**
8. **Automatic behavior is diagnosable.**
9. **Project data remains project-owned.**
10. **Provider/framework neutral.**
11. **No unnecessary package fragmentation.**
12. **No framework creep.**
13. **Failures are explicit and catchable.**
14. **Keep the exit door open; avoid architecture lock-in.**

## Documentation standard

Every public package should document: purpose, installation, one-minute quick start, common usage, error handling, advanced usage, integrations, contributed/accepted fluent extensions, adapters, diagnostics/troubleshooting and responsibility boundaries.

## Documentation map

**[Database](packages/database/README.md) · [Users](packages/users/README.md) · [Auth](packages/auth/README.md) · [Permissions](packages/permissions/README.md) · [Logs](packages/logs/README.md) · [Routes](packages/routes/README.md) · [Input](packages/input/README.md) · [Files](packages/files/README.md) · [Messaging](packages/messaging/README.md) · [Notifications](packages/notifications/README.md)**

> **Start with one block. Add more when you need them. Keep your architecture.**
