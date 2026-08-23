<?php

namespace Tihloh\Prefab\Users\Repositories;

use PDO;
use RuntimeException;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\User\DefaultUserFactory;
use Tihloh\Prefab\Users\User\PrefabUser;

/**
 * Maps a project-owned user table to Prefab Users using standard PDO.
 *
 * The repository intentionally uses a small portable SQL subset. Pagination is
 * adapted for SQL Server; MySQL/MariaDB, PostgreSQL and SQLite use LIMIT/OFFSET.
 * Prefab Database is optional and is not required by this repository.
 */
final class PdoUserProvider implements UserProviderInterface
{
    private UserFactoryInterface $factory;

    public function __construct(
        private PDO $pdo,
        private UserMap $map,
        ?UserFactoryInterface $factory = null,
    ) {
        $this->factory = $factory ?? new DefaultUserFactory();

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
        $sql = $this->driver() === 'sqlsrv'
            ? sprintf('SELECT TOP 1 * FROM %s WHERE %s = :id', $this->map->table, $this->map->id)
            : sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', $this->map->table, $this->map->id);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?PrefabUser
    {
        if ($this->map->email === null) {
            return null;
        }

        $sql = $this->driver() === 'sqlsrv'
            ? sprintf('SELECT TOP 1 * FROM %s WHERE %s = :email', $this->map->table, $this->map->email)
            : sprintf('SELECT * FROM %s WHERE %s = :email LIMIT 1', $this->map->table, $this->map->email);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function all(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 1000));
        $offset = max(0, $offset);

        $sql = $this->driver() === 'sqlsrv'
            ? sprintf(
                'SELECT * FROM %s ORDER BY %s OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                $this->map->table,
                $this->map->id,
                $offset,
                $limit,
            )
            : sprintf(
                'SELECT * FROM %s ORDER BY %s LIMIT %d OFFSET %d',
                $this->map->table,
                $this->map->id,
                $limit,
                $offset,
            );

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
        $placeholders = array_map(fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->map->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $this->pdo->prepare($sql)->execute($mapped);
        $id = $data['id'] ?? $this->pdo->lastInsertId();

        return $this->find($id)
            ?? throw new RuntimeException('User was created but could not be reloaded.');
    }

    public function update(int|string $id, array $data): PrefabUser
    {
        if (!$this->map->allowUpdate) {
            throw new RuntimeException('Updating users is disabled for this user mapping.');
        }

        $mapped = $this->mapInput($data, true);

        if ($mapped !== []) {
            $sets = array_map(
                fn (string $column): string => $column . ' = :' . $column,
                array_keys($mapped),
            );
            $mapped['_prefab_id'] = $id;
            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s = :_prefab_id',
                $this->map->table,
                implode(', ', $sets),
                $this->map->id,
            );
            $this->pdo->prepare($sql)->execute($mapped);
        }

        return $this->find($id)
            ?? throw new RuntimeException('User not found after update.');
    }

    public function delete(int|string $id): bool
    {
        if (!$this->map->allowDelete) {
            throw new RuntimeException('Deleting users is disabled for this user mapping.');
        }

        $stmt = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE %s = :id', $this->map->table, $this->map->id),
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

        return $this->factory->make(
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

        foreach ($this->map->coreColumns() as $logical => $column) {
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
