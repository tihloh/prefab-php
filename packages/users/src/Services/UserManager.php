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
 * Main service API for the Prefab Users module.
 *
 * The manager can operate with an explicitly supplied UserProviderInterface or
 * configure a PDO-backed provider from module/common Prefab configuration.
 *
 * Integration is automatic when compatible Prefab modules are present:
 * - Auth can provide the current actor for generated activity logs.
 * - Logs can receive generated CRUD activity automatically.
 *
 * Explicit constructor configuration affects this module only and never writes
 * values back into the shared Prefab configuration.
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
     * @param UserProviderInterface|array|null $provider A custom provider, local
     *        module configuration array, or null to use Prefab/common defaults.
     */
    public function __construct(UserProviderInterface|array|null $provider = null)
    {
        if ($provider instanceof UserProviderInterface) {
            $this->provider = $provider;
        } elseif (is_array($provider)) {
            $this->config = $provider;
        }

        PrefabRuntime::register('users', $this);
    }

    /**
     * Resolves missing configuration and compatible module integrations.
     *
     * This method is called by PrefabRuntime during module declaration passes.
     * Normal CRUD operations use the resolved references directly afterwards.
     */
    public function prefabConfigure(): void
    {
        if (!$this->provider) {
            $configuredProvider = $this->config['provider']
                ?? PrefabConfig::module('users', 'provider');

            if ($configuredProvider instanceof UserProviderInterface) {
                $this->provider = $configuredProvider;
            } else {
                $database = $this->config['database']
                    ?? PrefabConfig::module('users', 'database');

                if ($database instanceof PDO) {
                    $this->database = $database;

                    $table = $this->config['table']
                        ?? PrefabConfig::module('users', 'table', 'users');

                    $map = $this->config['map']
                        ?? PrefabConfig::module('users', 'map');

                    if (!$map instanceof UserMap) {
                        $map = new UserMap((string) $table);
                    }

                    $factory = $this->config['factory']
                        ?? PrefabConfig::module('users', 'factory');

                    $this->provider = new PdoUserProvider(
                        $database,
                        $map,
                        $factory instanceof UserFactoryInterface ? $factory : null,
                    );
                }
            }
        }

        $this->autoLogger ??= PrefabRuntime::get('logs');
        $this->actorProvider ??= PrefabRuntime::get('auth');
    }

    /**
     * Exposes a resolved resource to another compatible Prefab module.
     */
    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'user_provider' => $this->provider,
            default => null,
        };
    }

    /** Attach a project-specific context provider. */
    public function useContext(object $context): self
    {
        $this->context = $context;
        return $this;
    }

    /** Attach an external event dispatcher. */
    public function useEvents(object $events): self
    {
        $this->events = $events;
        return $this;
    }

    /** Find a user by primary identifier. */
    public function find(int|string $id): ?PrefabUser
    {
        return $this->provider()->find($id);
    }

    /** Find a user by email address. */
    public function findByEmail(string $email): ?PrefabUser
    {
        return $this->provider()->findByEmail($email);
    }

    /**
     * Return users for management/list UIs.
     *
     * @return array<int, PrefabUser>
     */
    public function all(int $limit = 100, int $offset = 0): array
    {
        return $this->provider()->all($limit, $offset);
    }

    /**
     * Create a user and return both the user and its structured activity log.
     */
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

    /**
     * Update a user and include a field-level before/after change set.
     */
    public function update(int|string $id, array $data, array $context = []): OperationResult
    {
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

    /** Delete a user when the configured provider allows deletion. */
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

        return new OperationResult(
            data: $data,
            log: $log,
        );
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
