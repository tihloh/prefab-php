# Tihloh Prefab Permissions

Framework-independent permissions and authorization for PHP, with optional Laravel integration.

## Custom PHP

```php
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Repositories\PdoPermissionStore;
use Tihloh\Prefab\Permissions\Runtime\PermissionRuntime;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Permissions\Traits\HasPermissions;

$definitions = PermissionDefinitions::fromJsonFile(__DIR__.'/config/permissions.json');
$manager = new PermissionManager($definitions, new PdoPermissionStore($pdo));
PermissionRuntime::use($manager);

class User implements PermissionSubjectInterface
{
    use HasPermissions;

    public function __construct(public int $id, public array $groupIds = []) {}

    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return $this->groupIds; }
}

if ($user->can('documents.approve')) {
    // allowed
}
```

## Laravel

Keep Laravel's native `can()` method. Make the user model implement `PermissionSubjectInterface`, then register `LaravelGateBridge` with the same `PermissionManager`. Defined Prefab permissions are resolved by Prefab; unknown abilities return control to Laravel's normal Gate/Policy system.

```php
class User extends Authenticatable implements PermissionSubjectInterface
{
    public function permissionSubjectId(): int|string { return $this->getKey(); }
    public function permissionGroupIds(): array { return $this->groups()->pluck('groups.id')->all(); }
}
```

Then normal Laravel syntax remains available:

```php
$user->can('documents.approve');
```

## Resolution

1. Direct user override
2. Group overrides (current policy: any allow wins)
3. JSON template default

Stored override values are only permission ID => boolean. Missing keys mean inherit.
