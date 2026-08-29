<?php

namespace Tihloh\Prefab\Permissions\DTOs;

use Tihloh\Prefab\PrefabRuntime;

final class PermissionResult
{
    public function __construct(
        public bool $allowed,
        public string $source,
        public array $groups = [],
        public array $deniedGroups = [],
    ) {
        PrefabRuntime::traceStart('permissions', 'check');
        PrefabRuntime::traceEnd([
            'allowed' => $allowed,
            'source' => $source,
            'groups' => count($groups),
            'denied_groups' => count($deniedGroups),
        ]);
    }
}
