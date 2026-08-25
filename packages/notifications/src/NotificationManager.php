<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Notifications;

use Tihloh\Prefab\Notifications\Contracts\NotificationStoreInterface;
use Tihloh\Prefab\Notifications\Stores\InMemoryNotificationStore;

final class NotificationManager
{
    public function __construct(
        private readonly NotificationStoreInterface $store = new InMemoryNotificationStore(),
    ) {}

    /** @param array<string, mixed> $metadata */
    public function send(
        string|int $recipientId,
        string $title,
        string $message,
        array $metadata = [],
        ?string $actionUrl = null,
    ): Notification {
        return $this->store->create(new Notification(
            null,
            $recipientId,
            $title,
            $message,
            $metadata,
            $actionUrl,
            time(),
            null,
        ));
    }

    /** @return list<Notification> */
    public function recent(string|int $recipientId, int $limit = 20): array
    {
        return $this->store->recent($recipientId, $limit, false);
    }

    /** @return list<Notification> */
    public function unread(string|int $recipientId, int $limit = 20): array
    {
        return $this->store->recent($recipientId, $limit, true);
    }

    public function unreadCount(string|int $recipientId): int
    {
        return $this->store->unreadCount($recipientId);
    }

    public function markRead(string|int $notificationId, ?int $readAt = null): bool
    {
        return $this->store->markRead($notificationId, $readAt);
    }

    public function markUnread(string|int $notificationId): bool
    {
        return $this->store->markUnread($notificationId);
    }

    public function delete(string|int $notificationId): bool
    {
        return $this->store->delete($notificationId);
    }
}
