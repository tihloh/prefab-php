<?php

namespace Tihloh\Prefab\Logs\Repositories;

use PDO;
use RuntimeException;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;

/**
 * Portable PDO audit-log repository.
 *
 * Schema differences are isolated here so Prefab Logs can remain standalone.
 * First-class drivers are MySQL/MariaDB, PostgreSQL, SQLite and SQL Server.
 */
final class PdoLogRepository implements LogRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private string $table = 'prefab_logs',
    ) {
        $this->assertIdentifier($this->table);
        $this->ensureSchema();
    }

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
        $sql = $this->driver() === 'sqlsrv'
            ? "SELECT TOP 1 * FROM {$this->table} WHERE id = :id"
            : "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decode($row) : null;
    }

    public function recent(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->pdo->query($this->pagedSql(null, $limit, $offset))
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => $this->decode($row), $rows);
    }

    public function forSubject(string $subjectType, int|string $subjectId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            $this->pagedSql('subject_type = :type AND subject_id = :id', $limit),
        );
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);

        return array_map(
            fn (array $row) => $this->decode($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function forActor(int|string $actorId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            $this->pagedSql('actor_id = :actor_id', $limit),
        );
        $stmt->execute(['actor_id' => (string) $actorId]);

        return array_map(
            fn (array $row) => $this->decode($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    private function pagedSql(?string $where, int $limit, int $offset = 0): string
    {
        $limit = max(1, min($limit, 1000));
        $offset = max(0, $offset);
        $whereSql = $where ? " WHERE {$where}" : '';

        if ($this->driver() === 'sqlsrv') {
            return "SELECT * FROM {$this->table}{$whereSql} ORDER BY id DESC OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
        }

        return "SELECT * FROM {$this->table}{$whereSql} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
    }

    private function ensureSchema(): void
    {
        $sql = match ($this->driver()) {
            'sqlite' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT NOT NULL,
                subject_type TEXT NOT NULL,
                subject_id TEXT NULL,
                actor_id TEXT NULL,
                message TEXT NULL,
                changes TEXT NOT NULL DEFAULT '{}',
                metadata TEXT NOT NULL DEFAULT '{}',
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                occurred_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",

            'pgsql' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGSERIAL PRIMARY KEY,
                action VARCHAR(191) NOT NULL,
                subject_type VARCHAR(64) NOT NULL,
                subject_id VARCHAR(191) NULL,
                actor_id VARCHAR(191) NULL,
                message TEXT NULL,
                changes JSONB NOT NULL DEFAULT '{}'::jsonb,
                metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
                ip_address VARCHAR(64) NULL,
                user_agent TEXT NULL,
                occurred_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",

            'sqlsrv' => "IF OBJECT_ID(N'{$this->table}', N'U') IS NULL
                CREATE TABLE {$this->table} (
                    id BIGINT IDENTITY(1,1) PRIMARY KEY,
                    action NVARCHAR(191) NOT NULL,
                    subject_type NVARCHAR(64) NOT NULL,
                    subject_id NVARCHAR(191) NULL,
                    actor_id NVARCHAR(191) NULL,
                    message NVARCHAR(MAX) NULL,
                    changes NVARCHAR(MAX) NOT NULL DEFAULT '{}',
                    metadata NVARCHAR(MAX) NOT NULL DEFAULT '{}',
                    ip_address NVARCHAR(64) NULL,
                    user_agent NVARCHAR(MAX) NULL,
                    occurred_at DATETIME2 NULL,
                    created_at DATETIME2 NOT NULL DEFAULT CURRENT_TIMESTAMP
                )",

            'mysql' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(191) NOT NULL,
                subject_type VARCHAR(64) NOT NULL,
                subject_id VARCHAR(191) NULL,
                actor_id VARCHAR(191) NULL,
                message TEXT NULL,
                changes JSON NOT NULL,
                metadata JSON NOT NULL,
                ip_address VARCHAR(64) NULL,
                user_agent TEXT NULL,
                occurred_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_prefab_logs_subject (subject_type, subject_id),
                KEY idx_prefab_logs_actor (actor_id),
                KEY idx_prefab_logs_action (action),
                KEY idx_prefab_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            default => throw new RuntimeException(
                "Unsupported log database driver '{$this->driver()}'.",
            ),
        };

        $this->pdo->exec($sql);
        $this->ensureIndexes();
    }

    private function ensureIndexes(): void
    {
        $driver = $this->driver();

        if ($driver === 'sqlite') {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->table}_subject ON {$this->table}(subject_type, subject_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->table}_actor ON {$this->table}(actor_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->table}_action ON {$this->table}(action)");
        } elseif ($driver === 'pgsql') {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->table}_subject ON {$this->table}(subject_type, subject_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->table}_actor ON {$this->table}(actor_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->table}_action ON {$this->table}(action)");
        }
        // MySQL indexes are declared with the table. SQL Server deployments may
        // add project-specific indexes independently to avoid duplicate-name DDL.
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

    private function driver(): string
    {
        $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        return $driver === 'dblib' ? 'sqlsrv' : $driver;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException("Unsafe SQL identifier: {$identifier}");
        }
    }
}
