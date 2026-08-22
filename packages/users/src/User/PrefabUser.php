<?php

namespace Tihloh\Prefab\Users\User;

class PrefabUser
{
    public function __construct(
        public readonly int|string $id,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly bool $active = true,
        protected array $attributes = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
            ...$this->attributes,
        ];
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }
}
