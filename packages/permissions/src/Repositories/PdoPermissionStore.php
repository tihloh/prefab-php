<?php

namespace Tihloh\Prefab\Permissions\Repositories;

use PDO;
use RuntimeException;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;

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
        $sql = "SELECT permissions FROM {$this->table} WHERE subject_type = :type AND subject_id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);
        $json = $stmt->fetchColumn();

        if ($json === false || $json === null || $json === '') return [];

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

        if ($this->driver() === 'sqlite') {
            $sql = "INSERT INTO {$this->table} (subject_type, subject_id, permissions, created_at, updated_at)
                    VALUES (:type, :id, :permissions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON CONFLICT(subject_type, subject_id)
                    DO UPDATE SET permissions = excluded.permissions, updated_at = CURRENT_TIMESTAMP";
        } else {
            $sql = "INSERT INTO {$this->table} (subject_type, subject_id, permissions, created_at, updated_at)
                    VALUES (:type, :id, :permissions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE permissions = VALUES(permissions), updated_at = CURRENT_TIMESTAMP";
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    public function remove(string $subjectType, int|string $subjectId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE subject_type = :type AND subject_id = :id");
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);
    }

    private function ensureSchema(): void
    {
        if ($this->driver() === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subject_type TEXT NOT NULL,
                subject_id TEXT NOT NULL,
                permissions TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(subject_type, subject_id)
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                subject_type VARCHAR(64) NOT NULL,
                subject_id VARCHAR(191) NOT NULL,
                permissions JSON NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_prefab_subject_permissions (subject_type, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        $this->pdo->exec($sql);
    }

    private function driver(): string
    {
        return strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException("Unsafe SQL identifier: {$identifier}");
        }
    }
}
