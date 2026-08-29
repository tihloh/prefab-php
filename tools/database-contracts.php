<?php

namespace Tihloh\Prefab;

use PDO;
use Throwable;

/*
 |--------------------------------------------------------------------------
 | Prefab database interoperability contract
 |--------------------------------------------------------------------------
 |
 | Small shared contract used by Prefab modules without forcing
 | prefab-database as a dependency.
 |
 */

if (!interface_exists(DatabaseInterface::class, false)) {
    interface DatabaseInterface
    {
        /** @return array<int, array<string, mixed>> */
        public function select(string $sql, array $bindings = []): array;
        public function statement(string $sql, array $bindings = []): bool;
        public function transaction(callable $callback): mixed;
        public function driver(): string;
        public function lastInsertId(?string $name = null): string|false;
        public function pdo(): PDO;
    }
}

if (!class_exists(PdoDatabaseAdapter::class, false)) {
    final class PdoDatabaseAdapter implements DatabaseInterface
    {
        public function __construct(private PDO $connection)
        {
        }

        public function select(string $sql, array $bindings = []): array
        {
            return PrefabRuntime::traceCall(
                'database',
                'select',
                $this->sqlContext($sql, $bindings),
                function () use ($sql, $bindings): array {
                    $statement = $this->connection->prepare($sql);
                    $statement->execute($bindings);
                    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                    PrefabRuntime::traceStep('database.rows', ['rows' => count($rows)]);
                    return $rows;
                },
            );
        }

        public function statement(string $sql, array $bindings = []): bool
        {
            $context = $this->sqlContext($sql, $bindings);
            $operation = $context['operation'] ?? 'statement';
            unset($context['operation']);

            return PrefabRuntime::traceCall(
                'database',
                $operation,
                $context,
                function () use ($sql, $bindings): bool {
                    $statement = $this->connection->prepare($sql);
                    $ok = $statement->execute($bindings);
                    PrefabRuntime::traceStep('database.affected', ['rows' => $statement->rowCount()]);
                    return $ok;
                },
            );
        }

        public function transaction(callable $callback): mixed
        {
            return PrefabRuntime::traceCall('database', 'transaction', [], function () use ($callback): mixed {
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
            });
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

        /**
         * Extract only safe, debugging-useful SQL metadata. Raw SQL and values
         * are deliberately not included in traces.
         */
        private function sqlContext(string $sql, array $bindings): array
        {
            $trimmed = ltrim($sql);
            $operation = strtolower((string) strtok($trimmed, " \t\r\n"));
            $operation = in_array($operation, ['select', 'insert', 'update', 'delete'], true)
                ? $operation
                : 'statement';

            $table = null;
            $patterns = [
                '/\bINSERT\s+INTO\s+([A-Za-z_][A-Za-z0-9_]*)/i',
                '/\bUPDATE\s+([A-Za-z_][A-Za-z0-9_]*)/i',
                '/\bDELETE\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)/i',
                '/\bFROM\s+([A-Za-z_][A-Za-z0-9_]*)/i',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $sql, $match)) {
                    $table = $match[1];
                    break;
                }
            }

            return array_filter([
                'operation' => $operation,
                'table' => $table,
                'bindings' => count($bindings),
            ], static fn (mixed $value): bool => $value !== null);
        }
    }
}
