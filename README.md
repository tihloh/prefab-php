# Tihloh Prefab PHP

> ## **PHP building blocks. Your architecture.**

### **Modular** · **Reusable** · *Independent* · **Adaptive** · ***Better Together***

Start with one block. Add another. **Gain capabilities.**  
Improve the blocks you already have.

Prefab gives PHP applications reusable building blocks without requiring a full framework or forcing a project structure.

```bash
composer require tihloh/prefab-routes
```

Use one package by itself, or combine compatible packages and let Prefab handle useful integration plumbing.

> **New to Prefab? Start here: [Getting Started](docs/getting-started.md)**

## In one minute

```text
Need routing?       Install Routes.
Need validation?    Add Input.
Need login?         Add Auth.
Already have users? Add Users and map your existing table.
Need permissions?   Add Permissions.
Need audit logs?    Add Logs.
```

You do **not** install everything. There is **no required Core package**.

## What makes Prefab different?

A normal collection of packages gives you more separate APIs as you install more packages. Prefab aims to do something more useful:

> **Adding a compatible module can improve modules you already installed.**

The easiest way to see the difference is in ordinary application code.

## Without Prefab vs. with Prefab

Imagine a small PHP application that needs **Users + Authentication + Activity Logging**.

### Without Prefab

Plain PHP can absolutely do this, but your application has to own and connect all of the plumbing:

```php
session_start();

// Find the user.
$stmt = $pdo->prepare(
    'SELECT * FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$_POST['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($_POST['password'], $user['password'])) {
    throw new RuntimeException('Invalid credentials.');
}

// Establish the authenticated session.
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];

// Record the login.
$stmt = $pdo->prepare(
    'INSERT INTO logs (user_id, action, created_at)
     VALUES (?, ?, NOW())'
);
$stmt->execute([$user['id'], 'auth.login']);
```

This works. The problem is not PHP—it is the repeated infrastructure code your application now owns:

```text
Find the user
      ↓
Verify the password
      ↓
Manage the session safely
      ↓
Remember the current user
      ↓
Create the audit entry
      ↓
Handle failures consistently
```

As the project grows, you normally start extracting this plumbing into your own user, authentication, session and logging classes.

### With Prefab

Install only the blocks the application needs:

```bash
composer require tihloh/prefab-users
composer require tihloh/prefab-auth
composer require tihloh/prefab-logs
```

Then the application can focus on the operation it actually wants to perform:

```php
$result = $auth->login([
    'email'    => $_POST['email'],
    'password' => $_POST['password'],
]);

if (!$result->ok()) {
    // Show the application's login error.
}
```

The idea is not merely "fewer lines of code." The installed blocks have clear responsibilities and can integrate:

```text
$auth->login(...)
       │
       ├── Users
       │    └── resolve the application user
       │
       ├── Auth
       │    ├── verify credentials
       │    └── establish authentication
       │
       └── Logs
            └── audit the authentication event
```

Your application still decides what happens next:

```php
$result = $auth->login($credentials);

if ($result->ok()) {
    // YOUR application decides what to do next.
}
```

> **You decide what the application does. Prefab handles reusable plumbing.**

### The modules remain independent

Don't need logging yet? Don't install it.

```bash
composer require tihloh/prefab-users
composer require tihloh/prefab-auth
```

Authentication still has a useful combination:

```text
Users + Auth
     ↓
User authentication
```

Later, add Logs:

```bash
composer require tihloh/prefab-logs
```

The goal is that compatible infrastructure gains integration without forcing you to redesign the application:

```text
Users + Auth + Logs
         ↓
Authentication
         +
Auditing
```

This is the meaning of ***Independent*** and ***Better Together***: each block remains useful on its own, while compatible blocks can provide more value when combined.

> **Note:** Prefab is actively evolving. Documentation distinguishes established APIs from integration direction; an example should not be treated as a guarantee that every illustrated integration is available in every released package version.

## Automatic plumbing vs. explicit business decisions

Prefab does **not** turn every installed module into automatic behavior.

Authentication logging is infrastructure: a successful login is naturally an authentication event and can be audited by an integrated Logs module.

Sending an email after changing a user is different. That is a **business decision**, so the application should ask for it explicitly.

```php
$users->update($id, $data);
```

Installing Messaging should not silently turn that into an email.

When the application wants communication, a compatible Fluent Extension can express it directly:

```php
$users->update($id, $data)
      ->email();
```

Add Notifications and the same operation can become:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

This gives Prefab a simple rule:

```text
Automatic infrastructure integration
              ↓
          Auto-Wiring

Explicit optional capability
              ↓
       Fluent Extensions
```

Short application code does **not** mean hidden business policy.

## The Prefab growth idea

```text
Users
  └─ user management works

Users + Auth
  └─ authentication can use the Users capability

Users + Auth + Logs
  └─ authentication infrastructure can gain auditing

Users + Notifications
  └─ compatible operations can gain ->notify()

Users + Notifications + Messaging
  └─ compatible operations can gain ->notify()->email()
```

## The three integration ideas

### 1. Auto-Wiring — Prefab connects infrastructure

> **Add a block. Prefab connects what makes sense.**

Example:

```text
Users ── user provider ──→ Auth
Auth  ── current actor ──→ Permissions / Logs
```

You can always configure things manually. **Explicit configuration wins.**

[Learn Auto-Wiring →](docs/auto-integration.md)

### 2. Fluent Extensions — installed modules add useful actions

A compatible module can add an optional fluent action without being hard-coded into the target module.

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

Conceptually:

```text
Users only            update()
+ Notifications       update()->notify()
+ Messaging           update()->notify()->email()
```

The module providing the capability owns the extension. Users does not need Messaging code inside it.

[Learn Fluent Extensions →](docs/fluent-extensions.md)

### 3. Object Interoperability — compatible objects simply fit

Not every integration needs a new method.

```text
Input UploadedFile → Files
Files attachment   → Messaging
Auth actor         → Permissions / Logs
```

Prefab uses the simplest integration that makes sense.

## What Prefab automates — and what it does not

The rule is simple:

> **Prefab automates plumbing. Your application decides business behavior.**

A login is naturally an authentication event, so integrated logging can audit it automatically. But changing a user's profile does not automatically mean "send an email" because that is a business decision.

Your application makes that decision explicitly:

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

Short code, but no hidden business policy.

## Choose a module

| I need... | Use | What it owns |
|---|---|---|
| Database connections / lightweight queries | [Database](packages/database/README.md) | Database infrastructure |
| Existing/project-owned users | [Users](packages/users/README.md) | User mapping and management |
| Login/logout/current user | [Auth](packages/auth/README.md) | Authentication |
| Access rules/groups/permissions | [Permissions](packages/permissions/README.md) | Authorization |
| HTTP routing | [Routes](packages/routes/README.md) | Request routing |
| Validation/request data/uploads | [Input](packages/input/README.md) | Input processing |
| File storage | [Files](packages/files/README.md) | Filesystem/storage operations |
| Audit/activity history | [Logs](packages/logs/README.md) | Logging |
| Email/external communication | [Messaging](packages/messaging/README.md) | Outbound communication |
| Internal bell/inbox notices | [Notifications](packages/notifications/README.md) | In-app notifications |

A useful distinction:

```text
Auth            = Who are you?
Permissions     = What are you allowed to do?

Messaging       = Send something outside the application
Notifications   = Show/store a notice inside the application

Input           = Understand/validate incoming data
Files           = Store/manage files
```

## Start small

A small project does not need central configuration or runtime knowledge.

```php
$routes = new RouteManager();

$routes->get('/', fn () => 'Home');
$routes->get('/users/{id}', 'UserController@show');

$result = $routes->dispatch();
```

Or use Users directly with an existing PDO connection:

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);

$user = $users->find(25);
```

Learn advanced integration only when your application actually needs it.

## Grow progressively

```text
Small site
└── Routes

Growing application
├── Routes
└── Input

Authenticated application
├── Routes
├── Input
├── Users
└── Auth

Larger application
├── Routes
├── Input
├── Users
├── Auth
├── Permissions
├── Files
└── Logs
```

The original APIs remain useful as the application grows.

## Existing projects are first-class

Prefab is designed to fit existing PHP applications instead of demanding a rewrite.

You can keep your existing:

- database schema and PDO connection;
- users/employees/accounts table;
- controllers and templates;
- directory structure;
- deployment model;
- third-party libraries.

For example, Prefab Users maps your table rather than requiring a special Prefab users table.

```text
Existing project
      ↓
add one Prefab package
      ↓
existing project + new capability
      ↓
add another compatible package
      ↓
more capability + useful integration
```

## Configuration — only when you need it

Small applications can configure each module directly. Larger applications can share configuration through `PrefabConfig` and Auto-Wiring.

Resource resolution follows this order:

```text
Direct module configuration       ← strongest
        ↓
Module-specific PrefabConfig
        ↓
Common PrefabConfig
        ↓
Auto-Wired compatible capability
        ↓
Sensible module default
        ↓
Clear error if unresolved
```

> **Explicit configuration wins. Auto-Wiring fills the gaps.**

## Diagnostics

Prefab's automatic behavior should never be mysterious.

Inspect one module:

```php
$auth->explain();
$users->explain();
```

Inspect the whole Prefab composition:

```php
$info = PrefabRuntime::inspect();
```

This can show modules, capability providers, fluent extensions and resource-resolution decisions without exposing secret connection values.

## Errors

Prefab aims for errors that are clear, predictable and catchable.

```text
Invalid user input          → validation result/errors
Missing required resource   → clear exception
Missing optional extension  → BadMethodCallException
Ambiguous integration       → RuntimeException with conflict details
Delivery failure            → documented result/exception
```

Prefab libraries should not unexpectedly `die()` or `exit()` for ordinary library failures. The host application decides whether an error becomes HTML, JSON, a redirect, a log entry or something else.

## Documentation paths

If you are learning Prefab, follow this order:

1. **[Getting Started](docs/getting-started.md)** — understand Prefab without runtime internals.
2. **The module README you actually need** — use the package standalone first.
3. **[Auto-Wiring](docs/auto-integration.md)** — understand automatic infrastructure integration.
4. **[Fluent Extensions](docs/fluent-extensions.md)** — understand features such as `->notify()` and `->email()`.
5. **[Packagist / release process](docs/packagist-release.md)** — maintainer/release information.

## Integration rules

These rules keep ***Better Together*** from turning into hidden framework magic:

1. **Standalone first.** Every module must remain useful by itself.
2. **Auto-wire infrastructure, not business policy.**
3. **Explicit configuration always wins.**
4. **The provider owns its fluent extension.**
5. **Removing an optional provider must not break the target's base API.**
6. **Extensions must make semantic sense.** Not every method belongs everywhere.
7. **Prefer ordinary object interoperability when it is clearer.**
8. **Ambiguity is an error, never a guess.**
9. **Automatic behavior must be inspectable.**
10. **Keep the application's architecture under application control.**

Initial Fluent Extension direction:

| Provider | Enhances | Capability |
|---|---|---|
| Auth | Routes | `auth()` |
| Permissions | Routes | `can()` |
| Input | Routes | `validate()` |
| Notifications | compatible operations/results | `notify()` |
| Messaging | compatible operations/results | `email()` |
| Logs | auditable operations/results | `audit()` |

## Monorepo and releases

`prefab-php` is the development source of truth. Individual distribution repositories are publication targets for standalone Composer packages.

Release/Packagist details are documented separately so package users do not need to understand repository-maintenance mechanics: [Packagist / release process](docs/packagist-release.md).

## Documentation standard

Every public package should clearly answer:

```text
What problem does this module solve?
What does it NOT do?
How do I install it?
What is the smallest working example?
What are the common operations?
What errors should I expect?
How does it work alone?
What improves when other Prefab modules are installed?
What extensions does it provide or accept?
How do I inspect/troubleshoot it?
```

> **Start with one block. Add more when you need them. Keep your architecture.**
