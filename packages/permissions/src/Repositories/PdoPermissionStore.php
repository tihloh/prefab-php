<?php

namespace Tihloh\Prefab\Permissions\Repositories;

use PDO;
use RuntimeException;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;

/**
 * PDO-backed permission storage with driver-specific SQL kept inside the
 * repository. Prefab Permissions therefore remains standalone and does not
 * require Prefab Database merely to support multiple PDO database engines.
 *
 * First-class drivers: MySQL/MariaDB, PostgreSQL, SQLite and SQL Server.
 */
final class PdoPermissionStore implements PermissionStoreInterface
{
    public function __construct(
        private PDO $pdo,
        private string $table = 'prefab_subject_permissions',
    ) {
        $this->assertIdentifier($this->table);
        $this->ensureSchema();
    }

    public function get(string $subjectType, int|string $subjectId): array
    {
        $sql = "SELECT permissions FROM {$this->table} WHERE subject_type = :type AND subject_id = :id";

        if ($this->driver() !== 'sqlsrv') {
            $sql .= ' LIMIT 1';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);
        $json = $stmt->fetchColumn();

        if ($json === false || $json === null || $json === '') {
            return [];
        }

        $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    public function put(string $subjectType, int|string $subjectId, array $permissions): void
    {
        $params = [
            'type' => $subjectType,
            'id' => (string) $subjectId,
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ];

        $sql = match ($this->driver()) {
            'sqlite' => "INSERT INTO {$this->table} (subject_type, subject_id, permissions, created_at, updated_at)
                VALUES (:type, :id, :permissions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(subject_type, subject_id)
                DO UPDATE SET permissions = excluded.permissions, updated_at = CURRENT_TIMESTAMP",

            'pgsql' => "INSERT INTO {$this->table} (subject_type, subject_id, permissions, created_at, updated_at)
                VALUES (:type, :id, CAST(:permissions AS JSONB), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(subject_type, subject_id)
                DO UPDATE SET permissions = EXCLUDED.permissions, updated_at = CURRENT_TIMESTAMP",

            'sqlsrv' => "MERGE {$this->table} AS target
                USING (SELECT :type AS subject_type, :id AS subject_id, :permissions AS permissions) AS source
                ON target.subject_type = source.subject_type AND target.subject_id = source.subject_id
                WHEN MATCHED THEN
                    UPDATE SET permissions = source.permissions, updated_at = CURRENT_TIMESTAMP
                WHEN NOT MATCHED THEN
                    INSERT (subject_type, subject_id, permissions, created_at, updated_at)
                    VALUES (source.subject_type, source.subject_id, source.permissions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);",

            'mysql' => "INSERT INTO {$this->table} (subject_type, subject_id, permissions, created_at, updated_at)
                VALUES (:type, :id, :permissions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE permissions = VALUES(permissions), updated_at = CURRENT_TIMESTAMP",

            default => throw new RuntimeException(
                "Unsupported permission database driver '{$this->driver()}'.",
            ),
        };

        $this->pdo->prepare($sql)->execute($params);
    }

    public function remove(string $subjectType, int|string $subjectId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE subject_type = :type AND subject_id = :id",
        );
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);
    }

    private function ensureSchema(): void
    {
        $sql = match ($this->driver()) {
            'sqlite' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subject_type TEXT NOT NULL,
                subject_id TEXT NOT NULL,
                permissions TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(subject_type, subject_id)
            )",

            'pgsql' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGSERIAL PRIMARY KEY,
                subject_type VARCHAR(64) NOT NULL,
                subject_id VARCHAR(191) NOT NULL,
                permissions JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_prefab_subject_permissions UNIQUE (subject_type, subject_id)
            )",

            'sqlsrv' => "IF OBJECT_ID(N'{$this->table}', N'U') IS NULL
                CREATE TABLE {$this->table} (
                    id BIGINT IDENTITY(1,1) PRIMARY KEY,
                    subject_type NVARCHAR(64) NOT NULL,
                    subject_id NVARCHAR(191) NOT NULL,
                    permissions NVARCHAR(MAX) NOT NULL DEFAULT '{}',
                    created_at DATETIME2 NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME2 NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT uq_prefab_subject_permissions UNIQUE (subject_type, subject_id)
                )",

            'mysql' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                subject_type VARCHAR(64) NOT NULL,
                subject_id VARCHAR(191) NOT NULL,
                permissions JSON NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_prefab_subject_permissions (subject_type, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            default => throw new RuntimeException(
                "Unsupported permission database driver '{$this->driver()}'.",
            ),
        };

        $this->pdo->exec($sql);
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
