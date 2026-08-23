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
 * Logs is standalone. It can use an explicit repository/database, module/common
 * PrefabConfig, a named database capability, or the default compatible database
 * capability. Once storage is resolved, Logs publishes the `logger` capability
 * so other Prefab modules can send activity without depending on this package.
 */
final class LogManager
{
    private ?LogRepositoryInterface $repository = null;
    private ?PDO $database = null;
    private array $config = [];

    public function __construct(LogRepositoryInterface|array|null $repository = null)
    {
        if ($repository instanceof LogRepositoryInterface) {
            $this->repository = $repository;
            PrefabRuntime::recordResolution(
                'logs',
                'repository',
                'module-local',
                ['provider' => $repository::class],
            );
        } elseif (is_array($repository)) {
            $this->config = $repository;
        }

        PrefabRuntime::register('logs', $this);
    }

    /** Resolve storage once during module declaration/configuration passes. */
    public function prefabConfigure(): void
    {
        if (!$this->repository) {
            $repository = PrefabConfig::resolve('logs', 'repository', $this->config);

            if ($repository['value'] instanceof LogRepositoryInterface) {
                $this->repository = $repository['value'];
                PrefabRuntime::recordResolution(
                    'logs',
                    'repository',
                    $repository['source'],
                    ['provider' => $this->repository::class],
                );
            }
        }

        if (!$this->repository) {
            $database = PrefabConfig::resolve('logs', 'database', $this->config);
            $pdo = $database['value'] instanceof PDO ? $database['value'] : null;
            $databaseSource = $database['source'];
            $databaseDetails = [];

            if (!$pdo) {
                $connection = PrefabConfig::resolve('logs', 'connection', $this->config);

                if (is_string($connection['value']) && $connection['value'] !== '') {
                    $entry = PrefabRuntime::resolveEntry(
                        'database.connection.' . $connection['value'],
                    );

                    if ($entry && $entry['value'] instanceof PDO) {
                        $pdo = $entry['value'];
                        $databaseSource = 'prefab-capability';
                        $databaseDetails = [
                            'provider' => $entry['provider'],
                            'connection' => $connection['value'],
                        ];
                    }
                }
            }

            if (!$pdo) {
                $entry = PrefabRuntime::resolveEntry('database');

                if ($entry && $entry['value'] instanceof PDO) {
                    $pdo = $entry['value'];
                    $databaseSource = 'prefab-capability';
                    $databaseDetails = [
                        'provider' => $entry['provider'],
                        ...($entry['meta'] ?? []),
                    ];
                }
            }

            if ($pdo) {
                $this->database = $pdo;
                $table = PrefabConfig::resolve(
                    'logs',
                    'table',
                    $this->config,
                    'prefab_logs',
                );

                $this->repository = new PdoLogRepository(
                    $pdo,
                    (string) $table['value'],
                );

                PrefabRuntime::recordResolution(
                    'logs',
                    'database',
                    $databaseSource,
                    $databaseDetails,
                );
                PrefabRuntime::recordResolution(
                    'logs',
                    'table',
                    $table['source'],
                    ['table' => (string) $table['value']],
                );
                PrefabRuntime::recordResolution(
                    'logs',
                    'repository',
                    'pdo-repository',
                    ['provider' => PdoLogRepository::class],
                );
            }
        }

        if ($this->repository) {
            PrefabRuntime::provide('logger', $this, 'prefab-logs');
        }
    }

    /** Explain how Logs resolved storage and integrations. */
    public function explain(): array
    {
        return PrefabRuntime::explain('logs');
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
                'Prefab Logs needs a repository or database capability/configuration.',
            );
        }

        return $this->repository;
    }
}
