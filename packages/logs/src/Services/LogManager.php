<?php

namespace Tihloh\Prefab\Logs\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Tihloh\Prefab\DatabaseInterface;
use Tihloh\Prefab\PdoDatabaseAdapter;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Presenters\HumanLogPresenter;
use Tihloh\Prefab\Logs\Repositories\PdoLogRepository;

/**
 * Main service API for structured Prefab activity/audit logs.
 *
 * Logs remains standalone. Storage may be a custom repository, plain PDO,
 * Prefab Database, or any compatible DatabaseInterface implementation.
 */
final class LogManager
{
    private ?LogRepositoryInterface $repository = null;
    private ?DatabaseInterface $database = null;
    private array $config = [];
    private mixed $background = null;
    private bool $backgroundEnabled = false;

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
            $repository = PrefabConfig::resolve(
                'logs',
                'repository',
                $this->config,
            );

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
            [$database, $source, $details] = $this->resolveDatabase();

            if ($database) {
                $this->database = $database;
                $table = PrefabConfig::resolve(
                    'logs',
                    'table',
                    $this->config,
                    'prefab_logs',
                );

                $this->repository = new PdoLogRepository(
                    $database,
                    (string) $table['value'],
                );

                PrefabRuntime::recordResolution(
                    'logs',
                    'database',
                    $source,
                    $details,
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
                    'database-repository',
                    ['provider' => PdoLogRepository::class],
                );
            }
        }

        if ($this->repository) {
            PrefabRuntime::provide(
                'logger',
                $this,
                'prefab-logs',
            );
        }

        $background = PrefabConfig::resolve(
            'logs',
            'background',
            $this->config,
            false,
        );
        $this->backgroundEnabled = (bool) $background['value'];

        if ($this->backgroundEnabled) {
            $entry = PrefabRuntime::resolveEntry('background');
            $service = $entry['value'] ?? null;

            if (is_object($service)
                && method_exists($service, 'handler')
                && method_exists($service, 'run')
            ) {
                $this->background = $service;
                $service->handler('logs.record', function (array $payload): void {
                    $this->recordDirect(LogEntry::fromArray($payload));
                });

                PrefabRuntime::recordResolution(
                    'logs',
                    'background',
                    'prefab-capability',
                    ['provider' => $entry['provider'] ?? 'unknown'],
                );
            } else {
                $this->background = null;
                PrefabRuntime::recordResolution(
                    'logs',
                    'background',
                    'unresolved',
                    ['requested' => true],
                );
            }
        } else {
            $this->background = null;
            PrefabRuntime::recordResolution(
                'logs',
                'background',
                $background['source'],
                ['enabled' => false],
            );
        }
    }

    /** @return array{0:?DatabaseInterface,1:string,2:array} */
    private function resolveDatabase(): array
    {
        $localDatabase = $this->asDatabase(
            $this->config['database'] ?? null,
        );

        if ($localDatabase) {
            return [
                $localDatabase,
                'module-local',
                ['driver' => $localDatabase->driver()],
            ];
        }

        if (
            isset($this->config['connection'])
            && is_string($this->config['connection'])
        ) {
            return $this->namedConnection(
                $this->config['connection'],
                'module-local',
            );
        }

        $module = PrefabConfig::moduleOnly('logs');
        $moduleDatabase = $this->asDatabase(
            $module['database'] ?? null,
        );

        if ($moduleDatabase) {
            return [
                $moduleDatabase,
                'prefab-config-module',
                ['driver' => $moduleDatabase->driver()],
            ];
        }

        if (
            isset($module['connection'])
            && is_string($module['connection'])
        ) {
            return $this->namedConnection(
                $module['connection'],
                'prefab-config-module',
            );
        }

        $common = $this->asDatabase(PrefabConfig::get('database'));

        if ($common) {
            return [
                $common,
                'prefab-config-common',
                ['driver' => $common->driver()],
            ];
        }

        $entry = PrefabRuntime::resolveEntry('database');
        $capability = $entry
            ? $this->asDatabase($entry['value'])
            : null;

        if ($entry && $capability) {
            return [
                $capability,
                'prefab-capability',
                [
                    'provider' => $entry['provider'],
                    ...($entry['meta'] ?? []),
                ],
            ];
        }

        return [null, 'unresolved', []];
    }

    /** @return array{0:?DatabaseInterface,1:string,2:array} */
    private function namedConnection(string $name, string $source): array
    {
        $entry = PrefabRuntime::resolveEntry(
            'database.connection.' . $name,
        );
        $database = $entry
            ? $this->asDatabase($entry['value'])
            : null;

        if ($entry && $database) {
            return [
                $database,
                $source,
                [
                    'provider' => $entry['provider'],
                    'connection' => $name,
                    'driver' => $database->driver(),
                ],
            ];
        }

        return [
            null,
            $source,
            [
                'connection' => $name,
                'unresolved' => true,
            ],
        ];
    }

    private function asDatabase(mixed $value): ?DatabaseInterface
    {
        if ($value instanceof DatabaseInterface) {
            return $value;
        }

        return $value instanceof PDO
            ? new PdoDatabaseAdapter($value)
            : null;
    }

    /** Explain how Logs resolved storage and integrations. */
    public function explain(): array
    {
        return PrefabRuntime::explain('logs');
    }

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

        if ($entry->occurredAt === null || trim($entry->occurredAt) === '') {
            $entry->occurredAt = self::nowUtc();
        }

        if ($this->backgroundEnabled) {
            if (!is_object($this->background)) {
                $this->prefabConfigure();
            }

            if (!is_object($this->background) || !method_exists($this->background, 'run')) {
                throw new RuntimeException(
                    'Prefab Logs background mode requires tihloh/prefab-background and an active BackgroundManager.'
                );
            }

            $receipt = $this->background->run(
                'logs.record',
                $entry->toArray(),
                $entry->occurredAt,
            );

            if (!is_object($receipt) || !isset($receipt->id)) {
                throw new RuntimeException('Prefab Background returned an invalid receipt for Logs.');
            }

            return (string) $receipt->id;
        }

        return $this->recordDirect($entry);
    }

    private function recordDirect(LogEntry $entry): int|string
    {
        return $this->repo()->record($entry);
    }

    private static function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d\\TH:i:s.u\\Z');
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
        return $this->repo()->forSubject(
            $subjectType,
            $subjectId,
            $limit,
        );
    }

    public function forActor(
        int|string $actorId,
        int $limit = 100,
    ): array {
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
            $this->prefabConfigure();
        }

        if (!$this->repository) {
            throw new RuntimeException(
                'Prefab Logs needs a repository or database capability/configuration.',
            );
        }

        return $this->repository;
    }
}
