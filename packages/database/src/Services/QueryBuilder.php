<?php

namespace Tihloh\Prefab\Database\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Lightweight framework-independent query builder.
 *
 * This deliberately covers the common CRUD subset needed by rapid Prefab
 * applications rather than attempting to reproduce a full ORM. Values are
 * always parameterized; table/column identifiers are validated separately.
 */
final class QueryBuilder
{
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limitValue = null;
    private int $offsetValue = 0;

    public function __construct(
        private PDO $pdo,
        private string $table,
    ) {
        $this->assertIdentifier($table);
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $this->assertIdentifier($column);
        $clone = clone $this;
        $operator = func_num_args() === 2 ? '=' : strtoupper((string) $operatorOrValue);
        $actualValue = func_num_args() === 2 ? $operatorOrValue : $value;

        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE'], true)) {
            throw new InvalidArgumentException("Unsupported where operator '{$operator}'.");
        }

        $parameter = ':w' . count($clone->bindings);
        $clone->wheres[] = "{$column} {$operator} {$parameter}";
        $clone->bindings[$parameter] = $actualValue;

        return $clone;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->assertIdentifier($column);
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Order direction must be ASC or DESC.');
        }

        $clone = clone $this;
        $clone->orders[] = "{$column} {$direction}";

        return $clone;
    }

    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->limitValue = max(1, $limit);

        return $clone;
    }

    public function offset(int $offset): self
    {
        $clone = clone $this;
        $clone->offsetValue = max(0, $offset);

        return $clone;
    }

    public function get(): array
    {
        [$sql, $bindings] = $this->selectSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        $rows = $this->limit(1)->get();

        return $rows[0] ?? null;
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            throw new InvalidArgumentException('Insert values cannot be empty.');
        }

        $columns = array_keys($values);
        array_walk($columns, fn (string $column) => $this->assertIdentifier($column));
        $placeholders = array_map(fn (string $column): string => ':i_' . $column, $columns);
        $bindings = [];

        foreach ($values as $column => $value) {
            $bindings[':i_' . $column] = $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        return $this->pdo->prepare($sql)->execute($bindings);
    }

    public function insertGetId(array $values): int|string
    {
        $this->insert($values);

        return $this->pdo->lastInsertId();
    }

    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $sets = [];
        $bindings = $this->bindings;

        foreach ($values as $column => $value) {
            $this->assertIdentifier((string) $column);
            $parameter = ':u_' . $column;
            $sets[] = "{$column} = {$parameter}";
            $bindings[$parameter] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    private function selectSql(): array
    {
        $driver = $this->driver();
        $sql = "SELECT * FROM {$this->table}" . $this->whereSql();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($driver === 'sqlsrv') {
            if (($this->limitValue !== null || $this->offsetValue > 0) && $this->orders === []) {
                $sql .= ' ORDER BY (SELECT 0)';
            }

            if ($this->limitValue !== null || $this->offsetValue > 0) {
                $limit = $this->limitValue ?? PHP_INT_MAX;
                $sql .= " OFFSET {$this->offsetValue} ROWS FETCH NEXT {$limit} ROWS ONLY";
            }
        } else {
            if ($this->limitValue !== null) {
                $sql .= " LIMIT {$this->limitValue}";
            }

            if ($this->offsetValue > 0) {
                if ($this->limitValue === null) {
                    $sql .= $driver === 'sqlite' ? ' LIMIT -1' : ' LIMIT 18446744073709551615';
                }
                $sql .= " OFFSET {$this->offsetValue}";
            }
        }

        return [$sql, $this->bindings];
    }

    private function whereSql(): string
    {
        return $this->wheres === [] ? '' : ' WHERE ' . implode(' AND ', $this->wheres);
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
