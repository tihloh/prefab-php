<?php

namespace Tihloh\Prefab\Permissions\Services;

use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
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

    public function set(string $subjectType, int|string $subjectId, string $permission, bool $value): void
    {
        $permissions = $this->store->get($subjectType, $subjectId);
        $permissions[$permission] = $value;
        $this->store->put($subjectType, $subjectId, $this->definitions->validateOverrides($permissions));
    }

    public function clear(string $subjectType, int|string $subjectId, string $permission): void
    {
        $permissions = $this->store->get($subjectType, $subjectId);
        unset($permissions[$permission]);
        if ($permissions === []) {
            $this->store->remove($subjectType, $subjectId);
            return;
        }
        $this->store->put($subjectType, $subjectId, $permissions);
    }

    public function definitions(): array
    {
        return $this->definitions->all();
    }

    public function defined(string $permission): bool
    {
        return $this->definitions->has($permission);
    }
}
