<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Background;

final class BackgroundContext
{
    public function __construct(
        public readonly string $id,
        public readonly string $handler,
        public readonly string $occurredAt,
        public readonly string $acceptedAt,
        public readonly string $requestId,
        public readonly int $sequence,
        public readonly int $attempt,
    ) {
    }
}
