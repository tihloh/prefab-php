<?php

namespace Tihloh\Prefab\Database\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Tihloh\Prefab\PrefabRuntime;

/** Lightweight, framework-independent query builder for common portable CRUD. */
final class QueryBuilder
{
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limitValue = null;
    private int $offsetValue = 0;

    public function __construct(private PDO $pdo, private string $table)
    {
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
        return PrefabRuntime::traceCall('database', 'get', [
            'table' => $this->table,
            'bindings' => count($this->bindings),
        ], function (): array {
            [$sql, $bindings] = $this->selectSql();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function first(): ?array
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            throw new InvalidArgumentException('Insert values cannot be empty.');
        }

        return PrefabRuntime::traceCall('database', 'insert', [
            'table' => $this->table,
            'columns' => array_keys($values),
        ], function () use ($values): bool {
            $columns = array_keys($values);
            foreach ($columns as $column) {
                $this->assertIdentifier((string) $column);
            }
            $placeholders = array_map(fn (string $column): string => ':i_' . $column, $columns);
            $bindings = [];
            foreach ($values as $column => $value) {
                $bindings[':i_' . $column] = $value;
            }

            $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, implode(', ', $columns), implode(', ', $placeholders));
            return $this->pdo->prepare($sql)->execute($bindings);
        });
    }

    public function insertGetId(array $values): int|string
    {
        $this->insert($values);
        return $this->pdo->lastInsertId();
    }

    public function update(array $values): int
    {
        if ($values === []) {
            PrefabRuntime::traceStart('database', 'update', ['table' => $this->table, 'columns' => []]);
            PrefabRuntime::traceEnd(['result' => 0]);
            return 0;
        }

        return PrefabRuntime::traceCall('database', 'update', [
            'table' => $this->table,
            'columns' => array_keys($values),
        ], function () use ($values): int {
            $sets = [];
            $bindings = $this->bindings;
            foreach ($values as $column => $value) {
                $this->assertIdentifier((string) $column);
                $parameter = ':u_' . $column;
                $sets[] = "{$column} = {$parameter}";
                $bindings[$parameter] = $value;
            }

            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(', ', $sets) . $this->whereSql());
            $stmt->execute($bindings);
            return $stmt->rowCount();
        });
    }

    public function delete(): int
    {
        return PrefabRuntime::traceCall('database', 'delete', [
            'table' => $this->table,
            'bindings' => count($this->bindings),
        ], function (): int {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table}" . $this->whereSql());
            $stmt->execute($this->bindings);
            return $stmt->rowCount();
        });
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
                $limit = $this->limitValue ?? 2147483647;
                $sql .= " OFFSET {$this->offsetValue} ROWS FETCH NEXT {$limit} ROWS ONLY";
            }
        } else {
            if ($this->limitValue !== null) {
                $sql .= " LIMIT {$this->limitValue}";
            } elseif ($this->offsetValue > 0) {
                if ($driver === 'sqlite') {
                    $sql .= ' LIMIT -1';
                } elseif ($driver === 'mysql') {
                    $sql .= ' LIMIT 18446744073709551615';
                }
                // PostgreSQL supports OFFSET without LIMIT.
            }

            if ($this->offsetValue > 0) {
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
