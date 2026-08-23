<?php

namespace Tihloh\Prefab\Database\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;

/**
 * Standalone manager for one or more named PDO connections.
 *
 * Prefab Database is optional. Other Prefab modules may consume its published
 * database capabilities automatically, but they never require this package.
 *
 * Connection definitions accept either a ready PDO/DSN or a convenient
 * Laravel-like driver configuration for mysql/mariadb, pgsql, sqlite/sqlsrv.
 */
final class DatabaseManager
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

    /** Resolve the three config levels, create connections once and publish them. */
    public function prefabConfigure(): void
    {
        $default = PrefabConfig::resolve(
            'database',
            'default',
            $this->config,
            $this->defaultConnection,
        );
        $this->defaultConnection = (string) $default['value'];
        PrefabRuntime::recordResolution(
            'database',
            'default_connection',
            $default['source'],
            ['name' => $this->defaultConnection],
        );

        $configured = PrefabConfig::resolve('database', 'connections', $this->config, []);
        if (is_array($configured['value'])) {
            foreach ($configured['value'] as $name => $definition) {
                if ($definition instanceof PDO || is_array($definition)) {
                    $this->definitions[(string) $name] = $definition;
                }
            }
        }

        /* Backward-compatible shorthand: PrefabConfig::set(['database' => $pdo]). */
        $database = PrefabConfig::resolve('database', 'database', $this->config);
        if (
            $database['value'] instanceof PDO
            && !isset($this->definitions[$this->defaultConnection])
            && !isset($this->connections[$this->defaultConnection])
        ) {
            $this->definitions[$this->defaultConnection] = $database['value'];
            PrefabRuntime::recordResolution(
                'database',
                'database',
                $database['source'],
                ['connection' => $this->defaultConnection],
            );
        }

        foreach ($this->definitions as $name => $definition) {
            $this->connections[$name] ??= $this->createConnection($name, $definition);
        }

        if ($this->connections !== [] && !isset($this->connections[$this->defaultConnection])) {
            $this->defaultConnection = (string) array_key_first($this->connections);
            PrefabRuntime::recordResolution(
                'database',
                'default_connection',
                'internal-fallback',
                ['name' => $this->defaultConnection],
            );
        }

        if ($this->connections === []) {
            return;
        }

        PrefabRuntime::provide(
            'database',
            $this->default(),
            'prefab-database',
            meta: ['connection' => $this->defaultConnection, 'driver' => $this->driver()],
        );
        PrefabRuntime::provide('database_manager', $this, 'prefab-database');

        foreach ($this->connections as $name => $connection) {
            PrefabRuntime::provide(
                'database.connection.' . $name,
                $connection,
                'prefab-database',
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

    /** Start a lightweight portable query builder on a connection. */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return new QueryBuilder($this->connection($connection), $table);
    }

    /** Execute a parameterized SELECT and return associative rows. */
    public function select(string $sql, array $bindings = [], ?string $connection = null): array
    {
        $stmt = $this->connection($connection)->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Execute a parameterized SQL statement and return affected rows. */
    public function statement(string $sql, array $bindings = [], ?string $connection = null): int
    {
        $stmt = $this->connection($connection)->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Execute a callback atomically on one connection.
     * The callback receives this manager and the active PDO connection.
     */
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

    public function set(string $name, PDO|array $definition): self
    {
        $this->definitions[$name] = $definition;
        $this->connections[$name] = $this->createConnection($name, $definition);

        PrefabRuntime::provide(
            'database.connection.' . $name,
            $this->connections[$name],
            'prefab-database',
            meta: ['connection' => $name, 'driver' => $this->driver($name)],
        );

        if ($name === $this->defaultConnection) {
            PrefabRuntime::provide(
                'database',
                $this->connections[$name],
                'prefab-database',
                meta: ['connection' => $name, 'driver' => $this->driver($name)],
            );
        }

        return $this;
    }

    public function useDefault(string $name): self
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Unknown database connection '{$name}'.");
        }

        $this->defaultConnection = $name;
        PrefabRuntime::provide(
            'database',
            $this->connections[$name],
            'prefab-database',
            meta: ['connection' => $name, 'driver' => $this->driver($name)],
        );
        PrefabRuntime::recordResolution(
            'database',
            'default_connection',
            'runtime-explicit',
            ['name' => $name],
        );

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

    public function prefabResource(string $name): mixed
    {
        if ($name === 'database_manager') {
            return $this;
        }
        if ($name === 'database') {
            return $this->connections !== [] ? $this->default() : null;
        }
        if (str_starts_with($name, 'connection:')) {
            return $this->connections[substr($name, strlen('connection:'))] ?? null;
        }

        return null;
    }

    public function explain(): array
    {
        return PrefabRuntime::explain('database');
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

    /** Build a PDO DSN from a convenient driver-based connection definition. */
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
            'mysql' => sprintf(
                'mysql:host=%s;%sdbname=%s;charset=%s',
                $host,
                $port ? 'port=' . (int) $port . ';' : '',
                $database,
                (string) ($definition['charset'] ?? 'utf8mb4'),
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;%sdbname=%s',
                $host,
                $port ? 'port=' . (int) $port . ';' : '',
                $database,
            ),
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s%s;Database=%s',
                $host,
                $port ? ',' . (int) $port : '',
                $database,
            ),
            default => throw new InvalidArgumentException(
                "Connection '{$name}' needs a DSN or supported driver: mysql/mariadb, pgsql, sqlite, sqlsrv.",
            ),
        };
    }
}
