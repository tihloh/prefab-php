<?php

namespace Tihloh\Prefab\Core\Connections;

use InvalidArgumentException;

final class ConnectionManager
{
    private array $connections = [];
    public function set(string $name, mixed $connection): self { $this->connections[$name] = $connection; return $this; }
    public function has(string $name): bool { return array_key_exists($name, $this->connections); }
    public function get(string $name = 'default'): mixed { return $this->connections[$name] ?? throw new InvalidArgumentException("Prefab connection '{$name}' is not registered."); }
    public function all(): array { return $this->connections; }
}
