# Prefab Permissions

**Prefab Permissions** provides framework-independent authorization for PHP applications with user overrides, group inheritance and configurable permission defaults.

> Define what an action means once, then resolve whether a user or subject may perform it.

Prefab Permissions is standalone. It does not require Prefab Database, Users, Auth, Logs, Routes, Laravel, or another framework package.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

```bash
composer require tihloh/prefab-permissions
```

---

# 1. Core model

Prefab resolves an effective permission through three levels:

```text
User override
     ↓
Group permission
     ↓
Definition default
```

A user-level override has the highest priority. If there is no override, group settings are considered. If neither supplies a decision, the permission definition's default is used.

This naturally supports tri-state behavior:

```text
ALLOW
DENY
INHERIT
```

`INHERIT` means there is no override at that level; resolution continues downward.

---

# 2. Permission definitions

Definitions describe the permissions known by the application.

They may come from an inline PHP array, PHP configuration file, or JSON file.

```php
$permissions = new PermissionManager([
    'definitions' => __DIR__ . '/config/permissions.php',
]);
```

JSON is also supported:

```php
$permissions = new PermissionManager([
    'definitions' => __DIR__ . '/config/permissions.json',
]);
```

A definition may contain a stable ID plus human-friendly information such as a name, description and default value.

Conceptually:

```text
documents.view
    Name:        View Documents
    Description: Allows viewing routed documents
    Default:     false
```

Stable machine IDs should be used in application code; friendly names are for humans and interfaces.

---

# 3. Checking permission

The common authorization question is:

```php
if ($permissions->can($user, 'documents.approve')) {
    // Allowed.
}
```

This keeps authorization logic out of controllers and business code.

Instead of repeatedly implementing:

```php
if ($user->role === 'admin' || ...) {
```

application code asks one stable question:

```php
$permissions->can($user, 'documents.approve');
```

---

# 4. User overrides

Explicitly allow an action for one user:

```php
$permissions->set(
    'user',
    $userId,
    'documents.approve',
    true,
);
```

Explicitly deny it:

```php
$permissions->set(
    'user',
    $userId,
    'documents.approve',
    false,
);
```

Restore inheritance:

```php
$permissions->clear(
    'user',
    $userId,
    'documents.approve',
);
```

Clearing is different from setting `false`: `false` is an explicit deny, while clearing removes the override and lets lower levels decide.

---

# 5. Group inheritance

A user can inherit permissions from one or more groups according to the subject/group information supplied to the permission system.

Conceptually:

```text
User: Christian
   ↓
Groups
├── Budget Staff
└── IT Support
   ↓
Group permission values
   ↓
Effective result
```

A user-specific override still takes precedence over inherited group behavior.

This makes it possible to manage most access at group level while handling exceptions for individual users.

---

# 6. Subject integration

Projects keep ownership of their user and group models.

For object-based resolution, a subject implements `PermissionSubjectInterface`:

```php
class User implements PermissionSubjectInterface
{
    public function __construct(
        public int $id,
        public array $groupIds = [],
    ) {}

    public function permissionSubjectId(): int|string
    {
        return $this->id;
    }

    public function permissionGroupIds(): array
    {
        return $this->groupIds;
    }
}
```

Then:

```php
$permissions->can($user, 'documents.approve');
```

Prefab does not require your model to extend a particular base class.

---

# 7. Custom storage

A custom `PermissionStoreInterface` can be supplied directly:

```php
$permissions = new PermissionManager(
    definitions: $definitions,
    store: $customStore,
);
```

This makes the permission engine independent of one database implementation.

Conceptually:

```text
Database / framework / service
             ↓
 PermissionStoreInterface
             ↓
    PermissionManager
```

---

# 8. Built-in database storage

The built-in database store accepts plain PDO or Prefab's `DatabaseInterface`:

```php
$permissions = new PermissionManager(
    $definitions,
    new PdoPermissionStore($pdo),
);
```

The historical `PdoPermissionStore` name remains for compatibility. Internally it consumes `DatabaseInterface`; plain PDO is adapted automatically.

---

# 9. Automatic database configuration

A common centralized configuration is:

```php
PrefabConfig::set([
    'database' => $mainPdo,

    'modules' => [
        'permissions' => [
            'definitions' => __DIR__ . '/config/permissions.php',
        ],
    ],
]);

$permissions = new PermissionManager();
```

Or select a named Prefab Database connection:

```php
PrefabConfig::set([
    'modules' => [
        'permissions' => [
            'connection' => 'security',
        ],
    ],
]);
```

---

# 10. Configuration resolution

Permissions follows the standard Prefab hierarchy:

```text
1. Direct Permissions configuration
2. Permissions-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal/default behavior where applicable
6. Clear error when required storage remains unresolved
```

This allows explicit standalone usage and low-configuration modular usage to coexist.

---

# 11. Database abstraction

Built-in database storage uses:

```text
PDO or framework database
          ↓
DatabaseInterface
          ↓
PdoPermissionStore
```

Database-specific DDL/upsert differences are isolated inside the built-in store for MySQL/MariaDB, PostgreSQL, SQLite and SQL Server.

The shared database interface deliberately remains small rather than turning Permissions into a database framework.

---

# 12. Prefab Users integration

Users can supply the identity/group information needed for authorization while Permissions remains responsible for the decision.

```text
Prefab Users
     ↓
subject + groups
     ↓
Prefab Permissions
     ↓
allow / deny
```

The modules remain independently installable.

---

# 13. Prefab Auth integration

Auth and Permissions answer different questions:

```text
Auth:        Who is logged in?
Permissions: What may that user do?
```

Typical flow:

```text
login
  ↓
Prefab Auth
  ↓
current user
  ↓
Prefab Permissions
  ↓
can('documents.approve')
```

Authentication success does not automatically imply authorization.

---

# 14. Prefab Routes integration

Authorization can be attached to routes rather than repeated inside every controller.

Conceptually:

```php
$routes->get('/documents/{id}', 'DocumentController@show')
    ->auth()
    ->permission('documents.view');
```

An integration layer or middleware can then use Prefab Permissions to enforce the route's requirement.

This keeps routing and authorization separate while allowing them to cooperate.

---

# 15. Prefab Logs integration

When a compatible logger is available, permission changes can produce structured audit records automatically.

A technical permission operation such as:

```text
permission.denied
actor_id = 7
subject_id = 25
permission = documents.view
```

can be rendered in a human-friendly form such as:

```text
Demo Admin denied View Documents for Test User.
```

Friendly permission definition names improve audit readability without changing the stable permission ID used by code.

---

# 16. Laravel and framework compatibility

Laravel's own user model, Auth system and Gate system do not need to be replaced.

An adapter/bridge can expose the contracts Prefab needs while Laravel remains the host framework.

The same principle applies to other frameworks:

```text
Host framework
     ↓
adapter / contract
     ↓
Prefab Permissions
```

Prefab consumes capabilities rather than demanding control of the application.

---

# 17. Diagnostics

Use:

```php
$info = $permissions->explain();
```

Diagnostics describe how important resources were resolved, including definitions, storage, table and database resources.

This is particularly useful when module-specific configuration, common configuration and automatic capability discovery coexist.

---

# 18. Practical small application

```php
$permissions = new PermissionManager(
    definitions: $definitions,
    store: $store,
);

if ($permissions->can($user, 'reports.view')) {
    showReports();
}
```

No other Prefab module is required.

---

# 19. Practical modular application

```php
PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'permissions' => [
            'definitions' => __DIR__ . '/config/permissions.php',
        ],
    ],
]);

$database = new DatabaseManager();
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

The resulting responsibilities remain clear:

```text
Users        → identity
Auth         → authentication
Permissions  → authorization
Logs         → audit history
```

---

# 20. Common authorization pattern

A controller or service can remain simple:

```php
if (!$permissions->can($user, 'documents.approve')) {
    http_response_code(403);
    return 'Forbidden';
}

approveDocument($documentId);
```

With routing middleware, the same check can be moved outside the controller entirely.

---

# 21. API quick reference

Common operations:

| API | Purpose |
|---|---|
| `can()` | Resolve whether a subject has a permission |
| `set()` | Set an explicit permission value |
| `clear()` | Remove an override and restore inheritance |
| `explain()` | Inspect configuration/resource resolution |

Important concepts:

| Component | Purpose |
|---|---|
| permission definition | Stable permission metadata/default |
| `PermissionSubjectInterface` | Supplies subject ID and groups |
| `PermissionStoreInterface` | Storage abstraction |
| `PdoPermissionStore` | Built-in database-backed store |
| `DatabaseInterface` | Shared database contract |

---

# 22. Design philosophy

Prefab Permissions keeps authorization policy independent from user storage, authentication, routing and presentation.

```text
Small application
      ↓
can() + explicit store

Application grows
      ↓
groups + user overrides

Application grows further
      ↓
shared database + logging

Large modular application
      ↓
Users + Auth + Permissions + Routes + Logs
```

The important rule remains:

> **Authentication identifies the user. Permissions decides what that user may do.**

Prefab makes those responsibilities cooperate without forcing them into one monolithic framework.
