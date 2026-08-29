<?php

namespace Tihloh\Prefab;

use PDO;
use Throwable;

/*
 |--------------------------------------------------------------------------
 | Prefab database interoperability contract
 |--------------------------------------------------------------------------
 |
 | This is the canonical development copy embedded as src/database.php in
 | standalone Prefab packages that provide or consume database resources.
 |
 | The contract intentionally stays small. It covers the operations Prefab
 | modules need to share database access without forcing prefab-database as a
 | dependency. The richer Laravel-like query builder remains an optional
 | feature of Prefab Database itself.
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

        /**
         * Legacy/project escape hatch. Prefab modules should normally use the
         * unified methods above rather than operating on PDO directly.
         */
        public function pdo(): PDO;
    }
}

if (!class_exists(PdoDatabaseAdapter::class, false)) {
    /**
     * Adapts a normal PDO connection to Prefab's database contract.
     *
     * This keeps every consuming module standalone: passing PDO continues to
     * work even when prefab-database is not installed.
     */
    final class PdoDatabaseAdapter implements DatabaseInterface
    {
        public function __construct(
            private PDO $connection,
        ) {
        }

        public function select(string $sql, array $bindings = []): array
        {
            return PrefabRuntime::traceCall('database', 'select', [
                'bindings' => count($bindings),
            ], function () use ($sql, $bindings): array {
                $statement = $this->connection->prepare($sql);
                $statement->execute($bindings);
                return $statement->fetchAll(PDO::FETCH_ASSOC);
            });
        }

        public function statement(string $sql, array $bindings = []): bool
        {
            return PrefabRuntime::traceCall('database', 'statement', [
                'bindings' => count($bindings),
            ], function () use ($sql, $bindings): bool {
                $statement = $this->connection->prepare($sql);
                return $statement->execute($bindings);
            });
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
            $driver = strtolower(
                (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME),
            );

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
