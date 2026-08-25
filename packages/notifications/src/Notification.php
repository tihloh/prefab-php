<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Notifications;

final class Notification
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string|int|null $id,
        public readonly string|int $recipientId,
        public readonly string $title,
        public readonly string $message,
        public readonly array $metadata = [],
        public readonly ?string $actionUrl = null,
        public readonly ?int $createdAt = null,
        public readonly ?int $readAt = null,
    ) {}

    public function isRead(): bool { return $this->readAt !== null; }
    public function isUnread(): bool { return $this->readAt === null; }
}
