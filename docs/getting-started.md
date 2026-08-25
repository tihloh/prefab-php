# Getting Started with Prefab

> **Prefab is a set of PHP building blocks. Use one, several, or many.**

If you remember only one thing, remember this:

```text
You choose the modules.
Prefab connects compatible infrastructure.
Your application keeps the business logic.
```

## 1. What Prefab is — and is not

Prefab is **not a full-stack framework** and does not require a project skeleton, MVC structure, ORM, base controller or special deployment model.

Each package solves one area:

| Need | Package |
|---|---|
| Database connections and simple queries | `prefab-database` |
| Existing/project-owned users | `prefab-users` |
| Login, logout and current user | `prefab-auth` |
| Roles/groups/permissions | `prefab-permissions` |
| HTTP routes | `prefab-routes` |
| Request input, validation and uploads | `prefab-input` |
| File storage | `prefab-files` |
| Audit/activity logs | `prefab-logs` |
| Email/external messages | `prefab-messaging` |
| Internal user notifications | `prefab-notifications` |

You do **not** need to install all of them.

## 2. Smallest possible use

Install only what you need:

```bash
composer require tihloh/prefab-routes
```

Then use it normally:

```php
require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();
$routes->get('/', fn () => 'Hello');

echo $routes->dispatch();
```

Nothing else in Prefab is required.

## 3. Add another block

Later you may need validated input:

```bash
composer require tihloh/prefab-input
```

You now have Routes **and** Input. Each remains usable independently, but compatible integrations may become available when they make sense.

This is the Prefab growth model:

```text
Routes
  ↓
Routes + Input
  ↓
Routes + Input + Auth
  ↓
Routes + Input + Auth + Permissions
```

You grow the application without replacing its foundation.

## 4. Three ways modules work together

This is the most important Prefab concept.

### A. Auto-Wiring — automatic infrastructure

When one module can safely provide infrastructure another module needs, Prefab can connect them automatically.

Example:

```text
Users provides user_provider
          ↓
Auth discovers it
          ↓
Auth can authenticate project users
```

Another example:

```text
Auth provides current actor
          ↓
Permissions / Logs can use that actor
```

You can still configure these relationships manually. **Explicit configuration always wins.**

### B. Fluent Extensions — optional features appear on compatible Prefab objects

Installing a module can add useful actions to objects from another module.

Conceptually:

```php
$users->update($id, $data);
```

Add Notifications and a compatible user operation can gain:

```php
$users->update($id, $data)
      ->notify();
```

Add Messaging too:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

The important part: **Users does not need to contain Notifications or Messaging code.** The module providing the feature owns and registers the extension.

Prefab's own extension-aware objects handle this internally. Normal application code should not need to add an internal extension trait merely to use built-in Prefab integrations.

### C. Object Interoperability — objects simply fit together

Sometimes no new method is needed.

For example:

```text
Input UploadedFile
       ↓
Files stores it
```

or:

```text
Files attachment
       ↓
Messaging sends it
```

Prefab prefers normal object interoperability when that is clearer than adding another fluent method.

## 5. What is automatic and what is explicit?

A useful rule:

> **Prefab automates plumbing. Your application decides business behavior.**

For example, a successful login is naturally an authentication event, so if Logs is integrated it can be audited automatically.

But updating a user's name does **not** necessarily mean that user should receive an email. That is your application's decision:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

The code stays short, but the business decision remains visible.

## 6. Standalone vs integrated

### Users alone

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);

$users->update(25, ['name' => 'Christian']);
```

### Users + Auth

Users can become Auth's user source automatically when compatible configuration is available.

### Users + Auth + Logs

Authentication/user infrastructure events can be audited with the current actor context.

### Users + Notifications + Messaging

Compatible operation results can expose explicit communication actions:

```php
$users->update(25, $data)
      ->notify()
      ->email();
```

The base Users API remains valid regardless of which optional modules are installed.

## 7. Configuration: simple first

For a small application, configure a module directly:

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);
```

For a larger application, shared configuration can reduce repetition:

```php
PrefabConfig::set([
    'database' => $pdo,
    'modules' => [
        'users' => [
            'map' => $map,
        ],
    ],
]);

$users = new UserManager();
```

Resolution is straightforward:

```text
Direct module configuration       highest priority
        ↓
Module-specific PrefabConfig
        ↓
Common PrefabConfig
        ↓
Auto-discovered compatible capability
        ↓
Sensible default, if one exists
        ↓
Clear error if still unresolved
```

You do not need central configuration for small applications.

## 8. Errors are not hidden

Prefab should fail clearly instead of silently guessing.

Examples:

```text
Invalid form input          → validation result/errors
Missing required resource   → clear exception
Missing fluent extension    → BadMethodCallException
Ambiguous provider          → RuntimeException explaining the conflict
Delivery failure            → documented delivery result/exception
```

Prefab modules should not unexpectedly call `die()` or `exit()` for ordinary library failures. Your application decides how an error becomes HTML, JSON, a redirect or a log entry.

## 9. See what Prefab connected

Automatic behavior must be explainable.

Inspect a module:

```php
$auth->explain();
$users->explain();
```

Inspect the assembled Prefab runtime:

```php
$info = PrefabRuntime::inspect();
```

This can show registered modules, capabilities, fluent extensions and resource-resolution decisions.

## 10. Recommended learning order

If you are new to Prefab, do not begin with the runtime internals. Start with the package you need, then learn integration only when you add a second package.

```text
1. Pick a module
2. Read its Quick Start
3. Build the feature
4. Add another module when needed
5. Read Auto-Wiring / Fluent Extensions when modules begin cooperating
```

## Next

- [Main documentation](../README.md)
- [Automatic integration / Auto-Wiring](auto-integration.md)
- [Fluent Extensions](fluent-extensions.md)
- [Database](../packages/database/README.md)
- [Users](../packages/users/README.md)
- [Auth](../packages/auth/README.md)
- [Permissions](../packages/permissions/README.md)
- [Routes](../packages/routes/README.md)
- [Input](../packages/input/README.md)
- [Files](../packages/files/README.md)
- [Logs](../packages/logs/README.md)
- [Messaging](../packages/messaging/README.md)
- [Notifications](../packages/notifications/README.md)
