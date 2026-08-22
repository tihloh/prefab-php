<?php

namespace Tihloh\Prefab\Logs\Repositories;

use PDO;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;

final class PdoLogRepository implements LogRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private string $table = 'prefab_logs',
    ) {}

    public function record(LogEntry $entry): int|string
    {
        $sql = "INSERT INTO {$this->table}
            (action, subject_type, subject_id, actor_id, message, changes, metadata, ip_address, user_agent, occurred_at, created_at)
            VALUES
            (:action, :subject_type, :subject_id, :actor_id, :message, :changes, :metadata, :ip_address, :user_agent, :occurred_at, CURRENT_TIMESTAMP)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId !== null ? (string) $entry->subjectId : null,
            'actor_id' => $entry->actorId !== null ? (string) $entry->actorId : null,
            'message' => $entry->message,
            'changes' => json_encode($entry->changes, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($entry->metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $entry->ipAddress,
            'user_agent' => $entry->userAgent,
            'occurred_at' => $entry->occurredAt,
        ]);

        return $this->pdo->lastInsertId();
    }

    public function find(int|string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decode($row) : null;
    }

    public function recent(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 1000));
        $offset = max(0, $offset);
        $rows = $this->pdo->query(
            sprintf('SELECT * FROM %s ORDER BY id DESC LIMIT %d OFFSET %d', $this->table, $limit, $offset)
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => $this->decode($row), $rows);
    }

    public function forSubject(string $subjectType, int|string $subjectId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $stmt = $this->pdo->prepare(
            sprintf('SELECT * FROM %s WHERE subject_type = :type AND subject_id = :id ORDER BY id DESC LIMIT %d', $this->table, $limit)
        );
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);

        return array_map(fn (array $row) => $this->decode($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function forActor(int|string $actorId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $stmt = $this->pdo->prepare(
            sprintf('SELECT * FROM %s WHERE actor_id = :actor_id ORDER BY id DESC LIMIT %d', $this->table, $limit)
        );
        $stmt->execute(['actor_id' => (string) $actorId]);

        return array_map(fn (array $row) => $this->decode($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function decode(array $row): array
    {
        $row['changes'] = $this->decodeJson($row['changes'] ?? null);
        $row['metadata'] = $this->decodeJson($row['metadata'] ?? null);
        return $row;
    }

    private function decodeJson(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
