<?php

namespace Tihloh\Prefab\Users\Http;

use Tihloh\Prefab\Users\Services\UserManager;

final class UserController
{
    public function __construct(
        private UserManager $users,
    ) {}

    public function index(int $limit = 100, int $offset = 0): array
    {
        return [
            'data' => array_map(
                static fn ($user) => $user->toArray(),
                $this->users->all($limit, $offset)
            ),
        ];
    }

    public function show(int|string $id): array
    {
        $user = $this->users->find($id);

        return $user
            ? ['data' => $user->toArray()]
            : ['error' => 'User not found', 'status' => 404];
    }

    public function store(array $input): array
    {
        return ['data' => $this->users->create($input)->toArray(), 'status' => 201];
    }

    public function update(int|string $id, array $input): array
    {
        return ['data' => $this->users->update($id, $input)->toArray()];
    }

    public function destroy(int|string $id): array
    {
        return ['deleted' => $this->users->delete($id)];
    }
}
