<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Cache;

final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value:mixed, expires_at:?float}> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->items[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $ttl === null ? null : microtime(true) + max(0, $ttl),
        ];
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->items[$key])) {
            return false;
        }

        $expiresAt = $this->items[$key]['expires_at'];
        if ($expiresAt !== null && $expiresAt <= microtime(true)) {
            unset($this->items[$key]);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
