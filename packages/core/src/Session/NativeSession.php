<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Session;

final class NativeSession implements SessionInterface
{
    private const FLASH_KEY = '__prefab_flash';

    public function __construct(private bool $autoStart = true)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        unset($_SESSION[self::FLASH_KEY][$key]);
        if (($_SESSION[self::FLASH_KEY] ?? []) === []) {
            unset($_SESSION[self::FLASH_KEY]);
        }
        return $value;
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->start();
        return session_regenerate_id($deleteOldSession);
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE && !$this->autoStart) {
            return;
        }

        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!$this->autoStart) {
            return;
        }

        session_start();
    }
}
