<?php

namespace Tihloh\Prefab;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

if (!interface_exists(DatabaseInterface::class, false)) {
    interface DatabaseInterface
    {
        public function table(string $table): QueryBuilderInterface;
        public function select(string $sql, array $bindings = []): array;
        public function statement(string $sql, array $bindings = []): bool;
        public function transaction(callable $callback): mixed;
        public function driver(): string;
        public function lastInsertId(?string $name = null): string|false;
        public function pdo(): PDO;
    }
}

if (!interface_exists(QueryBuilderInterface::class, false)) {
    interface QueryBuilderInterface
    {
        public function where(string $column, mixed $operatorOrValue, mixed $value = null): static;
        public function orderBy(string $column, string $direction = 'asc'): static;
        public function limit(int $limit): static;
        public function offset(int $offset): static;
        public function get(): array;
        public function first(): ?array;
        public function insert(array $values): bool;
        public function insertGetId(array $values): int|string;
        public function update(array $values): int;
        public function delete(): int;
    }
}

if (!class_exists(PdoDatabaseAdapter::class, false)) {
    final class PdoDatabaseAdapter implements DatabaseInterface
    {
        public function __construct(private PDO $connection) {}

        public function table(string $table): QueryBuilderInterface
        {
            return new PdoQueryBuilder($this->connection, $table);
        }

        public function select(string $sql, array $bindings = []): array
        {
            $statement = $this->connection->prepare($sql);
            $statement->execute($bindings);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        public function statement(string $sql, array $bindings = []): bool
        {
            $statement = $this->connection->prepare($sql);
            return $statement->execute($bindings);
        }

        public function transaction(callable $callback): mixed
        {
            $this->connection->beginTransaction();
            try {
                $result = $callback($this);
                $this->connection->commit();
                return $result;
            } catch (Throwable $exception) {
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                throw $exception;
            }
        }

        public function driver(): string
        {
            $driver = strtolower((string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME));
            return $driver === 'dblib' ? 'sqlsrv' : $driver;
        }

        public function lastInsertId(?string $name = null): string|false
        {
            return $this->connection->lastInsertId($name);
        }

        public function pdo(): PDO
        {
            return $this->connection;
        }
    }
}

if (!class_exists(PdoQueryBuilder::class, false)) {
    final class PdoQueryBuilder implements QueryBuilderInterface
    {
        private array $wheres = [];
        private array $bindings = [];
        private array $orders = [];
        private ?int $limitValue = null;
        private int $offsetValue = 0;

        public function __construct(private PDO $connection, private string $table)
        {
            $this->assertIdentifier($table);
        }

        public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
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

        public function orderBy(string $column, string $direction = 'asc'): static
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

        public function limit(int $limit): static
        {
            $clone = clone $this;
            $clone->limitValue = max(1, $limit);
            return $clone;
        }

        public function offset(int $offset): static
        {
            $clone = clone $this;
            $clone->offsetValue = max(0, $offset);
            return $clone;
        }

        public function get(): array
        {
            [$sql, $bindings] = $this->selectSql();
            $statement = $this->connection->prepare($sql);
            $statement->execute($bindings);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
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
            foreach ($columns as $column) {
                $this->assertIdentifier((string) $column);
            }
            $placeholders = [];
            $bindings = [];
            foreach ($values as $column => $value) {
                $parameter = ':i_' . $column;
                $placeholders[] = $parameter;
                $bindings[$parameter] = $value;
            }
            $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, implode(', ', $columns), implode(', ', $placeholders));
            return $this->connection->prepare($sql)->execute($bindings);
        }

        public function insertGetId(array $values): int|string
        {
            $this->insert($values);
            $id = $this->connection->lastInsertId();
            return ctype_digit((string) $id) ? (int) $id : $id;
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
            $statement = $this->connection->prepare($sql);
            $statement->execute($bindings);
            return $statement->rowCount();
        }

        public function delete(): int
        {
            $sql = "DELETE FROM {$this->table}" . $this->whereSql();
            $statement = $this->connection->prepare($sql);
            $statement->execute($this->bindings);
            return $statement->rowCount();
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
                }
                if ($this->offsetValue > 0) {
                    if ($this->limitValue === null) {
                        $sql .= match ($driver) {
                            'sqlite' => ' LIMIT -1',
                            'pgsql' => ' LIMIT ALL',
                            default => ' LIMIT 18446744073709551615',
                        };
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
            $driver = strtolower((string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME));
            return $driver === 'dblib' ? 'sqlsrv' : $driver;
        }

        private function assertIdentifier(string $identifier): void
        {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                throw new RuntimeException("Unsafe SQL identifier: {$identifier}");
            }
        }
    }
}
