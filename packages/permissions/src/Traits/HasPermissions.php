<?php

namespace Tihloh\Prefab\Permissions\Traits;

use Tihloh\Prefab\Permissions\DTOs\PermissionResult;
use Tihloh\Prefab\Permissions\Runtime\PermissionRuntime;

trait HasPermissions
{
    public function can(string $permission): bool
    {
        return PermissionRuntime::manager()->can($this, $permission);
    }

    public function permission(string $permission): PermissionResult
    {
        return PermissionRuntime::manager()->resolve($this, $permission);
    }
}
