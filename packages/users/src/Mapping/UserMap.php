<?php

namespace Tihloh\Prefab\Users\Mapping;

final readonly class UserMap
{
    public function __construct(
        public string $table,
        public string $id = 'id',
        public ?string $name = 'name',
        public ?string $email = 'email',
        public ?string $active = 'active',
        public array $attributes = [],
        public bool $allowCreate = true,
        public bool $allowUpdate = true,
        public bool $allowDelete = false,
    ) {}

    public function coreColumns(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
        ], static fn ($value) => $value !== null);
    }
}
