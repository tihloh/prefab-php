<?php

namespace Tihloh\Prefab\Users\Services;

use PDO;
use RuntimeException;
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
 * Prefab Users is standalone. It can use an explicitly supplied user provider,
 * a PDO supplied through direct/module/common configuration, or an automatically
 * discovered database capability such as Prefab Database.
 *
 * Configuration priority for each setting:
 * 1. direct UserManager configuration;
 * 2. PrefabConfig modules.users configuration;
 * 3. common PrefabConfig configuration;
 * 4. compatible Prefab capability;
 * 5. Users' internal default;
 * 6. clear error when a required resource remains unresolved.
 */
final class UserManager
{
    private ?UserProviderInterface $provider = null;
    private ?PDO $database = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;
    private ?object $autoLogger = null;
    private ?object $actorProvider = null;

    /**
     * @param UserProviderInterface|array|null $provider Custom provider, direct
     *        module configuration, or null for automatic/shared configuration.
     */
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

    /**
     * Resolve missing resources and publish the user-provider capability.
     *
     * This runs during module declaration/configuration passes. Once resolved,
     * normal CRUD calls use cached direct references.
     */
    public function prefabConfigure(): void
    {
        if (!$this->provider) {
            $provider = PrefabConfig::resolve('users', 'provider', $this->config);

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
            $database = PrefabConfig::resolve('users', 'database', $this->config);
            $pdo = $database['value'] instanceof PDO ? $database['value'] : null;
            $databaseSource = $database['source'];
            $databaseDetails = [];

            if (!$pdo) {
                $connection = PrefabConfig::resolve('users', 'connection', $this->config);

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
                PrefabRuntime::recordResolution(
                    'users',
                    'database',
                    $databaseSource,
                    $databaseDetails,
                );

                $table = PrefabConfig::resolve('users', 'table', $this->config, 'users');
                $map = PrefabConfig::resolve('users', 'map', $this->config);
                $factory = PrefabConfig::resolve('users', 'factory', $this->config);

                $userMap = $map['value'] instanceof UserMap
                    ? $map['value']
                    : new UserMap((string) $table['value']);

                $this->provider = new PdoUserProvider(
                    $pdo,
                    $userMap,
                    $factory['value'] instanceof UserFactoryInterface
                        ? $factory['value']
                        : null,
                );

                PrefabRuntime::recordResolution(
                    'users',
                    'table',
                    $map['value'] instanceof UserMap ? $map['source'] : $table['source'],
                    ['table' => $userMap->table],
                );
                PrefabRuntime::recordResolution(
                    'users',
                    'user_provider',
                    'pdo-provider',
                    ['provider' => PdoUserProvider::class],
                );
            }
        }

        if ($this->provider) {
            PrefabRuntime::provide('user_provider', $this->provider, 'prefab-users');
        }

        if ($this->database) {
            PrefabRuntime::provide(
                'database',
                $this->database,
                'prefab-users',
                priority: -10,
                meta: ['role' => 'users-database'],
            );
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

    /** Backward-compatible resource exposure for older Prefab integrations. */
    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'user_provider' => $this->provider,
            default => null,
        };
    }

    /** Explain how this module resolved its resources and integrations. */
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
                $this->diff($before?->toArray() ?? [], $user->toArray()),
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
                $before ? $this->deletedChanges($before->toArray()) : [],
                $context,
            ),
        );
    }

    private function provider(): UserProviderInterface
    {
        if (!$this->provider) {
            throw new RuntimeException(
                'Prefab Users needs a provider or database configuration.',
            );
        }

        return $this->provider;
    }

    private function result(mixed $data, array $log): OperationResult
    {
        if ($this->events && method_exists($this->events, 'dispatch')) {
            $this->events->dispatch('prefab.log', $log);
        } elseif ($this->autoLogger && method_exists($this->autoLogger, 'record')) {
            $this->autoLogger->record($log);
        }

        return new OperationResult(data: $data, log: $log);
    }

    private function context(array $context): array
    {
        $base = ($this->context && method_exists($this->context, 'logContext'))
            ? $this->context->logContext()
            : [];

        if (!array_key_exists('actor_id', $base)) {
            $base['actor_id'] = (
                $this->actorProvider
                && method_exists($this->actorProvider, 'id')
            ) ? $this->actorProvider->id() : null;
        }

        if (!array_key_exists('actor_type', $base) && ($base['actor_id'] ?? null) !== null) {
            $base['actor_type'] = 'user';
        }

        return array_replace($base, $context);
    }

    private function createdChanges(array $data): array
    {
        $changes = [];

        foreach ($data as $field => $value) {
            $changes[$field] = ['old' => null, 'new' => $value];
        }

        return $changes;
    }

    private function deletedChanges(array $data): array
    {
        $changes = [];

        foreach ($data as $field => $value) {
            $changes[$field] = ['old' => $value, 'new' => null];
        }

        return $changes;
    }

    private function diff(array $before, array $after): array
    {
        $changes = [];
        $fields = array_unique([...array_keys($before), ...array_keys($after)]);

        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
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
