<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Cache;

use RuntimeException;

final class FileCache implements CacheInterface
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Unable to create cache directory: {$this->directory}");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->read($key);
        return $item === null ? $default : $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $payload = serialize([
            'expires_at' => $ttl === null ? null : time() + max(0, $ttl),
            'value' => $value,
        ]);

        return file_put_contents($this->path($key), $payload, LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);
        return !is_file($path) || unlink($path);
    }

    public function clear(): bool
    {
        $ok = true;
        foreach (glob(rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
            $ok = unlink($file) && $ok;
        }
        return $ok;
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $item = $this->read($key);
        if ($item !== null) {
            return $item['value'];
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    private function read(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $item = @unserialize($raw, ['allowed_classes' => true]);
        if (!is_array($item) || !array_key_exists('value', $item)) {
            @unlink($path);
            return null;
        }

        if (($item['expires_at'] ?? null) !== null && (int) $item['expires_at'] <= time()) {
            @unlink($path);
            return null;
        }

        return $item;
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
