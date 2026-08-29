<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Notifications;

use Tihloh\Prefab\Notifications\Contracts\NotificationStoreInterface;
use Tihloh\Prefab\Notifications\Stores\InMemoryNotificationStore;
use Tihloh\Prefab\PrefabRuntime;

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
        return PrefabRuntime::traceCall('notifications', 'send', [
            'recipient_id' => $recipientId,
        ], fn () => $this->store->create(new Notification(
            null,
            $recipientId,
            $title,
            $message,
            $metadata,
            $actionUrl,
            time(),
            null,
        )));
    }

    /** @return list<Notification> */
    public function recent(string|int $recipientId, int $limit = 20): array
    {
        return PrefabRuntime::traceCall('notifications', 'recent', [
            'recipient_id' => $recipientId,
            'limit' => $limit,
        ], fn () => $this->store->recent($recipientId, $limit, false));
    }

    /** @return list<Notification> */
    public function unread(string|int $recipientId, int $limit = 20): array
    {
        return PrefabRuntime::traceCall('notifications', 'unread', [
            'recipient_id' => $recipientId,
            'limit' => $limit,
        ], fn () => $this->store->recent($recipientId, $limit, true));
    }

    public function unreadCount(string|int $recipientId): int
    {
        return PrefabRuntime::traceCall('notifications', 'unreadCount', [
            'recipient_id' => $recipientId,
        ], fn () => $this->store->unreadCount($recipientId));
    }

    public function markRead(string|int $notificationId, ?int $readAt = null): bool
    {
        return PrefabRuntime::traceCall('notifications', 'markRead', [
            'notification_id' => $notificationId,
        ], fn () => $this->store->markRead($notificationId, $readAt));
    }

    public function markUnread(string|int $notificationId): bool
    {
        return PrefabRuntime::traceCall('notifications', 'markUnread', [
            'notification_id' => $notificationId,
        ], fn () => $this->store->markUnread($notificationId));
    }

    public function delete(string|int $notificationId): bool
    {
        return PrefabRuntime::traceCall('notifications', 'delete', [
            'notification_id' => $notificationId,
        ], fn () => $this->store->delete($notificationId));
    }
}
