<?php

namespace Tihloh\Prefab\Users\Services;

use PDO;
use RuntimeException;
use Tihloh\Prefab\DatabaseInterface;
use Tihloh\Prefab\PdoDatabaseAdapter;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\DTOs\OperationResult;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\User\PrefabUser;

/**
 * Main service API for Prefab Users.
 *
 * Prefab Users remains standalone. It can use a custom provider, plain PDO,
 * Prefab Database, or any framework adapter implementing DatabaseInterface.
 * Plain PDO is normalized automatically, so existing applications do not need
 * to change their configuration style.
 */
final class UserManager
{
    private ?UserProviderInterface $provider = null;
    private ?DatabaseInterface $database = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;
    private ?object $autoLogger = null;
    private ?object $actorProvider = null;
    private ?object $groups = null;

    public function __construct(UserProviderInterface|array|null $provider = null)
    {
        if ($provider instanceof UserProviderInterface) {
            $this->provider = $provider;
            PrefabRuntime::recordResolution(
                'users',
                'user_provider',
                'module-local',
                ['provider' => $provider::class],
            );
        } elseif (is_array($provider)) {
            $this->config = $provider;
        }

        PrefabRuntime::register('users', $this);
    }

    /** Resolve missing resources and publish reusable capabilities. */
    public function prefabConfigure(): void
    {
        if (!$this->provider) {
            $provider = PrefabConfig::resolve(
                'users',
                'provider',
                $this->config,
            );

            if ($provider['value'] instanceof UserProviderInterface) {
                $this->provider = $provider['value'];
                PrefabRuntime::recordResolution(
                    'users',
                    'user_provider',
                    $provider['source'],
                    ['provider' => $this->provider::class],
                );
            }
        }

        if (!$this->provider) {
            [$database, $source, $details] = $this->resolveDatabase();

            if ($database) {
                $this->database = $database;
                PrefabRuntime::recordResolution(
                    'users',
                    'database',
                    $source,
                    $details,
                );

                $table = PrefabConfig::resolve(
                    'users',
                    'table',
                    $this->config,
                    'users',
                );
                $map = PrefabConfig::resolve(
                    'users',
                    'map',
                    $this->config,
                );
                $factory = PrefabConfig::resolve(
                    'users',
                    'factory',
                    $this->config,
                );

                $userMap = $map['value'] instanceof UserMap
                    ? $map['value']
                    : new UserMap((string) $table['value']);

                $this->provider = new PdoUserProvider(
                    $database,
                    $userMap,
                    $factory['value'] instanceof UserFactoryInterface
                        ? $factory['value']
                        : null,
                );

                PrefabRuntime::recordResolution(
                    'users',
                    'table',
                    $map['value'] instanceof UserMap
                        ? $map['source']
                        : $table['source'],
                    ['table' => $userMap->table],
                );
                PrefabRuntime::recordResolution(
                    'users',
                    'user_provider',
                    'database-provider',
                    ['provider' => PdoUserProvider::class],
                );
            }
        }

        if ($this->provider) {
            PrefabRuntime::provide(
                'user_provider',
                $this->provider,
                'prefab-users',
            );
        }

        if ($this->database) {
            PrefabRuntime::provide(
                'database',
                $this->database,
                'prefab-users',
                priority: -10,
                meta: [
                    'role' => 'users-database',
                    'driver' => $this->database->driver(),
                ],
            );
        }

        $groupEntry = PrefabRuntime::resolveEntry('group_provider');
        if ($groupEntry && $groupEntry['provider'] !== 'prefab-users') {
            $candidate = $groupEntry['value'];
            if (is_object($candidate) && method_exists($candidate, 'groupIdsForUser')) {
                $this->groups = $candidate;
                PrefabRuntime::recordResolution('users', 'group_provider', 'prefab-capability', ['provider' => $groupEntry['provider']]);
            }
        }

        if (!$this->groups && $this->database) {
            $this->groups = new GroupManager($this->database);
            PrefabRuntime::recordResolution('users', 'group_provider', 'users-database', ['provider' => GroupManager::class]);
        }

        if ($this->groups) {
            PrefabRuntime::provide('group_provider', $this->groups, 'prefab-users', priority: 10);
        }

        if (!$this->autoLogger) {
            $logger = PrefabRuntime::resolveEntry('logger');

            if ($logger) {
                $this->autoLogger = $logger['value'];
                PrefabRuntime::recordResolution(
                    'users',
                    'logger',
                    'prefab-capability',
                    ['provider' => $logger['provider']],
                );
            }
        }

        if (!$this->actorProvider) {
            $actor = PrefabRuntime::resolveEntry('actor_provider');

            if ($actor) {
                $this->actorProvider = $actor['value'];
                PrefabRuntime::recordResolution(
                    'users',
                    'actor_provider',
                    'prefab-capability',
                    ['provider' => $actor['provider']],
                );
            }
        }
    }

    /** @return array{0:?DatabaseInterface,1:string,2:array} */
    private function resolveDatabase(): array
    {
        $localDatabase = $this->asDatabase($this->config['database'] ?? null);

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

        $module = PrefabConfig::moduleOnly('users');
        $moduleDatabase = $this->asDatabase($module['database'] ?? null);

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

    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'user_provider' => $this->provider,
            'group_provider' => $this->groups,
            default => null,
        };
    }

    public function explain(): array
    {
        return PrefabRuntime::explain('users');
    }

    public function useContext(object $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function useEvents(object $events): self
    {
        $this->events = $events;
        return $this;
    }

    /** Simple group management; Permissions can enhance the same group provider. */
    public function groups(): object
    {
        if (!$this->groups) {
            $this->prefabConfigure();
        }

        return $this->groups
            ?? throw new RuntimeException('Prefab Users groups need a database or compatible group_provider capability.');
    }

    public function find(int|string $id): ?PrefabUser
    {
        return $this->provider()->find($id);
    }

    public function findByEmail(string $email): ?PrefabUser
    {
        return $this->provider()->findByEmail($email);
    }

    /** @return array<int, PrefabUser> */
    public function all(int $limit = 100, int $offset = 0): array
    {
        return $this->provider()->all($limit, $offset);
    }

    public function create(array $data, array $context = []): OperationResult
    {
        $user = $this->provider()->create($data);

        return $this->result(
            $user,
            $this->logPayload(
                'user.created',
                $user->id,
                "User {$user->name} was created.",
                $this->createdChanges($user->toArray()),
                $context,
            ),
        );
    }

    public function update(
        int|string $id,
        array $data,
        array $context = [],
    ): OperationResult {
        $before = $this->provider()->find($id);
        $user = $this->provider()->update($id, $data);

        return $this->result(
            $user,
            $this->logPayload(
                'user.updated',
                $user->id,
                "User {$user->name} was updated.",
                $this->diff(
                    $before?->toArray() ?? [],
                    $user->toArray(),
                ),
                $context,
            ),
        );
    }

    public function delete(int|string $id, array $context = []): OperationResult
    {
        $before = $this->provider()->find($id);
        $deleted = $this->provider()->delete($id);
        $name = $before?->name ?? (string) $id;

        return $this->result(
            $deleted,
            $this->logPayload(
                'user.deleted',
                $id,
                "User {$name} was deleted.",
                $before
                    ? $this->deletedChanges($before->toArray())
                    : [],
                $context,
            ),
        );
    }

    private function provider(): UserProviderInterface
    {
        if (!$this->provider) {
            throw new RuntimeException(
                'Prefab Users needs a provider or database capability/configuration.',
            );
        }

        return $this->provider;
    }

    private function result(mixed $data, array $log): OperationResult
    {
        if ($this->events && method_exists($this->events, 'dispatch')) {
            $this->events->dispatch('prefab.log', $log);
        } elseif (
            $this->autoLogger
            && method_exists($this->autoLogger, 'record')
        ) {
            $this->autoLogger->record($log);
        }

        return new OperationResult(
            data: $data,
            log: $log,
        );
    }

    private function context(array $context): array
    {
        $base = (
            $this->context
            && method_exists($this->context, 'logContext')
        ) ? $this->context->logContext() : [];

        if (!array_key_exists('actor_id', $base)) {
            $base['actor_id'] = (
                $this->actorProvider
                && method_exists($this->actorProvider, 'id')
            ) ? $this->actorProvider->id() : null;
        }

        if (
            !array_key_exists('actor_type', $base)
            && ($base['actor_id'] ?? null) !== null
        ) {
            $base['actor_type'] = 'user';
        }

        return array_replace($base, $context);
    }

    private function createdChanges(array $data): array
    {
        $changes = [];

        foreach ($data as $field => $value) {
            $changes[$field] = [
                'old' => null,
                'new' => $value,
            ];
        }

        return $changes;
    }

    private function deletedChanges(array $data): array
    {
        $changes = [];

        foreach ($data as $field => $value) {
            $changes[$field] = [
                'old' => $value,
                'new' => null,
            ];
        }

        return $changes;
    }

    private function diff(array $before, array $after): array
    {
        $changes = [];
        $fields = array_unique([
            ...array_keys($before),
            ...array_keys($after),
        ]);

        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($old !== $new) {
                $changes[$field] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        return $changes;
    }

    private function logPayload(
        string $action,
        int|string $subjectId,
        string $message,
        array $changes,
        array $context,
    ): array {
        $context = $this->context($context);

        return [
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $subjectId,
            'actor_type' => $context['actor_type'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
            'message' => $message,
            'changes' => $changes,
            'metadata' => $context['metadata'] ?? [],
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
