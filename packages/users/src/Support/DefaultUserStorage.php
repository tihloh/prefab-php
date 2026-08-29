<?php

namespace Tihloh\Prefab\Users\Support;

use PDO;
use RuntimeException;
use Tihloh\Prefab\DatabaseInterface;
use Tihloh\Prefab\PdoDatabaseAdapter;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;

/**
 * Default storage/schema helper for Prefab Users.
 *
 * It creates a local SQLite database only when no database is available, and
 * it can also create the mapped users table on an already configured database.
 */
final class DefaultUserStorage
{
    public static function database(array $config = []): DatabaseInterface
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException(
                'Prefab Users could not resolve a configured database and the automatic SQLite fallback requires ext-pdo_sqlite.'
            );
        }

        $path = self::path($config);
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Prefab Users could not create storage directory: {$directory}");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $database = new PdoDatabaseAdapter($pdo);
        self::ensureSchema(
            $database,
            new UserMap(table: (string) ($config['table'] ?? 'users')),
        );

        return $database;
    }

    public static function provider(array $config = []): UserProviderInterface
    {
        $table = (string) ($config['table'] ?? 'users');

        return new PdoUserProvider(
            self::database($config),
            new UserMap(table: $table),
        );
    }

    /** Create the mapped users table if it does not already exist. */
    public static function ensureSchema(DatabaseInterface $database, UserMap $map): void
    {
        self::assertIdentifier($map->table);
        foreach ($map->coreColumns() as $column) {
            self::assertIdentifier($column);
        }
        foreach ($map->attributes as $alias => $column) {
            self::assertIdentifier((string) $column);
        }

        $driver = strtolower($database->driver());
        $table = $map->table;
        $definitions = self::columnDefinitions($driver, $map);

        $sql = match ($driver) {
            'mysql', 'mariadb' => sprintf(
                'CREATE TABLE IF NOT EXISTS %s (%s) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $table,
                implode(', ', $definitions),
            ),
            'pgsql' => sprintf(
                'CREATE TABLE IF NOT EXISTS %s (%s)',
                $table,
                implode(', ', $definitions),
            ),
            'sqlite' => sprintf(
                'CREATE TABLE IF NOT EXISTS %s (%s)',
                $table,
                implode(', ', $definitions),
            ),
            'sqlsrv' => sprintf(
                "IF OBJECT_ID(N'%s', N'U') IS NULL BEGIN CREATE TABLE %s (%s) END",
                $table,
                $table,
                implode(', ', $definitions),
            ),
            default => throw new RuntimeException(
                "Prefab Users cannot automatically create its users table for database driver: {$driver}"
            ),
        };

        $database->statement($sql);
    }

    /** @return array<int,string> */
    private static function columnDefinitions(string $driver, UserMap $map): array
    {
        $definitions = [];

        $definitions[] = match ($driver) {
            'mysql', 'mariadb' => "{$map->id} BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
            'pgsql' => "{$map->id} BIGSERIAL PRIMARY KEY",
            'sqlsrv' => "{$map->id} BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY",
            default => "{$map->id} INTEGER PRIMARY KEY AUTOINCREMENT",
        };

        if ($map->name !== null) {
            $definitions[] = match ($driver) {
                'sqlsrv' => "{$map->name} NVARCHAR(255) NULL",
                'sqlite' => "{$map->name} TEXT NULL",
                default => "{$map->name} VARCHAR(255) NULL",
            };
        }

        if ($map->email !== null) {
            $definitions[] = match ($driver) {
                'sqlsrv' => "{$map->email} NVARCHAR(255) NULL UNIQUE",
                'sqlite' => "{$map->email} TEXT NULL UNIQUE",
                default => "{$map->email} VARCHAR(255) NULL UNIQUE",
            };
        }

        if ($map->active !== null) {
            $definitions[] = match ($driver) {
                'pgsql' => "{$map->active} BOOLEAN NOT NULL DEFAULT TRUE",
                'sqlsrv' => "{$map->active} BIT NOT NULL DEFAULT 1",
                default => "{$map->active} INTEGER NOT NULL DEFAULT 1",
            };
        }

        $core = array_values($map->coreColumns());
        foreach ($map->attributes as $alias => $column) {
            $column = (string) $column;
            if (in_array($column, $core, true)) {
                continue;
            }

            $definitions[] = match ($driver) {
                'sqlsrv' => "{$column} NVARCHAR(MAX) NULL",
                default => "{$column} TEXT NULL",
            };
        }

        return $definitions;
    }

    public static function path(array $config = []): string
    {
        $configured = $config['sqlite_path'] ?? (getenv('PREFAB_USERS_SQLITE_PATH') ?: null);
        if (is_string($configured) && trim($configured) !== '') {
            return self::absolutePath($configured);
        }

        $root = getcwd() ?: '.';
        if (basename(str_replace('\\', '/', $root)) === 'public') {
            $root = dirname($root);
        }

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'prefab' . DIRECTORY_SEPARATOR . 'users.sqlite';
    }

    private static function absolutePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path)) {
            return $path;
        }

        $root = getcwd() ?: '.';
        if (basename(str_replace('\\', '/', $root)) === 'public') {
            $root = dirname($root);
        }

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $path;
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException("Unsafe SQL identifier: {$identifier}");
        }
    }
}
