<?php

namespace Tihloh\Prefab\Database\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;

/**
 * Manages one or more named PDO connections for an application.
 *
 * The module is completely standalone. When used with other Prefab modules,
 * those modules may inherit its default or named connection if they were not
 * explicitly configured with their own database resource.
 *
 * A connection may be provided as:
 * - an existing PDO instance, or
 * - a configuration array containing dsn, username, password, and options.
 *
 * Example:
 *
 *     $database = new DatabaseManager([
 *         'default' => 'main',
 *         'connections' => [
 *             'main' => $mainPdo,
 *             'logs' => [
 *                 'dsn' => 'mysql:host=127.0.0.1;dbname=logs;charset=utf8mb4',
 *                 'username' => 'app',
 *                 'password' => 'secret',
 *             ],
 *         ],
 *     ]);
 */
final class DatabaseManager
{
    /** @var array<string, PDO> */
    private array $connections = [];

    /** @var array<string, PDO|array<string, mixed>> */
    private array $definitions = [];

    private array $config = [];
    private string $defaultConnection = 'default';

    /**
     * @param array<string, mixed>|PDO|null $config
     *
     * Passing a PDO directly creates a single connection named "default".
     */
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

    /**
     * Resolve all configured connections during module declaration.
     *
     * Connections are established here rather than on each feature call so
     * normal runtime access is a direct array lookup of an already-created PDO.
     */
    public function prefabConfigure(): void
    {
        $config = array_replace(
            PrefabConfig::moduleConfig('database'),
            $this->config,
        );

        $this->defaultConnection = (string) (
            $config['default']
            ?? $this->defaultConnection
        );

        $configuredConnections = $config['connections'] ?? [];

        if (isset($config['database']) && $config['database'] instanceof PDO) {
            $configuredConnections[$this->defaultConnection] ??= $config['database'];
        }

        // The top-level shared Prefab `database` PDO remains compatible with
        // older projects and becomes the default connection when available.
        $sharedDatabase = PrefabConfig::get('database');

        if (
            $sharedDatabase instanceof PDO
            && !isset($configuredConnections[$this->defaultConnection])
            && !isset($this->connections[$this->defaultConnection])
        ) {
            $configuredConnections[$this->defaultConnection] = $sharedDatabase;
        }

        foreach ($configuredConnections as $name => $definition) {
            $this->definitions[(string) $name] = $definition;
        }

        // Complete connection creation during configuration. This keeps normal
        // get()/connection() calls free from repeated discovery/initialization.
        foreach (array_keys($this->definitions) as $name) {
            if (!isset($this->connections[$name])) {
                $this->connections[$name] = $this->createConnection(
                    $name,
                    $this->definitions[$name],
                );
            }
        }

        if (
            $this->connections !== []
            && !isset($this->connections[$this->defaultConnection])
        ) {
            // If no default name was explicitly present, use the first defined
            // connection as a practical standalone fallback.
            $this->defaultConnection = (string) array_key_first($this->connections);
        }
    }

    /**
     * Return a named connection, or the configured default when name is null.
     */
    public function connection(?string $name = null): PDO
    {
        $name ??= $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            throw new RuntimeException("Database connection '{$name}' is not configured.");
        }

        return $this->connections[$name];
    }

    /** Alias for connection(). */
    public function get(?string $name = null): PDO
    {
        return $this->connection($name);
    }

    /** Return the default PDO connection. */
    public function default(): PDO
    {
        return $this->connection();
    }

    /** Return the current default connection name. */
    public function defaultName(): string
    {
        return $this->defaultConnection;
    }

    /** Determine whether a named connection is already available. */
    public function has(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Add or replace a connection at runtime.
     *
     * This is useful for project-defined connections discovered after startup,
     * while still keeping normal connection access cached afterward.
     *
     * @param PDO|array<string, mixed> $definition
     */
    public function set(string $name, PDO|array $definition): self
    {
        $this->definitions[$name] = $definition;
        $this->connections[$name] = $this->createConnection($name, $definition);

        return $this;
    }

    /** Change which named connection is returned by default(). */
    public function useDefault(string $name): self
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Unknown database connection '{$name}'.");
        }

        $this->defaultConnection = $name;

        return $this;
    }

    /**
     * Verify a connection by executing a lightweight query.
     */
    public function ping(?string $name = null): bool
    {
        try {
            $this->connection($name)->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Expose database capabilities to compatible Prefab modules.
     *
     * Supported resources:
     * - database: default PDO connection
     * - connection:<name>: a specific named PDO connection
     * - database_manager: this manager instance
     */
    public function prefabResource(string $name): mixed
    {
        if ($name === 'database_manager') {
            return $this;
        }

        if ($name === 'database') {
            return $this->connections !== [] ? $this->default() : null;
        }

        if (str_starts_with($name, 'connection:')) {
            $connection = substr($name, strlen('connection:'));
            return $this->connections[$connection] ?? null;
        }

        return null;
    }

    /**
     * @param PDO|array<string, mixed> $definition
     */
    private function createConnection(string $name, PDO|array $definition): PDO
    {
        if ($definition instanceof PDO) {
            return $definition;
        }

        $dsn = $definition['dsn'] ?? null;

        if (!is_string($dsn) || $dsn === '') {
            throw new InvalidArgumentException(
                "Database connection '{$name}' requires a non-empty DSN.",
            );
        }

        $username = isset($definition['username'])
            ? (string) $definition['username']
            : null;
        $password = isset($definition['password'])
            ? (string) $definition['password']
            : null;
        $options = is_array($definition['options'] ?? null)
            ? $definition['options']
            : [];

        $options += [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO(
            $dsn,
            $username,
            $password,
            $options,
        );
    }
}
