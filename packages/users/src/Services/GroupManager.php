<?php

namespace Tihloh\Prefab\Users\Services;

use RuntimeException;
use Tihloh\Prefab\DatabaseInterface;
use Tihloh\Prefab\Users\DTOs\Group;

final class GroupManager
{
    public function __construct(private DatabaseInterface $database)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->database->driver() === 'sqlite') {
            $this->database->statement('CREATE TABLE IF NOT EXISTS prefab_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(191) NOT NULL UNIQUE, description VARCHAR(255) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
            $this->database->statement('CREATE TABLE IF NOT EXISTS prefab_user_groups (user_id VARCHAR(191) NOT NULL, group_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, group_id), FOREIGN KEY (group_id) REFERENCES prefab_groups(id) ON DELETE CASCADE)');
            return;
        }

        $this->database->statement('CREATE TABLE IF NOT EXISTS prefab_groups (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, description VARCHAR(255) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_prefab_group_name (name))');
        $this->database->statement('CREATE TABLE IF NOT EXISTS prefab_user_groups (user_id VARCHAR(191) NOT NULL, group_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, group_id), INDEX idx_prefab_user_groups_group (group_id), CONSTRAINT fk_prefab_user_groups_group FOREIGN KEY (group_id) REFERENCES prefab_groups(id) ON DELETE CASCADE)');
    }

    /** @return list<Group> */
    public function all(): array
    {
        $rows = $this->database->select('SELECT g.id, g.name, g.description, COUNT(ug.user_id) AS users_count FROM prefab_groups g LEFT JOIN prefab_user_groups ug ON ug.group_id = g.id GROUP BY g.id, g.name, g.description ORDER BY g.name');
        return array_map(fn(array $r) => new Group($r['id'], $r['name'], $r['description'] ?? null, (int)($r['users_count'] ?? 0)), $rows);
    }

    public function find(int|string $id): ?Group
    {
        $rows = $this->database->select('SELECT g.id, g.name, g.description, COUNT(ug.user_id) AS users_count FROM prefab_groups g LEFT JOIN prefab_user_groups ug ON ug.group_id = g.id WHERE g.id = :id GROUP BY g.id, g.name, g.description LIMIT 1', ['id' => $id]);
        $r = $rows[0] ?? null;
        return $r ? new Group($r['id'], $r['name'], $r['description'] ?? null, (int)($r['users_count'] ?? 0)) : null;
    }

    public function create(string $name, ?string $description = null): Group
    {
        $this->database->statement('INSERT INTO prefab_groups (name, description) VALUES (:name, :description)', ['name' => $name, 'description' => $description]);
        $id = $this->database->lastInsertId();
        return $this->find($id ?: throw new RuntimeException('Group ID unavailable.')) ?? throw new RuntimeException('Group could not be reloaded.');
    }

    public function update(int|string $id, array $data): Group
    {
        $group = $this->find($id) ?? throw new RuntimeException('Group not found.');
        $name = array_key_exists('name', $data) ? (string)$data['name'] : $group->name;
        $description = array_key_exists('description', $data) ? $data['description'] : $group->description;
        $this->database->statement('UPDATE prefab_groups SET name = :name, description = :description WHERE id = :id', ['name' => $name, 'description' => $description, 'id' => $id]);
        return $this->find($id) ?? throw new RuntimeException('Group not found after update.');
    }

    public function delete(int|string $id): bool
    {
        return $this->database->statement('DELETE FROM prefab_groups WHERE id = :id', ['id' => $id]);
    }

    /** @return list<string> */
    public function userIds(int|string $groupId): array
    {
        $rows = $this->database->select('SELECT user_id FROM prefab_user_groups WHERE group_id = :id ORDER BY user_id', ['id' => $groupId]);
        return array_map(fn(array $r) => (string)$r['user_id'], $rows);
    }

    /** @return list<string> */
    public function groupIdsForUser(int|string $userId): array
    {
        $rows = $this->database->select('SELECT group_id FROM prefab_user_groups WHERE user_id = :id ORDER BY group_id', ['id' => (string)$userId]);
        return array_map(fn(array $r) => (string)$r['group_id'], $rows);
    }

    /** @return list<string> */
    public function syncUserGroups(int|string $userId, array $groupIds): array
    {
        return $this->database->transaction(function (DatabaseInterface $db) use ($userId, $groupIds) {
            $db->statement('DELETE FROM prefab_user_groups WHERE user_id = :id', ['id' => (string)$userId]);
            foreach (array_unique($groupIds) as $groupId) {
                $db->statement('INSERT INTO prefab_user_groups (user_id, group_id) VALUES (:user_id, :group_id)', ['user_id' => (string)$userId, 'group_id' => $groupId]);
            }
            return $this->groupIdsForUser($userId);
        });
    }

    public function addUser(int|string $userId, int|string $groupId): void
    {
        if (in_array((string)$groupId, $this->groupIdsForUser($userId), true)) return;
        $this->database->statement('INSERT INTO prefab_user_groups (user_id, group_id) VALUES (:user_id, :group_id)', ['user_id' => (string)$userId, 'group_id' => $groupId]);
    }

    public function removeUser(int|string $userId, int|string $groupId): void
    {
        $this->database->statement('DELETE FROM prefab_user_groups WHERE user_id = :user_id AND group_id = :group_id', ['user_id' => (string)$userId, 'group_id' => $groupId]);
    }
}
