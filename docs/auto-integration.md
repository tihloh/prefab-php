# Prefab Auto-Wiring

> **Add a block. Prefab connects what makes sense.**

Auto-Wiring is Prefab's automatic **infrastructure integration** system. It lets standalone modules discover compatible services from other installed Prefab modules without creating hard package dependencies.

If this is your first time using Prefab, read the [Getting Started guide](getting-started.md) first.

## The simple idea

Suppose you install Users and Auth separately:

```text
Prefab Users                  Prefab Auth
provides users                needs users
      │                           │
      └──── user_provider ────────┘
```

When both managers are present, Auth can use the compatible user provider published by Users.

You do not have to manually connect them **unless you want to override the automatic choice**.

Another example:

```text
Auth
 └─ provides current actor
          ↓
Permissions / Logs
 └─ can use current actor
```

## What Auto-Wiring should automate

Auto-Wiring is for reusable infrastructure relationships:

```text
Users → Auth                  user provider
Auth → Permissions           current actor
Auth → Logs                  authentication audit context
Database → compatible blocks shared database capability
Logs ← compatible blocks     structured infrastructure events
```

It should **not invent application business rules**.

For example:

```text
User logs in
→ automatic audit is reasonable

User profile changes
→ automatically emailing the user is NOT assumed
```

If your application wants the second behavior, make the intent explicit:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

Those optional actions are [Fluent Extensions](fluent-extensions.md), not Auto-Wiring.

## You can still configure modules manually

Auto-Wiring is a convenience, not a requirement.

A small application can configure a module directly:

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);
```

That direct configuration has priority over automatically discovered resources.

## Configuration priority

When Prefab needs a resource, it resolves it in this order:

```text
1. Direct configuration on this module       ← strongest
2. This module's PrefabConfig settings
3. Common PrefabConfig settings
4. Compatible Auto-Wired capability
5. Module default, when one exists
6. Clear error if still unresolved
```

The short version is:

> **Explicit configuration wins. Auto-Wiring fills the gaps.**

Example:

```php
PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);

$auth = new AuthManager([
    'session_key' => 'my_app_auth',
]);
```

Here, Auth's direct `session_key` applies only to Auth. Logs' module-specific database choice applies to Logs. The common database remains available to modules that have no more-specific choice.

## What is a capability?

A capability is simply a small named service that one module makes available to compatible modules.

Common examples include:

```text
database                       default database
database.connection.<name>     named database connection
user_provider                  source of application users
actor_provider                 current authenticated actor
permission_store               permission persistence
logger                         logging service
```

You normally **use the module API**, not these capability names directly. They exist so Prefab modules can cooperate without requiring each other's packages.

## Example integration graph

```text
Database
   │
   ├────→ Users ─────→ Auth ─────→ actor_provider
   │                                │
   ├────→ Permissions ←─────────────┘
   │
   └────→ Logs ← authentication/user events
```

Each box remains independently installable. The arrows are integrations that become possible when compatible boxes are present.

## Does declaration order matter?

Prefab re-runs a small configuration pass as managers register, so normal declaration order is flexible:

```php
$database = new DatabaseManager();
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

Resolved references are cached during configuration. Normal feature calls do not need to rediscover the whole application repeatedly.

## Do I need `PrefabRuntime::ready()`?

Usually, **no**.

Applications that want an explicit startup boundary may call:

```php
PrefabRuntime::ready();
```

This performs the final configuration pass and prevents new module names from being registered afterward. It is useful for stricter bootstraps and diagnostics, but normal Prefab applications do not need it.

## How do I know what was connected?

Ask Prefab.

```php
$users->explain();
$auth->explain();
$permissions->explain();
$logs->explain();
$database->explain();
```

For the complete application:

```php
$info = PrefabRuntime::inspect();
```

The runtime inspection can show:

```text
registered modules
capability providers
fluent extensions
provider priorities
resource-resolution decisions
```

It should not dump secrets or raw connection objects.

## What happens if two modules provide the same thing?

Prefab does not silently guess.

If providers have different priorities, the higher-priority provider wins. If two providers have the same highest priority and Prefab cannot choose safely, it throws a clear ambiguity exception.

Resolve it by explicitly selecting/configuring the resource you want.

```text
One clear provider        → use it
Clear higher priority     → use it
Equal best providers      → error; application chooses
Explicit configuration   → application choice wins
```

## No required Core package

Prefab modules remain standalone. Installing only Users still means:

```bash
composer require tihloh/prefab-users
```

There is no mandatory `prefab-core` runtime package. Standalone packages embed the small shared interoperability bootstrap they need.

The monorepo maintains that shared bootstrap from:

```text
tools/prefab-bootstrap.php
```

and maintainers synchronize package copies before release with:

```bash
php tools/sync-prefab-bootstrap.php
```

Package consumers do not run that synchronization tool.

## Auto-Wiring vs Fluent Extensions

This distinction is important:

| Feature | Purpose | Example |
|---|---|---|
| **Auto-Wiring** | Connect infrastructure automatically | Auth discovers Users |
| **Fluent Extensions** | Add an explicit optional action | `$operation->notify()` |
| **Object Interoperability** | Let compatible objects pass naturally | Input upload → Files |

> **Auto-Wiring connects the plumbing. Fluent Extensions express optional actions. Your application keeps the business decisions.**
