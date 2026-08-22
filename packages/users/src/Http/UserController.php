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

    public function store(array $input, array $context = []): array
    {
        $result = $this->users->create($input, $context);

        return [
            'data' => $result->data->toArray(),
            'log' => $result->log,
            'status' => 201,
        ];
    }

    public function update(int|string $id, array $input, array $context = []): array
    {
        $result = $this->users->update($id, $input, $context);

        return [
            'data' => $result->data->toArray(),
            'log' => $result->log,
        ];
    }

    public function destroy(int|string $id, array $context = []): array
    {
        $result = $this->users->delete($id, $context);

        return [
            'deleted' => $result->data,
            'log' => $result->log,
        ];
    }
}
