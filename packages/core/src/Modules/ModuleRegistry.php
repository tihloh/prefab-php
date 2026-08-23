<?php

namespace Tihloh\Prefab\Core\Modules;

use InvalidArgumentException;

final class ModuleRegistry
{
    private array $modules = [];
    public function set(string $name, object $module): self { $this->modules[$name] = $module; return $this; }
    public function has(string $name): bool { return isset($this->modules[$name]); }
    public function get(string $name): object { return $this->modules[$name] ?? throw new InvalidArgumentException("Prefab module '{$name}' is not registered."); }
    public function all(): array { return $this->modules; }
}
