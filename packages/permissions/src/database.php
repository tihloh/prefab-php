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
        public function __construct(private PDO $connection) {}

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
