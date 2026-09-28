<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Background;

final class BackgroundReceipt
{
    public function __construct(
        public readonly string $id,
        public readonly string $occurredAt,
        public readonly string $acceptedAt,
        public readonly string $requestId,
        public readonly int $sequence,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurredAt,
            'accepted_at' => $this->acceptedAt,
            'request_id' => $this->requestId,
            'sequence' => $this->sequence,
        ];
    }
}
