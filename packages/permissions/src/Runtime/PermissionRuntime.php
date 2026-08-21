<?php

namespace Tihloh\Prefab\Permissions\Runtime;

use LogicException;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

final class PermissionRuntime
{
    private static ?PermissionManager $manager = null;

    public static function use(PermissionManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function manager(): PermissionManager
    {
        return self::$manager ?? throw new LogicException('Permission manager has not been configured.');
    }
}
