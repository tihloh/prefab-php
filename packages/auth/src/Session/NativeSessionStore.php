<?php

namespace Tihloh\Prefab\Auth\Session;

use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;

final class NativeSessionStore implements AuthSessionStoreInterface
{
    public function __construct(private string $key = 'prefab_auth_user_id')
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    public function put(int|string $userId): void { $_SESSION[$this->key] = $userId; }
    public function userId(): int|string|null { return $_SESSION[$this->key] ?? null; }
    public function forget(): void { unset($_SESSION[$this->key]); }
}
