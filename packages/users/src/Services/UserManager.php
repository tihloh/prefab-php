<?php

namespace Tihloh\Prefab\Users\Services;

use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\DTOs\OperationResult;
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

    public function create(array $data, array $context = []): OperationResult
    {
        $user = $this->provider->create($data);

        return new OperationResult(
            data: $user,
            log: $this->logPayload(
                action: 'user.created',
                subjectId: $user->id,
                message: "User {$user->name} was created.",
                changes: $this->createdChanges($user->toArray()),
                context: $context,
            ),
        );
    }

    public function update(int|string $id, array $data, array $context = []): OperationResult
    {
        $before = $this->provider->find($id);
        $user = $this->provider->update($id, $data);

        return new OperationResult(
            data: $user,
            log: $this->logPayload(
                action: 'user.updated',
                subjectId: $user->id,
                message: "User {$user->name} was updated.",
                changes: $this->diff($before?->toArray() ?? [], $user->toArray()),
                context: $context,
            ),
        );
    }

    public function delete(int|string $id, array $context = []): OperationResult
    {
        $before = $this->provider->find($id);
        $deleted = $this->provider->delete($id);
        $name = $before?->name ?? (string) $id;

        return new OperationResult(
            data: $deleted,
            log: $this->logPayload(
                action: 'user.deleted',
                subjectId: $id,
                message: "User {$name} was deleted.",
                changes: $before ? $this->deletedChanges($before->toArray()) : [],
                context: $context,
            ),
        );
    }

    private function createdChanges(array $data): array
    {
        $changes = [];
        foreach ($data as $key => $value) {
            $changes[$key] = ['old' => null, 'new' => $value];
        }
        return $changes;
    }

    private function deletedChanges(array $data): array
    {
        $changes = [];
        foreach ($data as $key => $value) {
            $changes[$key] = ['old' => $value, 'new' => null];
        }
        return $changes;
    }

    private function diff(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
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
