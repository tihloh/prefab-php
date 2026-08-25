<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Notifications\Contracts;

use Tihloh\Prefab\Notifications\Notification;

interface NotificationStoreInterface
{
    public function create(Notification $notification): Notification;

    /** @return list<Notification> */
    public function recent(string|int $recipientId, int $limit = 20, bool $unreadOnly = false): array;

    public function unreadCount(string|int $recipientId): int;

    public function markRead(string|int $notificationId, ?int $readAt = null): bool;

    public function markUnread(string|int $notificationId): bool;

    public function delete(string|int $notificationId): bool;
}
