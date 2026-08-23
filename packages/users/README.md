# Tihloh Prefab Users

Framework-independent user abstraction and mapping for existing PHP projects.

Prefab Users is standalone. It does not require Prefab Database, Auth, Permissions, Logs, Laravel, or another framework package.

## Goals

- Project keeps ownership of its own `users`, `employees`, or `accounts` table.
- Prefab maps that structure into one reusable `PrefabUser` object.
- Extra project fields remain available as dynamic attributes.
- Projects may return their own `PrefabUser` subclass through `UserFactoryInterface`.
- Database-backed usage accepts plain PDO or Prefab's `DatabaseInterface`.
- Compatible Prefab modules may integrate automatically without becoming dependencies.

## Quick standalone usage

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

The historical `PdoUserProvider` class name is retained for compatibility, but the provider now consumes `DatabaseInterface` internally. Passing PDO is automatically adapted.

## Automatic database configuration

A project may configure Users directly:

```php
$users = new UserManager([
    'database' => $pdo,
    'map' => $map,
]);
```

or centrally:

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

When Prefab Database exists, Users can inherit its default or a named connection automatically:

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
    ],
]);

$users = new UserManager();
```

The database resolution priority is:

```text
1. direct Users database / connection
2. Users-specific PrefabConfig
3. common PrefabConfig
4. compatible database capability
5. clear error if a database-backed provider is still unresolved
```

## Database abstraction

Prefab Users does not require a concrete database package. Its built-in database provider accepts either:

```text
PDO
DatabaseInterface
```

Plain PDO is normalized automatically:

```text
PDO
 ↓
PdoDatabaseAdapter
 ↓
DatabaseInterface
 ↓
PdoUserProvider
```

This allows future framework adapters to supply the same contract without changing UserManager.

## CRUD

```php
$users->all();
$users->find(25);
$users->findByEmail('user@example.com');
$users->create([...]);
$users->update(25, [...]);
$users->delete(25);
```

Write operations are controlled by `UserMap` capabilities: `allowCreate`, `allowUpdate`, and `allowDelete`.

## Custom user class

Extend `PrefabUser`:

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

Then implement `UserFactoryInterface` and pass the factory to the database provider. This keeps the project-owned schema reusable while allowing project-specific user behavior.

## Automatic cooperation

When compatible modules are present, Users may automatically:

- provide `user_provider` for Prefab Auth;
- publish its resolved database as a low-priority fallback capability;
- use Prefab Logs for activity recording;
- use Prefab Auth as the current actor provider.

None of those modules are required.

Use:

```php
$users->explain();
```

to inspect how Users resolved its provider, database, table, logger, and actor integrations.

## HTTP

`Http\UserController` is transport-neutral and returns arrays suitable for JSON serialization. Routers may map it to endpoints such as:

- `GET /api/v1/users`
- `GET /api/v1/users/{id}`
- `POST /api/v1/users`
- `PUT /api/v1/users/{id}`
- `DELETE /api/v1/users/{id}`

The HTTP layer uses the same `UserManager` as direct Composer usage.
