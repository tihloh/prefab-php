<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Notifications\Stores;

use Tihloh\Prefab\Notifications\Contracts\NotificationStoreInterface;
use Tihloh\Prefab\Notifications\Notification;

final class InMemoryNotificationStore implements NotificationStoreInterface
{
    /** @var array<string, Notification> */
    private array $items = [];
    private int $nextId = 1;

    public function create(Notification $notification): Notification
    {
        $id = $notification->id ?? $this->nextId++;
        $stored = new Notification(
            $id,
            $notification->recipientId,
            $notification->title,
            $notification->message,
            $notification->metadata,
            $notification->actionUrl,
            $notification->createdAt ?? time(),
            $notification->readAt,
        );
        $this->items[(string) $id] = $stored;
        return $stored;
    }

    public function recent(string|int $recipientId, int $limit = 20, bool $unreadOnly = false): array
    {
        $items = array_values(array_filter($this->items, fn (Notification $n) =>
            (string) $n->recipientId === (string) $recipientId && (!$unreadOnly || $n->isUnread())
        ));
        usort($items, fn (Notification $a, Notification $b) => ($b->createdAt ?? 0) <=> ($a->createdAt ?? 0));
        return array_slice($items, 0, max(0, $limit));
    }

    public function unreadCount(string|int $recipientId): int
    {
        return count($this->recent($recipientId, PHP_INT_MAX, true));
    }

    public function markRead(string|int $notificationId, ?int $readAt = null): bool
    {
        return $this->replaceReadState($notificationId, $readAt ?? time());
    }

    public function markUnread(string|int $notificationId): bool
    {
        return $this->replaceReadState($notificationId, null);
    }

    public function delete(string|int $notificationId): bool
    {
        $key = (string) $notificationId;
        if (!isset($this->items[$key])) return false;
        unset($this->items[$key]);
        return true;
    }

    private function replaceReadState(string|int $id, ?int $readAt): bool
    {
        $key = (string) $id;
        $n = $this->items[$key] ?? null;
        if (!$n) return false;
        $this->items[$key] = new Notification($n->id, $n->recipientId, $n->title, $n->message, $n->metadata, $n->actionUrl, $n->createdAt, $readAt);
        return true;
    }
}
