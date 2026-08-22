<?php

namespace Tihloh\Prefab\Users\Services;

use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\User\PrefabUser;

final class UserManager
{
    public function __construct(
        private UserProviderInterface $provider,
    ) {}

    public function find(int|string $id): ?PrefabUser
    {
        return $this->provider->find($id);
    }

    public function findByEmail(string $email): ?PrefabUser
    {
        return $this->provider->findByEmail($email);
    }

    /** @return list<PrefabUser> */
    public function all(int $limit = 100, int $offset = 0): array
    {
        return $this->provider->all($limit, $offset);
    }

    public function create(array $data): PrefabUser
    {
        return $this->provider->create($data);
    }

    public function update(int|string $id, array $data): PrefabUser
    {
        return $this->provider->update($id, $data);
    }

    public function delete(int|string $id): bool
    {
        return $this->provider->delete($id);
    }
}
