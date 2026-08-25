<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

final class DeliveryResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly bool $successful,
        public readonly string $channel,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    public function failed(): bool
    {
        return !$this->successful;
    }
}
