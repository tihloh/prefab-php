<?php

namespace Tihloh\Prefab;

use PDO;
use Throwable;

if (!interface_exists(DatabaseInterface::class, false)) {
    interface DatabaseInterface
    {
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
        public function __construct(
            private PDO $connection,
            private string $ownerModule = 'database',
        ) {}

        public function select(string $sql, array $bindings = []): array
        {
            $context = $this->sqlContext($sql, $bindings, 'select');
            return PrefabRuntime::traceCall('database', 'select', $context, function () use ($sql, $bindings): array {
                PrefabRuntime::traceStep('database.sql', ['sql' => $sql]);
                $statement = $this->connection->prepare($sql);
                $statement->execute($bindings);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                PrefabRuntime::traceStep('database.rows', ['rows' => count($rows)]);
                return $rows;
            });
        }

        public function statement(string $sql, array $bindings = []): bool
        {
            $context = $this->sqlContext($sql, $bindings);
            return PrefabRuntime::traceCall('database', $context['operation'], $context, function () use ($sql, $bindings): bool {
                PrefabRuntime::traceStep('database.sql', ['sql' => $sql]);
                $statement = $this->connection->prepare($sql);
                $ok = $statement->execute($bindings);
                PrefabRuntime::traceStep('database.affected', ['rows' => $statement->rowCount()]);
                return $ok;
            });
        }

        public function transaction(callable $callback): mixed
        {
            return PrefabRuntime::traceCall('database', 'transaction', ['owner_module' => $this->ownerModule], function () use ($callback): mixed {
                $this->connection->beginTransaction();
                try {
                    $result = $callback($this);
                    $this->connection->commit();
                    return $result;
                } catch (Throwable $exception) {
                    if ($this->connection->inTransaction()) $this->connection->rollBack();
                    throw $exception;
                }
            });
        }

        public function driver(): string
        {
            $driver = strtolower((string)$this->connection->getAttribute(PDO::ATTR_DRIVER_NAME));
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

        private function sqlContext(string $sql, array $bindings, ?string $operation = null): array
        {
            $operation ??= $this->operation($sql);
            $context = [
                'owner_module' => $this->ownerModule,
                'operation' => $operation,
                'bindings' => count($bindings),
            ];

            $table = $this->table($sql, $operation);
            if ($table !== null) $context['table'] = $table;

            return $context;
        }

        private function operation(string $sql): string
        {
            $verb = strtolower((string)preg_replace('/^\s*([a-z]+).*$/is', '$1', $sql));
            return match ($verb) {
                'select' => 'select',
                'insert' => 'insert',
                'update' => 'update',
                'delete' => 'delete',
                default => 'statement',
            };
        }

        private function table(string $sql, string $operation): ?string
        {
            $patterns = match ($operation) {
                'insert' => ['/\binsert\s+into\s+[`"\[]?([a-z0-9_.-]+)/i'],
                'update' => ['/\bupdate\s+[`"\[]?([a-z0-9_.-]+)/i'],
                'delete' => ['/\bdelete\s+from\s+[`"\[]?([a-z0-9_.-]+)/i'],
                'select' => ['/\bfrom\s+[`"\[]?([a-z0-9_.-]+)/i'],
                default => [],
            };

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $sql, $match)) {
                    return rtrim($match[1], '`"]');
                }
            }
            return null;
        }
    }
}
