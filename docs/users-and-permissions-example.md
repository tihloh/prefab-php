# Using Prefab Users + Permissions Together

This example shows how a normal PHP project can use both independent packages:

- `tihloh/prefab-users`
- `tihloh/prefab-permissions`

The two packages stay independent. The project decides how to connect them.

## Architecture

```text
Project
│
├── Project database
│   ├── employees                  <- project-owned user table
│   └── prefab_subject_permissions <- Permissions-owned table
│
├── Prefab Users
│   ├── UserMap
│   ├── PdoUserProvider
│   └── UserManager
│
└── Prefab Permissions
    ├── PermissionDefinitions
    ├── PdoPermissionStore
    ├── PermissionManager
    └── PermissionRuntime

ProjectUser
├── extends PrefabUser
├── implements PermissionSubjectInterface
└── uses HasPermissions

$user->can('documents.approve')
```

## Example project structure

```text
my-project/
├── composer.json
├── config/
│   └── permissions.json
├── src/
│   └── ProjectUser.php
├── bootstrap.php
└── public/
    └── index.php
```

## 1. Project user table

Assume the project already has this table:

```sql
CREATE TABLE employees (
    employee_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email_address VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    office_name VARCHAR(255) NULL,
    position_title VARCHAR(255) NULL
);
```

Prefab Users does not require the table to be named `users`. The project maps its existing columns.

## 2. Permission definitions

`config/permissions.json`

```json
{
    "documents.view": {
        "name": "View Documents",
        "description": "Can view documents.",
        "category": "Documents",
        "default": true
    },
    "documents.approve": {
        "name": "Approve Documents",
        "description": "Can approve documents.",
        "category": "Documents",
        "default": false
    },
    "documents.delete": {
        "name": "Delete Documents",
        "description": "Can delete documents.",
        "category": "Documents",
        "default": false
    }
}
```

Permission metadata stays in this template. Stored user/group overrides contain only permission IDs and boolean values.

## 3. Project user class

The project may extend `PrefabUser` and add Permissions capability.

`src/ProjectUser.php`

```php
<?php

namespace App;

use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Traits\HasPermissions;

class ProjectUser extends PrefabUser implements PermissionSubjectInterface
{
    use HasPermissions;

    public function permissionSubjectId(): int|string
    {
        return $this->id;
    }

    public function permissionGroupIds(): array
    {
        // Group membership integration will later be supplied by GroupManager.
        // Returning [] is valid when only defaults and direct user overrides are used.
        return [];
    }

    public function displayTitle(): string
    {
        return trim(($this->position ?? '') . ' - ' . ($this->office ?? ''), ' -');
    }
}
```

The project still gets its own methods and project-specific data while sharing the standard Prefab user object.

## 4. Bootstrap both Prefabs

`bootstrap.php`

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use App\ProjectUser;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Permissions\Repositories\PdoPermissionStore;
use Tihloh\Prefab\Permissions\Runtime\PermissionRuntime;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

$pdo = new PDO(
    'mysql:host=localhost;dbname=my_project;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$userMap = new UserMap(
    table: 'employees',
    id: 'employee_id',
    name: 'full_name',
    email: 'email_address',
    active: 'is_active',
    attributes: [
        'office' => 'office_name',
        'position' => 'position_title',
    ],
    allowCreate: true,
    allowUpdate: true,
    allowDelete: false,
);

$userFactory = new class implements UserFactoryInterface {
    public function make(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes = [],
    ): ProjectUser {
        return new ProjectUser(
            id: $id,
            name: $name,
            email: $email,
            active: $active,
            attributes: $attributes,
        );
    }
};

$userProvider = new PdoUserProvider($pdo, $userMap, $userFactory);
$users = new UserManager($userProvider);

$definitions = PermissionDefinitions::fromJsonFile(
    __DIR__ . '/config/permissions.json'
);

$permissionStore = new PdoPermissionStore($pdo);
$permissions = new PermissionManager($definitions, $permissionStore);

PermissionRuntime::use($permissions);

return [
    'pdo' => $pdo,
    'users' => $users,
    'permissions' => $permissions,
];
```

Both Prefabs use the same project PDO connection, but they remain separate modules.

## 5. Retrieve and use a user

```php
$services = require __DIR__ . '/bootstrap.php';

$users = $services['users'];

$user = $users->find(25);

if (!$user) {
    die('User not found');
}

echo $user->name;
echo $user->office;
echo $user->position;
echo $user->displayTitle();
```

The object returned by Users is the project's `ProjectUser` subclass.

## 6. Check permissions

Because `ProjectUser` uses `HasPermissions`, permission checks are simple:

```php
if ($user->can('documents.view')) {
    echo 'User may view documents.';
}

if ($user->can('documents.approve')) {
    echo 'Show Approve button.';
}
```

The project does not call the Permissions repository directly.

## 7. Set a direct user permission

An administrator can grant a direct override:

```php
$permissions = $services['permissions'];

$permissions->set(
    subjectType: 'user',
    subjectId: $user->id,
    permission: 'documents.approve',
    value: true,
);
```

Now:

```php
$user->can('documents.approve'); // true
```

Stored JSON is lightweight:

```json
{
    "documents.approve": true
}
```

## 8. Explicit deny

```php
$permissions->set(
    'user',
    $user->id,
    'documents.delete',
    false,
);
```

Then:

```php
$user->can('documents.delete'); // false
```

## 9. Return to inherited/default behavior

Clearing a user override removes the permission ID from that user's JSON record:

```php
$permissions->clear(
    'user',
    $user->id,
    'documents.approve',
);
```

The resolver then continues to group permissions and finally the template default.

## 10. Inspect why a permission was resolved

```php
$result = $user->permission('documents.approve');

echo $result->allowed ? 'Allowed' : 'Denied';
echo $result->source;
```

Possible sources include:

```text
user
Group
default
unknown
```

## 11. Create/update users through Users prefab

```php
$newUser = $users->create([
    'name' => 'Juan Dela Cruz',
    'email' => 'juan@example.com',
    'active' => true,
    'office' => 'Provincial Budget Office',
    'position' => 'Budget Officer',
]);
```

Update:

```php
$user = $users->update($newUser->id, [
    'position' => 'Senior Budget Officer',
]);
```

The `UserMap` converts these logical names back into project-specific DB columns.

## 12. Why the modules remain independent

Users knows nothing about Permissions:

```text
Prefab Users
    -> user retrieval
    -> mapping
    -> CRUD
    -> user object
```

Permissions knows nothing about the Users implementation:

```text
Prefab Permissions
    -> subject ID
    -> permission definitions
    -> overrides
    -> resolution
```

The project connects them here:

```php
class ProjectUser extends PrefabUser implements PermissionSubjectInterface
{
    use HasPermissions;
}
```

That means another project may instead use its own completely unrelated user class:

```php
class Employee implements PermissionSubjectInterface
{
    use HasPermissions;
}
```

without installing Prefab Users at all.

## 13. Group integration

The Permissions prefab already supports resolving group IDs supplied through:

```php
public function permissionGroupIds(): array
```

and the permission schema includes group-related tables. A dedicated `GroupManager` and membership repository/API are not yet implemented in the current prototype.

When added, the expected project usage will remain simple:

```php
$user->can('documents.approve');
```

The user object will supply its resolved group IDs to `PermissionManager`; callers will not need to manually pass groups into every permission check.

## Resulting dependency flow

```text
employees table
      │
      ▼
Prefab Users
      │
      ▼
ProjectUser
      │
      │ $user->can(...)
      ▼
Prefab Permissions
      │
      ├── user override
      ├── group override
      └── template default
      │
      ▼
 allow / deny
```

This keeps both Prefabs reusable independently while giving projects a unified developer experience.