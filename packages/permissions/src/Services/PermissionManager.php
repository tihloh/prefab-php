<?php

namespace Tihloh\Prefab\Permissions\Services;

use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\DTOs\OperationResult;
use Tihloh\Prefab\Permissions\DTOs\PermissionResult;

final class PermissionManager
{
    public function __construct(
        private PermissionDefinitions $definitions,
        private PermissionStoreInterface $store,
    ) {}

    public function can(PermissionSubjectInterface|int|string $subject, string $permission, array $groupIds = []): bool
    {
        return $this->resolve($subject, $permission, $groupIds)->allowed;
    }

    public function resolve(PermissionSubjectInterface|int|string $subject, string $permission, array $groupIds = []): PermissionResult
    {
        if (!$this->definitions->has($permission)) {
            return new PermissionResult(false, 'unknown');
        }

        if ($subject instanceof PermissionSubjectInterface) {
            $subjectId = $subject->permissionSubjectId();
            $groupIds = $subject->permissionGroupIds();
        } else {
            $subjectId = $subject;
        }

        $userPermissions = $this->store->get('user', $subjectId);
        if (array_key_exists($permission, $userPermissions)) {
            return new PermissionResult((bool) $userPermissions[$permission], 'user');
        }

        $allows = [];
        $denies = [];
        foreach ($groupIds as $groupId) {
            $groupPermissions = $this->store->get('group', $groupId);
            if (!array_key_exists($permission, $groupPermissions)) {
                continue;
            }
            if ($groupPermissions[$permission] === true) {
                $allows[] = $groupId;
            } else {
                $denies[] = $groupId;
            }
        }

        if ($allows !== []) {
            return new PermissionResult(true, 'group', $allows, $denies);
        }
        if ($denies !== []) {
            return new PermissionResult(false, 'group', $denies, $denies);
        }

        return new PermissionResult($this->definitions->default($permission), 'default');
    }

    public function overridesFor(string $subjectType, int|string $subjectId): array
    {
        return $this->store->get($subjectType, $subjectId);
    }

    public function resolvedFor(PermissionSubjectInterface|int|string $subject, array $groupIds = []): array
    {
        $resolved = [];
        foreach (array_keys($this->definitions->all()) as $permission) {
            $resolved[$permission] = $this->resolve($subject, $permission, $groupIds);
        }
        return $resolved;
    }

    public function set(string $subjectType, int|string $subjectId, string $permission, bool $value, array $context = []): OperationResult
    {
        $permissions = $this->store->get($subjectType, $subjectId);
        $old = array_key_exists($permission, $permissions) ? (bool)$permissions[$permission] : null;
        $permissions[$permission] = $value;
        $this->store->put($subjectType, $subjectId, $this->definitions->validateOverrides($permissions));

        return new OperationResult($value, $this->logPayload(
            $value ? 'permission.granted' : 'permission.denied', $subjectType, $subjectId,
            $permission, $old, $value, $context
        ));
    }

    public function clear(string $subjectType, int|string $subjectId, string $permission, array $context = []): OperationResult
    {
        $permissions = $this->store->get($subjectType, $subjectId);
        $old = array_key_exists($permission, $permissions) ? (bool)$permissions[$permission] : null;
        unset($permissions[$permission]);
        if ($permissions === []) {
            $this->store->remove($subjectType, $subjectId);
        } else {
            $this->store->put($subjectType, $subjectId, $permissions);
        }

        return new OperationResult(true, $this->logPayload(
            'permission.cleared', $subjectType, $subjectId, $permission, $old, null, $context
        ));
    }

    public function clearAll(string $subjectType, int|string $subjectId): void
    {
        $this->store->remove($subjectType, $subjectId);
    }

    public function definitions(): array
    {
        return $this->definitions->all();
    }

    public function definition(string $permission): ?array
    {
        return $this->definitions->get($permission);
    }

    public function defined(string $permission): bool
    {
        return $this->definitions->has($permission);
    }

    private function logPayload(string $action, string $subjectType, int|string $subjectId, string $permission, ?bool $old, ?bool $new, array $context): array
    {
        $verb = match ($action) {
            'permission.granted' => 'granted to',
            'permission.denied' => 'denied for',
            default => 'cleared from',
        };

        return [
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'actor_type' => $context['actor_type'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
            'message' => "Permission {$permission} was {$verb} {$subjectType} {$subjectId}.",
            'changes' => [$permission => ['old' => $old, 'new' => $new]],
            'metadata' => array_merge(['permission' => $permission], $context['metadata'] ?? []),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
