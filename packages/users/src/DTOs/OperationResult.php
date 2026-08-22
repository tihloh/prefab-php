<?php

namespace Tihloh\Prefab\Users\DTOs;

final readonly class OperationResult
{
    public function __construct(
        public mixed $data,
        public array $log,
    ) {}
}
