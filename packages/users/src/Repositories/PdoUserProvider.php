<?php

namespace Tihloh\Prefab\Users\Repositories;

use PDO;
use RuntimeException;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\User\PrefabUser;

final class PdoUserProvider implements UserProviderInterface
{
    public function __construct(
        private PDO $pdo,
        private UserMap $map,
    ) {
        $this->assertIdentifier($map->table);
        foreach ($map->coreColumns() as $column) {
            $this->assertIdentifier($column);
        }
        foreach ($map->attributes as $column) {
            $this->assertIdentifier($column);
        }
    }

    public function find(int|string $id): ?PrefabUser
    {
        $stmt = $this->pdo->prepare(
            sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', $this->map->table, $this->map->id)
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?PrefabUser
    {
        if ($this->map->email === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            sprintf('SELECT * FROM %s WHERE %s = :email LIMIT 1', $this->map->table, $this->map->email)
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function all(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 1000));
        $offset = max(0, $offset);
        $sql = sprintf('SELECT * FROM %s ORDER BY %s LIMIT %d OFFSET %d', $this->map->table, $this->map->id, $limit, $offset);
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => $this->hydrate($row), $rows);
    }

    public function create(array $data): PrefabUser
    {
        if (!$this->map->allowCreate) {
            throw new RuntimeException('Creating users is disabled for this user mapping.');
        }

        $mapped = $this->mapInput($data, false);
        if ($mapped === []) {
            throw new RuntimeException('No writable user fields were provided.');
        }

        $columns = array_keys($mapped);
        $placeholders = array_map(fn ($column) => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->map->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->pdo->prepare($sql)->execute($mapped);
        $id = $data['id'] ?? $this->pdo->lastInsertId();

        return $this->find($id) ?? throw new RuntimeException('User was created but could not be reloaded.');
    }

    public function update(int|string $id, array $data): PrefabUser
    {
        if (!$this->map->allowUpdate) {
            throw new RuntimeException('Updating users is disabled for this user mapping.');
        }

        $mapped = $this->mapInput($data, true);
        if ($mapped !== []) {
            $sets = array_map(fn ($column) => $column . ' = :' . $column, array_keys($mapped));
            $mapped['_prefab_id'] = $id;
            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s = :_prefab_id',
                $this->map->table,
                implode(', ', $sets),
                $this->map->id
            );
            $this->pdo->prepare($sql)->execute($mapped);
        }

        return $this->find($id) ?? throw new RuntimeException('User not found after update.');
    }

    public function delete(int|string $id): bool
    {
        if (!$this->map->allowDelete) {
            throw new RuntimeException('Deleting users is disabled for this user mapping.');
        }

        $stmt = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE %s = :id', $this->map->table, $this->map->id)
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): PrefabUser
    {
        $attributes = [];
        foreach ($this->map->attributes as $alias => $column) {
            $key = is_string($alias) ? $alias : $column;
            $attributes[$key] = $row[$column] ?? null;
        }

        return new PrefabUser(
            id: $row[$this->map->id],
            name: $this->map->name ? ($row[$this->map->name] ?? null) : null,
            email: $this->map->email ? ($row[$this->map->email] ?? null) : null,
            active: $this->map->active ? (bool) ($row[$this->map->active] ?? false) : true,
            attributes: $attributes,
        );
    }

    private function mapInput(array $data, bool $updating): array
    {
        $result = [];
        $fields = $this->map->coreColumns();

        foreach ($fields as $logical => $column) {
            if ($logical === 'id' && $updating) {
                continue;
            }
            if (array_key_exists($logical, $data)) {
                $result[$column] = $data[$logical];
            }
        }

        foreach ($this->map->attributes as $alias => $column) {
            $logical = is_string($alias) ? $alias : $column;
            if (array_key_exists($logical, $data)) {
                $result[$column] = $data[$logical];
            }
        }

        return $result;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException("Unsafe SQL identifier: {$identifier}");
        }
    }
}
