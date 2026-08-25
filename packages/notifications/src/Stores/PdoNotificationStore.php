<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Notifications\Stores;

use PDO;
use Tihloh\Prefab\Notifications\Contracts\NotificationStoreInterface;
use Tihloh\Prefab\Notifications\Notification;

final class PdoNotificationStore implements NotificationStoreInterface
{
    /** @param array<string, string> $columns */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'notifications',
        private readonly array $columns = [],
    ) {}

    public function create(Notification $n): Notification
    {
        $c = $this->columns();
        $sql = sprintf(
            'INSERT INTO %s (%s,%s,%s,%s,%s,%s,%s) VALUES (:recipient,:title,:message,:metadata,:action_url,:created_at,:read_at)',
            $this->id($this->table), $this->id($c['recipient']), $this->id($c['title']), $this->id($c['message']),
            $this->id($c['metadata']), $this->id($c['action_url']), $this->id($c['created_at']), $this->id($c['read_at'])
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'recipient' => $n->recipientId,
            'title' => $n->title,
            'message' => $n->message,
            'metadata' => json_encode($n->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'action_url' => $n->actionUrl,
            'created_at' => $this->date($n->createdAt ?? time()),
            'read_at' => $n->readAt === null ? null : $this->date($n->readAt),
        ]);
        $id = $this->pdo->lastInsertId();
        return new Notification($id === '' ? null : $id, $n->recipientId, $n->title, $n->message, $n->metadata, $n->actionUrl, $n->createdAt ?? time(), $n->readAt);
    }

    public function recent(string|int $recipientId, int $limit = 20, bool $unreadOnly = false): array
    {
        $c = $this->columns();
        $sql = sprintf('SELECT * FROM %s WHERE %s = :recipient%s ORDER BY %s DESC LIMIT %d',
            $this->id($this->table), $this->id($c['recipient']),
            $unreadOnly ? ' AND ' . $this->id($c['read_at']) . ' IS NULL' : '',
            $this->id($c['created_at']), max(0, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['recipient' => $recipientId]);
        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function unreadCount(string|int $recipientId): int
    {
        $c = $this->columns();
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s = :recipient AND %s IS NULL', $this->id($this->table), $this->id($c['recipient']), $this->id($c['read_at']));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['recipient' => $recipientId]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(string|int $notificationId, ?int $readAt = null): bool
    {
        return $this->setRead($notificationId, $this->date($readAt ?? time()));
    }

    public function markUnread(string|int $notificationId): bool
    {
        return $this->setRead($notificationId, null);
    }

    public function delete(string|int $notificationId): bool
    {
        $c = $this->columns();
        $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s = :id', $this->id($this->table), $this->id($c['id'])));
        $stmt->execute(['id' => $notificationId]);
        return $stmt->rowCount() > 0;
    }

    private function setRead(string|int $id, ?string $value): bool
    {
        $c = $this->columns();
        $stmt = $this->pdo->prepare(sprintf('UPDATE %s SET %s = :read_at WHERE %s = :id', $this->id($this->table), $this->id($c['read_at']), $this->id($c['id'])));
        $stmt->execute(['read_at' => $value, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $r): Notification
    {
        $c = $this->columns();
        $metadata = json_decode((string) ($r[$c['metadata']] ?? '{}'), true);
        return new Notification(
            $r[$c['id']] ?? null, $r[$c['recipient']], (string) $r[$c['title']], (string) $r[$c['message']],
            is_array($metadata) ? $metadata : [], $r[$c['action_url']] ?? null,
            isset($r[$c['created_at']]) ? strtotime((string) $r[$c['created_at']]) ?: null : null,
            !empty($r[$c['read_at']]) ? strtotime((string) $r[$c['read_at']]) ?: null : null,
        );
    }

    /** @return array<string,string> */
    private function columns(): array
    {
        return array_merge([
            'id'=>'id','recipient'=>'recipient_id','title'=>'title','message'=>'message','metadata'=>'metadata',
            'action_url'=>'action_url','created_at'=>'created_at','read_at'=>'read_at'
        ], $this->columns);
    }

    private function id(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) throw new \InvalidArgumentException("Unsafe SQL identifier [{$value}].");
        return $value;
    }

    private function date(int $timestamp): string { return date('Y-m-d H:i:s', $timestamp); }
}
