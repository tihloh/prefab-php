<?php

namespace Tihloh\Prefab\Permissions\Contracts;

interface PermissionStoreInterface
{
    public function get(string $subjectType, int|string $subjectId): array;
    public function put(string $subjectType, int|string $subjectId, array $permissions): void;
    public function remove(string $subjectType, int|string $subjectId): void;
}
