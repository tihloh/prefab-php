<?php

namespace Tihloh\Prefab\Permissions\DTOs;

final class Group
{
    public function __construct(
        public int|string $id,
        public string $name,
        public ?string $description = null,
        public int $usersCount = 0,
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
