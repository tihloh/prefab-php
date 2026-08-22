<?php

namespace Tihloh\Prefab\Permissions\DTOs;

final readonly class OperationResult
{
    public function __construct(
        public mixed $data,
        public array $log,
    ) {}
}
