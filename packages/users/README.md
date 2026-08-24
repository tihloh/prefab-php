# Prefab Users

**Prefab Users** is a framework-independent user abstraction for PHP applications that lets a project keep its existing `users`, `employees`, `accounts`, or other user table.

> Map the project you already have instead of redesigning the project around the package.

Prefab Users is standalone. It does not require Prefab Database, Auth, Permissions, Logs, Routes, Laravel, or another framework.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

```bash
composer require tihloh/prefab-users
```

## Goals

- Keep project ownership of its user table and schema.
- Map arbitrary project columns into a reusable `PrefabUser` object.
- Preserve extra project fields as attributes.
- Support project-specific user classes through `UserFactoryInterface`.
- Accept plain PDO or Prefab's `DatabaseInterface` for built-in database access.
- Cooperate with Auth, Permissions and Logs without requiring them.

---

# 1. Quick start

Suppose an existing project uses this table conceptually:

```text
employees
├── employee_id
├── full_name
├── email_address
├── is_active
├── office_name
├── position_title
└── employee_no
```

Map it instead of changing it:

```php
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\Services\UserManager;

$map = new UserMap(
    table: 'employees',
    id: 'employee_id',
    name: 'full_name',
    email: 'email_address',
    active: 'is_active',
    attributes: [
        'office' => 'office_name',
        'position' => 'position_title',
        'employee_no' => 'employee_no',
    ],
    allowDelete: false,
);

$users = new UserManager(
    new PdoUserProvider($pdo, $map),
);

$user = $users->find(25);
```

Prefab now exposes a consistent user abstraction while the database remains project-owned.

---

# 2. User mapping

`UserMap` describes how project fields correspond to Prefab's common user fields.

```php
$map = new UserMap(
    table: 'employees',
    id: 'employee_id',
    name: 'full_name',
    email: 'email_address',
    active: 'is_active',
);
```

Conceptually:

```text
Project column       Prefab meaning
-----------------------------------
employee_id       →  id
full_name         →  name
email_address     →  email
is_active         →  active
```

The project does not need to rename its columns.

---

# 3. Extra attributes

Project-specific fields can remain available:

```php
$map = new UserMap(
    table: 'employees',
    id: 'employee_id',
    name: 'full_name',
    email: 'email_address',
    attributes: [
        'office' => 'office_name',
        'position' => 'position_title',
        'employee_no' => 'employee_no',
    ],
);
```

Then application code can work with the mapped attributes on the resulting user object instead of losing project-specific information.

---

# 4. CRUD

The manager provides a compact CRUD API:

```php
$users->all();
$users->find(25);
$users->findByEmail('user@example.com');
$users->create([...]);
$users->update(25, [...]);
$users->delete(25);
```

Example:

```php
$user = $users->create([
    'name' => 'Demo User',
    'email' => 'demo@example.com',
]);
```

Update:

```php
$users->update(25, [
    'name' => 'Updated User',
]);
```

---

# 5. Controlling write operations

Projects may deliberately make their user source read-only or partially writable.

`UserMap` supports capabilities such as:

```text
allowCreate
allowUpdate
allowDelete
```

For example:

```php
$map = new UserMap(
    table: 'employees',
    id: 'employee_id',
    name: 'full_name',
    email: 'email_address',
    allowDelete: false,
);
```

This is useful when Prefab should consume an authoritative employee/account table without being allowed to delete records from it.

---

# 6. Direct database configuration

A database-backed manager can be configured directly:

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);
```

Direct configuration affects that Users instance and has the highest normal priority.

---

# 7. Central Prefab configuration

Common resources and module-specific settings can be configured centrally:

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

This keeps application bootstrap configuration in one place while preserving the ability to override individual modules.

---

# 8. Configuration resolution

Database-backed Users follows the Prefab configuration hierarchy:

```text
1. Direct Users configuration
2. Users-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered database capability
5. Internal/default behavior where applicable
6. Clear error when a required resource remains unresolved
```

This means a small application can configure Users explicitly, while a larger Prefab application can share infrastructure automatically.

---

# 9. Prefab Database integration

When Prefab Database is present, Users can inherit a compatible database capability:

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
    ],
]);

$users = new UserManager();
```

Conceptually:

```text
Prefab Database
      ↓
database capability
      ↓
Prefab Users
```

Users does not require Prefab Database; it merely knows how to cooperate with the shared database contract.

---

# 10. Database abstraction

The built-in database provider accepts either:

```text
PDO
DatabaseInterface
```

A plain PDO connection is normalized automatically:

```text
PDO
 ↓
PdoDatabaseAdapter
 ↓
DatabaseInterface
 ↓
PdoUserProvider
```

The historical `PdoUserProvider` name remains for compatibility even though its internal database dependency is now the framework-independent interface.

This design allows future Laravel, Doctrine or other framework adapters to provide the same contract without changing `UserManager`.

---

# 11. Custom provider

Applications are not limited to the built-in database provider. A custom provider can implement the appropriate Users provider contract and supply users from another source.

Conceptually:

```text
Database / API / LDAP / project service
                ↓
          user provider
                ↓
           UserManager
```

This keeps the manager independent of one persistence strategy.

---

# 12. Custom user class

Projects may extend `PrefabUser` with domain-specific behavior:

```php
use Tihloh\Prefab\Users\User\PrefabUser;

class Employee extends PrefabUser
{
    public function displayName(): string
    {
        return $this->name . ' - ' . ($this->position ?? '');
    }
}
```

Implement `UserFactoryInterface` and provide that factory to the user provider. Prefab can then return your `Employee` objects instead of only the base user class.

This preserves a common Prefab contract without removing project-specific behavior.

---

# 13. Prefab Auth integration

Users can expose the user-provider capability needed by Prefab Auth:

```text
Prefab Users
     ↓
user_provider
     ↓
Prefab Auth
```

The application can therefore keep user ownership in Users while Auth focuses only on authentication.

Neither package must become a hard dependency of the other.

---

# 14. Prefab Permissions integration

Users and Permissions can cooperate while retaining separate responsibilities:

```text
Prefab Users
     ↓
user identity / groups
     ↓
Prefab Permissions
     ↓
effective authorization
```

The project remains free to define its own user/group model as long as the appropriate contracts or adapters are supplied.

---

# 15. Prefab Logs integration

When a compatible logger is available, Users can emit activity records for operations such as user creation, modification and deletion.

Prefab Auth may also provide the current actor so a log can distinguish:

```text
actor  → person performing the change
subject → user being changed
```

This cooperation is optional.

---

# 16. HTTP usage

`Http\UserController` is transport-neutral and returns arrays suitable for JSON serialization.

A router may expose endpoints such as:

```text
GET     /api/v1/users
GET     /api/v1/users/{id}
POST    /api/v1/users
PUT     /api/v1/users/{id}
DELETE  /api/v1/users/{id}
```

Prefab Users itself does not force those URLs or require a router. Prefab Routes or another routing system may map them however the application prefers.

---

# 17. Diagnostics

Use:

```php
$info = $users->explain();
```

Diagnostics show how Users resolved important resources such as its provider, database, table, logger and actor integrations.

This is particularly useful when automatic Prefab interoperability is active because it answers:

> Why is this module using this resource?

without exposing actual database connection objects or sensitive configuration values.

---

# 18. Practical small application

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);

$user = $users->find(25);
```

That is enough for a small project. No global configuration or other Prefab modules are required.

---

# 19. Practical modular application

```php
PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'users' => [
            'map' => $employeeMap,
        ],
    ],
]);

$database = new DatabaseManager();
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

Conceptually:

```text
Database
   ↓
Users ─────→ Auth
  │           │
  └────→ Permissions
  │
  └────→ Logs
```

Each module remains independently configurable.

---

# 20. API quick reference

Common `UserManager` operations:

| API | Purpose |
|---|---|
| `all()` | Return users |
| `find()` | Find a user by ID |
| `findByEmail()` | Find a user by email |
| `create()` | Create a mapped user record |
| `update()` | Update a mapped user record |
| `delete()` | Delete a mapped user record when allowed |
| `explain()` | Inspect resolved configuration/integrations |

Important extension points:

| Component | Purpose |
|---|---|
| `UserMap` | Maps project schema to Prefab fields |
| `PrefabUser` | Common reusable user object |
| `UserFactoryInterface` | Creates custom user objects |
| user provider contract | Supplies users to `UserManager` |
| `DatabaseInterface` | Framework-independent database contract |

---

# 21. Design philosophy

Prefab Users is an adapter around the project's user domain, not a demand to replace it.

```text
Existing project
      ↓
map existing users
      ↓
PrefabUser abstraction
      ↓
optional Auth / Permissions / Logs
```

Small projects can configure a provider directly. Larger systems can inherit shared database and integration capabilities automatically.

The core principle remains the same: **your project owns its users; Prefab makes them reusable.**
