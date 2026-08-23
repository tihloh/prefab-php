<?php

namespace Tihloh\Prefab\Permissions\DTOs;

final class OperationResult
{
    public function __construct(
        public mixed $data,
        public array $log,
    ) {}
}
