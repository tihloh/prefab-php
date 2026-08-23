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

final class LogManager
{
    private ?LogRepositoryInterface $repository = null;
    private array $config = [];

    public function __construct(LogRepositoryInterface|array|null $repository = null)
    {
        if ($repository instanceof LogRepositoryInterface) $this->repository = $repository;
        elseif (is_array($repository)) $this->config = $repository;
        PrefabRuntime::register('logs', $this);
    }

    public function prefabConfigure(): void
    {
        if ($this->repository) return;
        $configured = $this->config['repository'] ?? PrefabConfig::module('logs', 'repository');
        if ($configured instanceof LogRepositoryInterface) { $this->repository = $configured; return; }

        $db = $this->config['database'] ?? PrefabConfig::module('logs', 'database');
        if (!$db instanceof PDO) {
            foreach (['users','permissions'] as $source) {
                $module = PrefabRuntime::get($source);
                if ($module && method_exists($module, 'prefabResource')) {
                    $candidate = $module->prefabResource('database');
                    if ($candidate instanceof PDO) { $db = $candidate; break; }
                }
            }
        }
        if ($db instanceof PDO) $this->repository = new PdoLogRepository($db, $this->config['table'] ?? 'prefab_logs');
    }

    public function record(LogEntry|array $entry): int|string
    {
        $entry = is_array($entry) ? LogEntry::fromArray($entry) : $entry;
        if ($entry->action === '' || $entry->subjectType === '') throw new InvalidArgumentException('Log entry requires action and subject type.');
        return $this->repo()->record($entry);
    }

    public function find(int|string $id): ?array { return $this->repo()->find($id); }
    public function recent(int $limit = 100, int $offset = 0): array { return $this->repo()->recent($limit, $offset); }
    public function forSubject(string $subjectType, int|string $subjectId, int $limit = 100): array { return $this->repo()->forSubject($subjectType, $subjectId, $limit); }
    public function forActor(int|string $actorId, int $limit = 100): array { return $this->repo()->forActor($actorId, $limit); }

    /** Human/ordinary-user view. Raw technical logs remain unchanged in storage. */
    public function humanRecent(int $limit = 100, int $offset = 0, ?callable $actorResolver = null, ?callable $subjectResolver = null): array
    {
        return (new HumanLogPresenter())->many($this->recent($limit, $offset), $actorResolver, $subjectResolver);
    }

    public function human(array $log, ?callable $actorResolver = null, ?callable $subjectResolver = null): array
    {
        return (new HumanLogPresenter())->present($log, $actorResolver, $subjectResolver);
    }

    private function repo(): LogRepositoryInterface
    {
        if (!$this->repository) throw new RuntimeException('Prefab Logs needs a repository or database configuration.');
        return $this->repository;
    }
}
