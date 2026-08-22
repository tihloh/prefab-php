<?php

namespace Tihloh\Prefab\Users\Contracts;

use Tihloh\Prefab\Users\User\PrefabUser;

interface UserProviderInterface
{
    public function find(int|string $id): ?PrefabUser;

    public function findByEmail(string $email): ?PrefabUser;

    /** @return list<PrefabUser> */
    public function all(int $limit = 100, int $offset = 0): array;

    public function create(array $data): PrefabUser;

    public function update(int|string $id, array $data): PrefabUser;

    public function delete(int|string $id): bool;
}
