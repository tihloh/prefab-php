<?php

namespace Tihloh\Prefab\Permissions\DTOs;

final readonly class PermissionResult
{
    public function __construct(
        public bool $allowed,
        public string $source,
        public array $groups = [],
        public array $deniedGroups = [],
    ) {}
}
