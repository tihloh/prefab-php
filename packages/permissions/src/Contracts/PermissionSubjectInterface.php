<?php

namespace Tihloh\Prefab\Permissions\Contracts;

interface PermissionSubjectInterface
{
    public function permissionSubjectId(): int|string;

    /** @return array<int|string> */
    public function permissionGroupIds(): array;
}
