<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\DatabaseInterface;
use Tihloh\Prefab\PdoDatabaseAdapter;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;

/**
 * Core database infrastructure for one or more named PDO connections.
 *
 * This is intentionally small: connections, basic SQL execution,
 * transactions and a lightweight query builder. Feature/business database
 * behavior belongs outside Core.
 */
final class DatabaseManager implements DatabaseInterface
{
    /** @var array<string, PDO> */
    private array $connections = [];

    /** @var array<string, PDO|array<string, mixed>> */
    private array $definitions = [];

    private array $config = [];
    private string $defaultConnection = 'default';

    public function __construct(array|PDO|null $config = null)
    {
        if ($config instanceof PDO) {
            $this->connections['default'] = $config;
            $this->definitions['default'] = $config;
        } elseif (is_array($config)) {
            $this->config = $config;
        }

        PrefabRuntime::register('database', $this);
    }

    public function prefabConfigure(): void
    {
        $default = PrefabConfig::resolve('database', 'default', $this->config, $this->defaultConnection);
        $this->defaultConnection = (string) $default['value'];

        PrefabRuntime::recordResolution('database', 'default_connection', $default['source'], [
            'name' => $this->defaultConnection,
        ]);

        $configured = PrefabConfig::resolve('database', 'connections', $this->config, []);
        if (is_array($configured['value'])) {
            foreach ($configured['value'] as $name => $definition) {
                if ($definition instanceof PDO || is_array($definition)) {
                    $this->definitions[(string) $name] = $definition;
                }
            }
        }

        $database = PrefabConfig::resolve('database', 'database', $this->config);
        if (
            $database['value'] instanceof PDO
            && !isset($this->definitions[$this->defaultConnection])
            && !isset($this->connections[$this->defaultConnection])
        ) {
            $this->definitions[$this->defaultConnection] = $database['value'];
        }

        foreach ($this->definitions as $name => $definition) {
            $this->connections[$name] ??= $this->createConnection($name, $definition);
        }

        if ($this->connections !== [] && !isset($this->connections[$this->defaultConnection])) {
            $this->defaultConnection = (string) array_key_first($this->connections);
        }

        if ($this->connections === []) {
            return;
        }

        PrefabRuntime::provide('database', $this, 'prefab-core', meta: [
            'connection' => $this->defaultConnection,
            'driver' => $this->driver(),
        ]);
        PrefabRuntime::provide('database_manager', $this, 'prefab-core');

        foreach ($this->connections as $name => $connection) {
            PrefabRuntime::provide(
                'database.connection.' . $name,
                new PdoDatabaseAdapter($connection, 'database'),
                'prefab-core',
                meta: ['connection' => $name, 'driver' => $this->driver($name)],
            );
        }
    }

    public function connection(?string $name = null): PDO
    {
        $name ??= $this->defaultConnection;
        if (!isset($this->connections[$name])) {
            throw new RuntimeException("Database connection '{$name}' is not configured.");
        }
        return $this->connections[$name];
    }

    public function get(?string $name = null): PDO
    {
        return $this->connection($name);
    }

    public function default(): PDO
    {
        return $this->connection();
    }

    public function defaultName(): string
    {
        return $this->defaultConnection;
    }

    public function driver(?string $name = null): string
    {
        $driver = strtolower((string) $this->connection($name)->getAttribute(PDO::ATTR_DRIVER_NAME));
        return $driver === 'dblib' ? 'sqlsrv' : $driver;
    }

    public function has(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->connections);
    }

    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return new QueryBuilder($this->connection($connection), $table);
    }

    public function select(string $sql, array $bindings = [], ?string $connection = null): array
    {
        return PrefabRuntime::traceCall('database', 'select', [
            'sql' => $sql,
            'bindings' => $bindings,
        ], function () use ($sql, $bindings, $connection): array {
            $statement = $this->connection($connection)->prepare($sql);
            $statement->execute($bindings);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function statement(string $sql, array $bindings = [], ?string $connection = null): bool
    {
        return PrefabRuntime::traceCall('database', 'statement', [
            'sql' => $sql,
            'bindings' => $bindings,
        ], function () use ($sql, $bindings, $connection): bool {
            $statement = $this->connection($connection)->prepare($sql);
            return $statement->execute($bindings);
        });
    }

    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        $pdo = $this->connection($connection);
        $pdo->beginTransaction();
        try {
            $result = $callback($this, $pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->connection()->lastInsertId($name);
    }

    public function pdo(): PDO
    {
        return $this->default();
    }

    public function set(string $name, PDO|array $definition): self
    {
        $this->definitions[$name] = $definition;
        $this->connections[$name] = $this->createConnection($name, $definition);

        PrefabRuntime::provide(
            'database.connection.' . $name,
            new PdoDatabaseAdapter($this->connections[$name], 'database'),
            'prefab-core',
            meta: ['connection' => $name, 'driver' => $this->driver($name)],
        );

        if ($name === $this->defaultConnection) {
            PrefabRuntime::provide('database', $this, 'prefab-core', meta: [
                'connection' => $name,
                'driver' => $this->driver($name),
            ]);
        }

        return $this;
    }

    public function useDefault(string $name): self
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Unknown database connection '{$name}'.");
        }
        $this->defaultConnection = $name;
        PrefabRuntime::provide('database', $this, 'prefab-core', meta: [
            'connection' => $name,
            'driver' => $this->driver($name),
        ]);
        return $this;
    }

    public function ping(?string $name = null): bool
    {
        try {
            $this->connection($name)->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function explain(): void
    {
        PrefabRuntime::explain('database');
    }

    public function explainData(): array
    {
        return PrefabRuntime::explainData('database');
    }

    /** @param PDO|array<string, mixed> $definition */
    private function createConnection(string $name, PDO|array $definition): PDO
    {
        if ($definition instanceof PDO) {
            return $definition;
        }

        $dsn = $definition['dsn'] ?? $this->buildDsn($name, $definition);
        $username = isset($definition['username']) ? (string) $definition['username'] : null;
        $password = isset($definition['password']) ? (string) $definition['password'] : null;
        $options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
        $options += [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    private function buildDsn(string $name, array $definition): string
    {
        $driver = strtolower((string) ($definition['driver'] ?? ''));
        $driver = $driver === 'mariadb' ? 'mysql' : $driver;

        if ($driver === 'sqlite') {
            $database = (string) ($definition['database'] ?? '');
            if ($database === '') {
                throw new InvalidArgumentException("SQLite connection '{$name}' requires database path.");
            }
            return 'sqlite:' . $database;
        }

        $host = (string) ($definition['host'] ?? '127.0.0.1');
        $database = (string) ($definition['database'] ?? '');
        $port = $definition['port'] ?? null;
        if ($database === '') {
            throw new InvalidArgumentException("Database connection '{$name}' requires database name.");
        }

        return match ($driver) {
            'mysql' => sprintf('mysql:host=%s;%sdbname=%s;charset=%s', $host, $port ? 'port=' . (int) $port . ';' : '', $database, (string) ($definition['charset'] ?? 'utf8mb4')),
            'pgsql' => sprintf('pgsql:host=%s;%sdbname=%s', $host, $port ? 'port=' . (int) $port . ';' : '', $database),
            'sqlsrv' => sprintf('sqlsrv:Server=%s%s;Database=%s', $host, $port ? ',' . (int) $port : '', $database),
            default => throw new InvalidArgumentException(
                "Connection '{$name}' needs a DSN or supported driver: mysql/mariadb, pgsql, sqlite, sqlsrv.",
            ),
        };
    }
}
