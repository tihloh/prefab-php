<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

use Tihloh\Prefab\PrefabRuntime;

final class DeliveryResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly bool $successful,
        public readonly string $channel,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {
        PrefabRuntime::traceStart('messaging', 'send', [
            'channel' => $channel,
        ]);
        PrefabRuntime::traceEnd([
            'successful' => $successful,
            'message_id' => $messageId,
            'error' => $error,
        ]);
    }

    public function failed(): bool
    {
        return !$this->successful;
    }
}
