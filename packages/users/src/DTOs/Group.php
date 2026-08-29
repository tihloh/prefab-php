<?php

namespace Tihloh\Prefab\Users\DTOs;

final class Group
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly int $usersCount = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'users_count' => $this->usersCount,
        ];
    }
}
