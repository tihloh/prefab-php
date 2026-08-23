<?php

namespace Tihloh\Prefab\Permissions\Services;

use PDO;
use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\DTOs\OperationResult;
use Tihloh\Prefab\Permissions\DTOs\PermissionResult;
use Tihloh\Prefab\Permissions\Repositories\PdoPermissionStore;

/**
 * Main authorization service for Prefab Permissions.
 *
 * Effective permissions are resolved in this order:
 * 1. explicit user override
 * 2. group permissions
 * 3. permission definition default
 *
 * The module is standalone. When no store/database is explicitly configured,
 * it may use shared Prefab configuration or inherit a compatible database
 * resource exposed by Prefab Users.
 */
final class PermissionManager
{
    private ?PermissionDefinitions $definitions = null;
    private ?PermissionStoreInterface $store = null;
    private ?PDO $database = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;

    /**
     * @param PermissionDefinitions|array|null $definitions Definitions object,
     *        local module configuration array, or null to resolve defaults.
     */
    public function __construct(
        PermissionDefinitions|array|null $definitions = null,
        ?PermissionStoreInterface $store = null,
    ) {
        if ($definitions instanceof PermissionDefinitions) {
            $this->definitions = $definitions;
        } elseif (is_array($definitions)) {
            $this->config = $definitions;
        }

        if ($store) {
            $this->store = $store;
        }

        PrefabRuntime::register('permissions', $this);
    }

    /** Resolve definitions, storage, and compatible database resources. */
    public function prefabConfigure(): void
    {
        if (!$this->definitions) {
            $definitions = $this->config['definitions']
                ?? PrefabConfig::module('permissions', 'definitions');

            if ($definitions instanceof PermissionDefinitions) {
                $this->definitions = $definitions;
            } elseif (is_array($definitions)) {
                $this->definitions = new PermissionDefinitions($definitions);
            } else {
                $this->definitions = new PermissionDefinitions([]);
            }
        }

        if ($this->store) {
            return;
        }

        $configuredStore = $this->config['store']
            ?? PrefabConfig::module('permissions', 'store');

        if ($configuredStore instanceof PermissionStoreInterface) {
            $this->store = $configuredStore;
            return;
        }

        $database = $this->config['database']
            ?? PrefabConfig::module('permissions', 'database');

        if (!$database instanceof PDO) {
            $users = PrefabRuntime::get('users');

            if ($users && method_exists($users, 'prefabResource')) {
                $database = $users->prefabResource('database');
            }
        }

        if ($database instanceof PDO) {
            $this->database = $database;
            $this->store = new PdoPermissionStore(
                $database,
                $this->config['table'] ?? 'prefab_subject_permissions',
            );
        }
    }

    /** Expose compatible resolved resources to other Prefab modules. */
    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'permission_store' => $this->store,
            default => null,
        };
    }

    /** Attach a project-specific logging/request context provider. */
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

    /** Check whether a subject currently has a permission. */
    public function can(
        PermissionSubjectInterface|int|string $subject,
        string $permission,
        array $groupIds = [],
    ): bool {
        return $this->resolve(
            $subject,
            $permission,
            $groupIds,
        )->allowed;
    }

    /**
     * Resolve an effective permission and report where the decision came from.
     */
    public function resolve(
        PermissionSubjectInterface|int|string $subject,
        string $permission,
        array $groupIds = [],
    ): PermissionResult {
        $definitions = $this->defs();
        $store = $this->store();

        if (!$definitions->has($permission)) {
            return new PermissionResult(false, 'unknown');
        }

        if ($subject instanceof PermissionSubjectInterface) {
            $subjectId = $subject->permissionSubjectId();
            $groupIds = $subject->permissionGroupIds();
        } else {
            $subjectId = $subject;
        }

        // A user-specific value has the highest priority.
        $userOverrides = $store->get('user', $subjectId);

        if (array_key_exists($permission, $userOverrides)) {
            return new PermissionResult(
                (bool) $userOverrides[$permission],
                'user',
            );
        }

        $allowingGroups = [];
        $denyingGroups = [];

        foreach ($groupIds as $groupId) {
            $groupOverrides = $store->get('group', $groupId);

            if (!array_key_exists($permission, $groupOverrides)) {
                continue;
            }

            if ($groupOverrides[$permission] === true) {
                $allowingGroups[] = $groupId;
            } else {
                $denyingGroups[] = $groupId;
            }
        }

        // Current Prefab policy: an explicit allow from any group wins over
        // group denies. User-level overrides still take priority over all groups.
        if ($allowingGroups !== []) {
            return new PermissionResult(
                true,
                'group',
                $allowingGroups,
                $denyingGroups,
            );
        }

        if ($denyingGroups !== []) {
            return new PermissionResult(
                false,
                'group',
                $denyingGroups,
                $denyingGroups,
            );
        }

        return new PermissionResult(
            $definitions->default($permission),
            'default',
        );
    }

    /** Return raw overrides assigned to a user/group subject. */
    public function overridesFor(string $type, int|string $id): array
    {
        return $this->store()->get($type, $id);
    }

    /**
     * Resolve every defined permission for a subject; useful for management UIs.
     *
     * @return array<string, PermissionResult>
     */
    public function resolvedFor(
        PermissionSubjectInterface|int|string $subject,
        array $groups = [],
    ): array {
        $results = [];

        foreach (array_keys($this->defs()->all()) as $permission) {
            $results[$permission] = $this->resolve(
                $subject,
                $permission,
                $groups,
            );
        }

        return $results;
    }

    /** Set an explicit allow/deny override for a user or group. */
    public function set(
        string $type,
        int|string $id,
        string $permission,
        bool $value,
        array $context = [],
    ): OperationResult {
        $store = $this->store();
        $definitions = $this->defs();
        $overrides = $store->get($type, $id);

        $old = array_key_exists($permission, $overrides)
            ? (bool) $overrides[$permission]
            : null;

        $overrides[$permission] = $value;

        $store->put(
            $type,
            $id,
            $definitions->validateOverrides($overrides),
        );

        return $this->result(
            $value,
            $this->logPayload(
                $value ? 'permission.granted' : 'permission.denied',
                $type,
                $id,
                $permission,
                $old,
                $value,
                $context,
            ),
        );
    }

    /** Remove one explicit override so inheritance/default resolution applies. */
    public function clear(
        string $type,
        int|string $id,
        string $permission,
        array $context = [],
    ): OperationResult {
        $store = $this->store();
        $overrides = $store->get($type, $id);

        $old = array_key_exists($permission, $overrides)
            ? (bool) $overrides[$permission]
            : null;

        unset($overrides[$permission]);

        if ($overrides === []) {
            $store->remove($type, $id);
        } else {
            $store->put($type, $id, $overrides);
        }

        return $this->result(
            true,
            $this->logPayload(
                'permission.cleared',
                $type,
                $id,
                $permission,
                $old,
                null,
                $context,
            ),
        );
    }

    /** Remove all explicit overrides for a subject. */
    public function clearAll(string $type, int|string $id): void
    {
        $this->store()->remove($type, $id);
    }

    /** Return all permission definitions for UI/configuration use. */
    public function definitions(): array
    {
        return $this->defs()->all();
    }

    /** Return one permission definition. */
    public function definition(string $permission): ?array
    {
        return $this->defs()->get($permission);
    }

    /** Determine whether a permission key is defined. */
    public function defined(string $permission): bool
    {
        return $this->defs()->has($permission);
    }

    private function defs(): PermissionDefinitions
    {
        if (!$this->definitions) {
            $this->prefabConfigure();
        }

        return $this->definitions
            ?? new PermissionDefinitions([]);
    }

    private function store(): PermissionStoreInterface
    {
        if (!$this->store) {
            throw new RuntimeException(
                'Prefab Permissions needs a store/database configuration.',
            );
        }

        return $this->store;
    }

    private function result(mixed $data, array $log): OperationResult
    {
        if ($this->events && method_exists($this->events, 'dispatch')) {
            $this->events->dispatch('prefab.log', $log);
        } else {
            PrefabRuntime::emitLog($log);
        }

        return new OperationResult($data, $log);
    }

    private function logPayload(
        string $action,
        string $type,
        int|string $id,
        string $permission,
        ?bool $old,
        ?bool $new,
        array $context,
    ): array {
        $base = (
            $this->context
            && method_exists($this->context, 'logContext')
        ) ? $this->context->logContext() : [];

        if (!array_key_exists('actor_id', $base)) {
            $base['actor_id'] = PrefabRuntime::actorId();
        }

        if (
            !array_key_exists('actor_type', $base)
            && ($base['actor_id'] ?? null) !== null
        ) {
            $base['actor_type'] = 'user';
        }

        $context = array_replace($base, $context);

        $verb = match ($action) {
            'permission.granted' => 'granted to',
            'permission.denied' => 'denied for',
            default => 'cleared from',
        };

        $definition = $this->defs()->get($permission);
        $permissionName = $definition['name'] ?? null;

        return [
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'actor_type' => $context['actor_type'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
            'message' => "Permission {$permission} was {$verb} {$type} {$id}.",
            'changes' => [
                $permission => [
                    'old' => $old,
                    'new' => $new,
                ],
            ],
            'metadata' => array_merge(
                [
                    'permission' => $permission,
                    'permission_name' => $permissionName,
                ],
                $context['metadata'] ?? [],
            ),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
