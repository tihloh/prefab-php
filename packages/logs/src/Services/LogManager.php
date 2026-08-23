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
 * Logs can run completely standalone with a custom repository, inherit the
 * shared Prefab database, or use its own module-local database. A local Logs
 * database never changes the configuration of Users, Auth, Permissions, or
 * other modules.
 *
 * The module stores one technical log record. Human-friendly output is created
 * on demand by HumanLogPresenter, so there is no duplicate log storage.
 */
final class LogManager
{
    private ?LogRepositoryInterface $repository = null;
    private array $config = [];

    /**
     * @param LogRepositoryInterface|array|null $repository Custom repository,
     *        local module configuration, or null to resolve defaults.
     */
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
     * Resolve the repository during module configuration passes.
     *
     * Resolution order:
     * 1. explicit repository
     * 2. local Logs configuration
     * 3. shared Prefab configuration
     * 4. compatible Prefab database resource
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

    /**
     * Store a structured log entry.
     *
     * @return int|string Repository-assigned log identifier.
     */
    public function record(LogEntry|array $entry): int|string
    {
        $entry = is_array($entry)
            ? LogEntry::fromArray($entry)
            : $entry;

        if ($entry->action === '' || $entry->subjectType === '') {
            throw new InvalidArgumentException(
                'Log entry requires action and subject type.',
            );
        }

        return $this->repo()->record($entry);
    }

    /** Find one technical log record by ID. */
    public function find(int|string $id): ?array
    {
        return $this->repo()->find($id);
    }

    /**
     * Return recent technical/audit log records.
     *
     * @return array<int, array>
     */
    public function recent(int $limit = 100, int $offset = 0): array
    {
        return $this->repo()->recent($limit, $offset);
    }

    /** Return logs for one subject, such as a user or document. */
    public function forSubject(
        string $subjectType,
        int|string $subjectId,
        int $limit = 100,
    ): array {
        return $this->repo()->forSubject(
            $subjectType,
            $subjectId,
            $limit,
        );
    }

    /** Return logs performed by one actor. */
    public function forActor(
        int|string $actorId,
        int $limit = 100,
    ): array {
        return $this->repo()->forActor($actorId, $limit);
    }

    /**
     * Return recent logs formatted for ordinary users.
     *
     * Raw technical records remain available through recent(). Resolvers are
     * optional and allow the host project to convert IDs into meaningful names.
     *
     * @param callable|null $actorResolver fn (int|string $id): ?string
     * @param callable|null $subjectResolver fn (string $type, int|string|null $id, array $log): ?string
     */
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

    /**
     * Convert one existing technical record to human-friendly output.
     */
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
