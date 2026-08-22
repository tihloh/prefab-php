<?php

namespace Tihloh\Prefab\Auth\Contracts;

interface AuthSessionStoreInterface
{
    public function put(int|string $userId): void;
    public function userId(): int|string|null;
    public function forget(): void;
}
