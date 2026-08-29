<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Session;

interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    public function flash(string $key, mixed $value): void;

    public function pullFlash(string $key, mixed $default = null): mixed;

    public function regenerate(bool $deleteOldSession = true): bool;

    public function destroy(): void;
}
