<?php

namespace Tihloh\Prefab\Auth\Session;

use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;

final class NativeSessionStore implements AuthSessionStoreInterface
{
    private string $scopedKey;

    public function __construct(private string $key = 'auth:user_id')
    {
        SessionScope::start();
        $this->scopedKey = SessionScope::key($this->key);
    }

    public function put(int|string $userId): void
    {
        $_SESSION[$this->scopedKey] = $userId;
    }

    public function userId(): int|string|null
    {
        return $_SESSION[$this->scopedKey] ?? null;
    }

    public function forget(): void
    {
        unset($_SESSION[$this->scopedKey]);
    }
}
