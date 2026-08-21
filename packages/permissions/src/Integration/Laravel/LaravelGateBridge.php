<?php

namespace Tihloh\Prefab\Permissions\Integration\Laravel;

use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

final class LaravelGateBridge
{
    public static function register(object $gate, PermissionManager $permissions): void
    {
        $gate->before(function ($user, string $ability) use ($permissions) {
            if (!$permissions->defined($ability)) {
                return null;
            }

            if (!$user instanceof PermissionSubjectInterface) {
                return false;
            }

            return $permissions->can($user, $ability);
        });
    }
}
