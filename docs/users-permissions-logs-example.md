# Users + Permissions + Logs Example

This example shows three independent Prefabs sharing one project database and one project user object.

```text
Project
├── Prefab Users       -> loads/manages users
├── Prefab Permissions -> resolves authorization
└── Prefab Logs        -> stores structured activity/audit logs
```

## Shared database

All three may receive the same PDO connection:

```php
$pdo = new PDO($dsn, $username, $password);
```

The project may own an existing table such as `employees`, while Prefabs own only their tables:

```text
employees                    project-owned
prefab_subject_permissions   Permissions-owned
prefab_groups                Permissions-owned
prefab_user_groups           Permissions-owned
prefab_logs                  Logs-owned
```

## Bootstrap

```php
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Permissions\Repositories\PdoPermissionStore;
use Tihloh\Prefab\Permissions\Runtime\PermissionRuntime;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Logs\Repositories\PdoLogRepository;
use Tihloh\Prefab\Logs\Services\LogManager;

$userMap = new UserMap(
    table: 'employees',
    id: 'employee_id',
    name: 'full_name',
    email: 'email',
    active: 'active',
    attributes: [
        'office' => 'office_name',
        'position' => 'position_title',
    ],
);

$users = new UserManager(new PdoUserProvider($pdo, $userMap));

$permissions = new PermissionManager(
    PermissionDefinitions::fromFile(__DIR__ . '/permissions.json'),
    new PdoPermissionStore($pdo),
);
PermissionRuntime::use($permissions);

$logs = new LogManager(new PdoLogRepository($pdo));
```

## Use the same user object

A project-specific user may extend the Users prefab object and implement the Permissions subject contract.

```php
class ProjectUser extends PrefabUser implements PermissionSubjectInterface
{
    use HasPermissions;

    public function permissionSubjectId(): int|string
    {
        return $this->id;
    }

    public function permissionGroupIds(): array
    {
        return $this->groupIds ?? [];
    }
}
```

Then project code stays simple:

```php
$user = $users->find(25);

if ($user->can('documents.approve')) {
    // allow action
}
```

## Update and log

The Prefab that owns the operation should construct the meaning of the log entry. The project decides whether and where to store it.

```php
$before = $users->find(25);
$after = $users->update(25, ['office' => 'Budget']);

$logEntry = [
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => $after->id,
    'actor_id' => $currentUser->id,
    'message' => "User {$after->name} was updated.",
    'changes' => [
        'office' => [
            'old' => $before?->office,
            'new' => $after->office,
        ],
    ],
];

$logs->record($logEntry);
```

## Change permission and log

```php
$permissions->set('user', 25, 'documents.approve', true);

$logs->record([
    'action' => 'permission.granted',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_id' => $currentUser->id,
    'message' => 'documents.approve granted to user 25.',
    'metadata' => [
        'permission' => 'documents.approve',
        'value' => true,
    ],
]);
```

## Read activity history

```php
$userHistory = $logs->forSubject('user', 25);
$actorHistory = $logs->forActor($currentUser->id);
$recent = $logs->recent(50);
```

The modules remain independent: Users does not require Permissions or Logs; Permissions does not require Users or Logs; Logs does not require either. The host project composes them together.
