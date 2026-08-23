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
 * Permissions is standalone. Definitions can be inline, PHP, or JSON. Storage
 * can come from direct/module/common configuration or an optional database
 * capability. Database and connection-name settings are treated as two ways of
 * configuring the same resource so a module-specific connection cannot be
 * accidentally masked by a common database.
 */
final class PermissionManager
{
    private ?PermissionDefinitions $definitions = null;
    private ?PermissionStoreInterface $store = null;
    private ?PDO $database = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;

    public function __construct(
        PermissionDefinitions|array|string|null $definitions = null,
        ?PermissionStoreInterface $store = null,
    ) {
        if ($definitions instanceof PermissionDefinitions) {
            $this->definitions = $definitions;
            PrefabRuntime::recordResolution('permissions', 'definitions', 'module-local');
        } elseif (is_string($definitions)) {
            $this->config = ['definitions' => $definitions];
        } elseif (is_array($definitions)) {
            $this->config = $definitions;
        }

        if ($store) {
            $this->store = $store;
            PrefabRuntime::recordResolution(
                'permissions',
                'store',
                'module-local',
                ['provider' => $store::class],
            );
        }

        PrefabRuntime::register('permissions', $this);
    }

    /** Resolve definitions/storage and publish reusable capabilities. */
    public function prefabConfigure(): void
    {
        if (!$this->definitions) {
            $definitions = PrefabConfig::resolve(
                'permissions',
                'definitions',
                $this->config,
            );

            $source = $definitions['value'];
            $this->definitions = $source instanceof PermissionDefinitions
                || is_array($source)
                || is_string($source)
                    ? PermissionDefinitions::from($source)
                    : new PermissionDefinitions([]);

            PrefabRuntime::recordResolution(
                'permissions',
                'definitions',
                $definitions['source'],
                ['count' => count($this->definitions->all())],
            );
        }

        if (!$this->store) {
            $store = PrefabConfig::resolve('permissions', 'store', $this->config);

            if ($store['value'] instanceof PermissionStoreInterface) {
                $this->store = $store['value'];
                PrefabRuntime::recordResolution(
                    'permissions',
                    'store',
                    $store['source'],
                    ['provider' => $this->store::class],
                );
            }
        }

        if (!$this->store) {
            [$pdo, $source, $details] = $this->resolveDatabase();

            if ($pdo) {
                $this->database = $pdo;
                $table = PrefabConfig::resolve(
                    'permissions',
                    'table',
                    $this->config,
                    'prefab_subject_permissions',
                );

                $this->store = new PdoPermissionStore(
                    $pdo,
                    (string) $table['value'],
                );

                PrefabRuntime::recordResolution(
                    'permissions',
                    'database',
                    $source,
                    $details,
                );
                PrefabRuntime::recordResolution(
                    'permissions',
                    'table',
                    $table['source'],
                    ['table' => (string) $table['value']],
                );
                PrefabRuntime::recordResolution(
                    'permissions',
                    'store',
                    'pdo-store',
                    ['provider' => PdoPermissionStore::class],
                );
            }
        }

        if ($this->store) {
            PrefabRuntime::provide(
                'permission_store',
                $this->store,
                'prefab-permissions',
            );
        }

        if ($this->database) {
            PrefabRuntime::provide(
                'database',
                $this->database,
                'prefab-permissions',
                priority: -20,
                meta: ['role' => 'permissions-database'],
            );
        }
    }

    /** @return array{0:?PDO,1:string,2:array} */
    private function resolveDatabase(): array
    {
        if (($this->config['database'] ?? null) instanceof PDO) {
            return [$this->config['database'], 'module-local', []];
        }

        if (isset($this->config['connection']) && is_string($this->config['connection'])) {
            return $this->namedConnection($this->config['connection'], 'module-local');
        }

        $module = PrefabConfig::moduleOnly('permissions');

        if (($module['database'] ?? null) instanceof PDO) {
            return [$module['database'], 'prefab-config-module', []];
        }

        if (isset($module['connection']) && is_string($module['connection'])) {
            return $this->namedConnection($module['connection'], 'prefab-config-module');
        }

        $common = PrefabConfig::get('database');

        if ($common instanceof PDO) {
            return [$common, 'prefab-config-common', []];
        }

        $entry = PrefabRuntime::resolveEntry('database');

        if ($entry && $entry['value'] instanceof PDO) {
            return [
                $entry['value'],
                'prefab-capability',
                [
                    'provider' => $entry['provider'],
                    ...($entry['meta'] ?? []),
                ],
            ];
        }

        return [null, 'unresolved', []];
    }

    /** @return array{0:?PDO,1:string,2:array} */
    private function namedConnection(string $name, string $source): array
    {
        $entry = PrefabRuntime::resolveEntry('database.connection.' . $name);

        if ($entry && $entry['value'] instanceof PDO) {
            return [
                $entry['value'],
                $source,
                ['provider' => $entry['provider'], 'connection' => $name],
            ];
        }

        return [null, $source, ['connection' => $name, 'unresolved' => true]];
    }

    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'permission_store' => $this->store,
            default => null,
        };
    }

    public function explain(): array
    {
        return PrefabRuntime::explain('permissions');
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

    public function can(
        PermissionSubjectInterface|int|string $subject,
        string $permission,
        array $groupIds = [],
    ): bool {
        return $this->resolve($subject, $permission, $groupIds)->allowed;
    }

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

        $userOverrides = $store->get('user', $subjectId);

        if (array_key_exists($permission, $userOverrides)) {
            return new PermissionResult((bool) $userOverrides[$permission], 'user');
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

        if ($allowingGroups !== []) {
            return new PermissionResult(true, 'group', $allowingGroups, $denyingGroups);
        }

        if ($denyingGroups !== []) {
            return new PermissionResult(false, 'group', $denyingGroups, $denyingGroups);
        }

        return new PermissionResult($definitions->default($permission), 'default');
    }

    public function overridesFor(string $type, int|string $id): array
    {
        return $this->store()->get($type, $id);
    }

    /** @return array<string, PermissionResult> */
    public function resolvedFor(
        PermissionSubjectInterface|int|string $subject,
        array $groups = [],
    ): array {
        $results = [];

        foreach (array_keys($this->defs()->all()) as $permission) {
            $results[$permission] = $this->resolve($subject, $permission, $groups);
        }

        return $results;
    }

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
        $store->put($type, $id, $definitions->validateOverrides($overrides));

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

    public function clearAll(string $type, int|string $id): void
    {
        $this->store()->remove($type, $id);
    }

    public function definitions(): array
    {
        return $this->defs()->all();
    }

    public function definition(string $permission): ?array
    {
        return $this->defs()->get($permission);
    }

    public function defined(string $permission): bool
    {
        return $this->defs()->has($permission);
    }

    private function defs(): PermissionDefinitions
    {
        if (!$this->definitions) {
            $this->prefabConfigure();
        }

        return $this->definitions ?? new PermissionDefinitions([]);
    }

    private function store(): PermissionStoreInterface
    {
        if (!$this->store) {
            throw new RuntimeException(
                'Prefab Permissions needs a store or database capability/configuration.',
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
        $base = ($this->context && method_exists($this->context, 'logContext'))
            ? $this->context->logContext()
            : [];

        if (!array_key_exists('actor_id', $base)) {
            $base['actor_id'] = PrefabRuntime::actorId();
        }

        if (!array_key_exists('actor_type', $base) && ($base['actor_id'] ?? null) !== null) {
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
                $permission => ['old' => $old, 'new' => $new],
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
