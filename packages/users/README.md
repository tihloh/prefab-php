# Tihloh Prefab Users

Framework-independent user abstraction and mapping for existing PHP projects.

## Goals

- Project keeps ownership of its own `users`, `employees`, or `accounts` table.
- Prefab maps that structure into one reusable `PrefabUser` object.
- Extra project fields remain available as dynamic attributes.
- Projects may return their own `PrefabUser` subclass through `UserFactoryInterface`.
- No Laravel dependency.
- HTTP controllers and framework integrations sit on top of the same `UserManager`.

## Basic usage

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
    new PdoUserProvider($pdo, $map)
);

$user = $users->find(25);

echo $user->name;
echo $user->office;
echo $user->position;
```

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

Then implement `UserFactoryInterface` and pass the factory to `PdoUserProvider`. This keeps the provider/database mapping reusable while allowing project-specific user behavior.

## HTTP

`Http\UserController` is transport-neutral and returns arrays suitable for JSON serialization. Routers may map it to endpoints such as:

- `GET /api/v1/users`
- `GET /api/v1/users/{id}`
- `POST /api/v1/users`
- `PUT /api/v1/users/{id}`
- `DELETE /api/v1/users/{id}`

The HTTP layer uses the same `UserManager` as direct Composer usage.
