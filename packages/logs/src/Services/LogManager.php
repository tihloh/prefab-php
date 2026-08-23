<?php

namespace Tihloh\Prefab\Logs\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Presenters\HumanLogPresenter;
use Tihloh\Prefab\Logs\Repositories\PdoLogRepository;

/**
 * Main service API for structured Prefab activity/audit logs.
 *
 * Logs can run completely standalone with a custom repository, use an explicit
 * database, inherit a named/default connection from Prefab Database, or fall
 * back to compatible database resources exposed by other Prefab modules.
 */
final class LogManager
{
    private ?LogRepositoryInterface $repository = null;
    private array $config = [];

    public function __construct(LogRepositoryInterface|array|null $repository = null)
    {
        if ($repository instanceof LogRepositoryInterface) {
            $this->repository = $repository;
        } elseif (is_array($repository)) {
            $this->config = $repository;
        }

        PrefabRuntime::register('logs', $this);
    }

    /**
     * Resolve storage once during module declaration/configuration passes.
     *
     * Resolution order:
     * 1. explicit repository
     * 2. explicit/local Logs database
     * 3. named/default Prefab Database connection
     * 4. compatible Users/Permissions database resource
     */
    public function prefabConfigure(): void
    {
        if ($this->repository) {
            return;
        }

        $configuredRepository = $this->config['repository']
            ?? PrefabConfig::module('logs', 'repository');

        if ($configuredRepository instanceof LogRepositoryInterface) {
            $this->repository = $configuredRepository;
            return;
        }

        $database = $this->config['database']
            ?? PrefabConfig::module('logs', 'database');

        if (!$database instanceof PDO) {
            $databaseManager = PrefabRuntime::get('database');

            if ($databaseManager) {
                $connectionName = $this->config['connection']
                    ?? PrefabConfig::module('logs', 'connection');

                if (
                    is_string($connectionName)
                    && method_exists($databaseManager, 'has')
                    && method_exists($databaseManager, 'connection')
                    && $databaseManager->has($connectionName)
                ) {
                    $database = $databaseManager->connection($connectionName);
                } elseif (method_exists($databaseManager, 'prefabResource')) {
                    $candidate = $databaseManager->prefabResource('database');

                    if ($candidate instanceof PDO) {
                        $database = $candidate;
                    }
                }
            }
        }

        if (!$database instanceof PDO) {
            foreach (['users', 'permissions'] as $source) {
                $module = PrefabRuntime::get($source);

                if (!$module || !method_exists($module, 'prefabResource')) {
                    continue;
                }

                $candidate = $module->prefabResource('database');

                if ($candidate instanceof PDO) {
                    $database = $candidate;
                    break;
                }
            }
        }

        if ($database instanceof PDO) {
            $this->repository = new PdoLogRepository(
                $database,
                $this->config['table'] ?? 'prefab_logs',
            );
        }
    }

    public function record(LogEntry|array $entry): int|string
    {
        $entry = is_array($entry) ? LogEntry::fromArray($entry) : $entry;

        if ($entry->action === '' || $entry->subjectType === '') {
            throw new InvalidArgumentException(
                'Log entry requires action and subject type.',
            );
        }

        return $this->repo()->record($entry);
    }

    public function find(int|string $id): ?array
    {
        return $this->repo()->find($id);
    }

    /** @return array<int, array> */
    public function recent(int $limit = 100, int $offset = 0): array
    {
        return $this->repo()->recent($limit, $offset);
    }

    public function forSubject(
        string $subjectType,
        int|string $subjectId,
        int $limit = 100,
    ): array {
        return $this->repo()->forSubject($subjectType, $subjectId, $limit);
    }

    public function forActor(int|string $actorId, int $limit = 100): array
    {
        return $this->repo()->forActor($actorId, $limit);
    }

    public function humanRecent(
        int $limit = 100,
        int $offset = 0,
        ?callable $actorResolver = null,
        ?callable $subjectResolver = null,
    ): array {
        return (new HumanLogPresenter())->many(
            $this->recent($limit, $offset),
            $actorResolver,
            $subjectResolver,
        );
    }

    public function human(
        array $log,
        ?callable $actorResolver = null,
        ?callable $subjectResolver = null,
    ): array {
        return (new HumanLogPresenter())->present(
            $log,
            $actorResolver,
            $subjectResolver,
        );
    }

    private function repo(): LogRepositoryInterface
    {
        if (!$this->repository) {
            throw new RuntimeException(
                'Prefab Logs needs a repository or database configuration.',
            );
        }

        return $this->repository;
    }
}
