<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

final class Recipient
{
    /** @param array<string, mixed> $routes */
    public function __construct(
        public readonly string|int|null $id = null,
        public readonly ?string $name = null,
        public readonly array $routes = [],
    ) {}

    public static function email(string $email, ?string $name = null): self
    {
        return new self(name: $name, routes: ['mail' => $email]);
    }

    public function route(string $channel): mixed
    {
        return $this->routes[$channel] ?? null;
    }
}
