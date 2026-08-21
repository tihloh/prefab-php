<?php

namespace Tihloh\Prefab\Permissions\Repositories;

use PDO;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;

final class PdoPermissionStore implements PermissionStoreInterface
{
    public function __construct(
        private PDO $pdo,
        private string $table = 'prefab_subject_permissions',
    ) {}

    public function get(string $subjectType, int|string $subjectId): array
    {
        $sql = "SELECT permissions FROM {$this->table} WHERE subject_type = :type AND subject_id = :id LIMIT 1";
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
        $sql = "INSERT INTO {$this->table} (subject_type, subject_id, permissions, created_at, updated_at)
                VALUES (:type, :id, :permissions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE permissions = VALUES(permissions), updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'type' => $subjectType,
            'id' => (string) $subjectId,
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);
    }

    public function remove(string $subjectType, int|string $subjectId): void
    {
        $sql = "DELETE FROM {$this->table} WHERE subject_type = :type AND subject_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['type' => $subjectType, 'id' => (string) $subjectId]);
    }
}
