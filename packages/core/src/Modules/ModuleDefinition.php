<?php

namespace Tihloh\Prefab\Core\Modules;

final class ModuleDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        private $factory,
    ) {}

    public function available(): bool
    {
        return class_exists($this->class);
    }

    public function boot(array $config, object $prefab): ?object
    {
        return ($this->factory)($config, $prefab);
    }
}
