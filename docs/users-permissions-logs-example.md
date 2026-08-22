# Users + Permissions + Logs Example

This example shows three independent Prefabs sharing one project database and one project user object.

```text
Project
├── Prefab Users       -> loads/manages users and constructs user CRUD log payloads
├── Prefab Permissions -> resolves authorization and constructs permission-change log payloads
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

## Mutations return data + a ready log payload

Reads still return normal user/permission objects. Mutations return an `OperationResult` containing:

```text
$result->data   actual operation result
$result->log    normalized structured log payload
```

The module constructs the meaning of the log. The host application decides whether to record it.

### Update a user

```php
$result = $users->update(
    25,
    ['office' => 'Budget'],
    context: [
        'actor_type' => 'user',
        'actor_id' => $currentUser->id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ],
);

$updatedUser = $result->data;
$logs->record($result->log);
```

The Users prefab automatically compares before/after values and may produce:

```php
[
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_type' => 'user',
    'actor_id' => 7,
    'message' => 'User Juan Dela Cruz was updated.',
    'changes' => [
        'office' => [
            'old' => 'Accounting',
            'new' => 'Budget',
        ],
    ],
    'metadata' => [],
    'ip_address' => '192.168.1.10',
    'user_agent' => '...',
]
```

### Create and delete users

```php
$created = $users->create($data, ['actor_id' => $currentUser->id]);
$logs->record($created->log);

$deleted = $users->delete(25, ['actor_id' => $currentUser->id]);
$logs->record($deleted->log);
```

## Permission changes work the same way

```php
$result = $permissions->set(
    'user',
    25,
    'documents.approve',
    true,
    context: ['actor_id' => $currentUser->id],
);

$logs->record($result->log);
```

The Permissions prefab automatically records the old/new override value:

```php
[
    'action' => 'permission.granted',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_id' => 7,
    'message' => 'Permission documents.approve was granted to user 25.',
    'changes' => [
        'documents.approve' => [
            'old' => null,
            'new' => true,
        ],
    ],
    'metadata' => [
        'permission' => 'documents.approve',
    ],
]
```

Clearing an override is also log-ready:

```php
$result = $permissions->clear(
    'user',
    25,
    'documents.approve',
    context: ['actor_id' => $currentUser->id],
);

$logs->record($result->log);
```

## Logging is still optional

A project that does not install Prefab Logs can simply ignore the payload:

```php
$result = $users->update(25, ['office' => 'Budget']);
$user = $result->data;
```

No dependency exists from Users or Permissions to Logs.

## Read activity history

```php
$userHistory = $logs->forSubject('user', 25);
$actorHistory = $logs->forActor($currentUser->id);
$recent = $logs->recent(50);
```

The modules remain independent: Users does not require Permissions or Logs; Permissions does not require Users or Logs; Logs does not require either. The host project composes them together.
