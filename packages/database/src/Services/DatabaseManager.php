<?php

namespace Tihloh\Prefab\Database\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;

/**
 * Standalone manager for one or more named PDO connections.
 *
 * The Database module never requires another Prefab package. When other Prefab
 * modules are present, it publishes database capabilities that they may consume
 * automatically when they have no explicit database configuration of their own.
 *
 * Configuration priority is:
 * 1. direct DatabaseManager constructor configuration;
 * 2. PrefabConfig modules.database configuration;
 * 3. common PrefabConfig configuration;
 * 4. module internal defaults.
 *
 * Other modules remain free to ignore this manager and use their own PDO or
 * repository. Installing Prefab Database therefore adds capability, not a new
 * dependency requirement.
 */
final class DatabaseManager
{
    /** @var array<string, PDO> */
    private array $connections = [];

    /** @var array<string, PDO|array<string, mixed>> */
    private array $definitions = [];

    /** Direct constructor configuration, local to this instance only. */
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
     * Resolve configuration, create connections once, and publish capabilities.
     *
     * PrefabRuntime calls this during module declaration/configuration passes.
     * Normal connection() calls afterwards are direct cached array lookups.
     */
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

        $configuredConnections = PrefabConfig::resolve(
            'database',
            'connections',
            $this->config,
            [],
        );

        if (is_array($configuredConnections['value'])) {
            foreach ($configuredConnections['value'] as $name => $definition) {
                if ($definition instanceof PDO || is_array($definition)) {
                    $this->definitions[(string) $name] = $definition;
                }
            }
        }

        /*
         * Backward-compatible shorthand:
         *
         * PrefabConfig::set(['database' => $pdo])
         *
         * A PDO at the common `database` key becomes the default connection.
         */
        $database = PrefabConfig::resolve(
            'database',
            'database',
            $this->config,
        );

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
            if (!isset($this->connections[$name])) {
                $this->connections[$name] = $this->createConnection(
                    $name,
                    $definition,
                );
            }
        }

        if (
            $this->connections !== []
            && !isset($this->connections[$this->defaultConnection])
        ) {
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

        /* Publish the default database capability for unconfigured modules. */
        PrefabRuntime::provide(
            'database',
            $this->default(),
            'prefab-database',
            meta: [
                'connection' => $this->defaultConnection,
            ],
        );

        PrefabRuntime::provide(
            'database_manager',
            $this,
            'prefab-database',
        );

        /* Publish each named connection as its own optional capability. */
        foreach ($this->connections as $name => $connection) {
            PrefabRuntime::provide(
                'database.connection.' . $name,
                $connection,
                'prefab-database',
                meta: ['connection' => $name],
            );
        }
    }

    /** Return a named connection, or the configured default when name is null. */
    public function connection(?string $name = null): PDO
    {
        $name ??= $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            throw new RuntimeException(
                "Database connection '{$name}' is not configured.",
            );
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

    /** Determine whether a named connection is available. */
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
     * Add or replace a named connection.
     *
     * @param PDO|array<string, mixed> $definition
     */
    public function set(string $name, PDO|array $definition): self
    {
        $this->definitions[$name] = $definition;
        $this->connections[$name] = $this->createConnection($name, $definition);

        PrefabRuntime::provide(
            'database.connection.' . $name,
            $this->connections[$name],
            'prefab-database',
            meta: ['connection' => $name],
        );

        if ($name === $this->defaultConnection) {
            PrefabRuntime::provide(
                'database',
                $this->connections[$name],
                'prefab-database',
                meta: ['connection' => $name],
            );
        }

        return $this;
    }

    /** Change which named connection is returned by default(). */
    public function useDefault(string $name): self
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(
                "Unknown database connection '{$name}'.",
            );
        }

        $this->defaultConnection = $name;

        PrefabRuntime::provide(
            'database',
            $this->connections[$name],
            'prefab-database',
            meta: ['connection' => $name],
        );

        PrefabRuntime::recordResolution(
            'database',
            'default_connection',
            'runtime-explicit',
            ['name' => $name],
        );

        return $this;
    }

    /** Verify a connection by executing a lightweight query. */
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
     * Backward-compatible direct resource access used by older Prefab modules.
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

    /** Explain how this module resolved its configuration/resources. */
    public function explain(): array
    {
        return PrefabRuntime::explain('database');
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
