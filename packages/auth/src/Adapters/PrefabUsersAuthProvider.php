<?php

namespace Tihloh\Prefab\Auth\Adapters;

use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;

final class PrefabUsersAuthProvider implements AuthUserProviderInterface
{
    public function __construct(private object $users) {}

    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface
    {
        if (!method_exists($this->users, 'findByEmail')) return null;
        $user = $this->users->findByEmail($identifier);
        return $user instanceof AuthenticatableUserInterface ? $user : null;
    }

    public function findById(int|string $id): ?AuthenticatableUserInterface
    {
        if (!method_exists($this->users, 'find')) return null;
        $user = $this->users->find($id);
        return $user instanceof AuthenticatableUserInterface ? $user : null;
    }
}
