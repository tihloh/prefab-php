<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use InvalidArgumentException;
use RuntimeException;

final class ComponentRegistry
{
    private array $components = [];

    public function register(string $name, string|callable $resolver): self
    {
        $name = $this->normalizeName($name);
        $this->components[$name] = $resolver;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->components[$this->normalizeName($name)]);
    }

    public function make(string $name): Component
    {
        $name = $this->normalizeName($name);

        if (!isset($this->components[$name])) {
            throw new InvalidArgumentException("Prefab Live component is not registered: {$name}");
        }

        $resolver = $this->components[$name];
        $component = is_string($resolver) ? $this->makeClass($resolver) : $resolver();

        if (!$component instanceof Component) {
            throw new RuntimeException("Prefab Live component resolver for {$name} must return a Component instance.");
        }

        return $component;
    }

    public function names(): array
    {
        return array_keys($this->components);
    }

    private function makeClass(string $class): Component
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Prefab Live component class does not exist: {$class}");
        }

        if (!is_subclass_of($class, Component::class)) {
            throw new InvalidArgumentException("Prefab Live component class must extend " . Component::class . '.');
        }

        return new $class();
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        if ($name === '' || preg_match('/^[a-z][a-z0-9._-]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Prefab Live component names may contain lowercase letters, numbers, dots, underscores and hyphens.');
        }

        return $name;
    }
}
