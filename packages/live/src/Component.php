<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use ReflectionMethod;
use RuntimeException;

abstract class Component
{
    private array $liveErrors = [];

    abstract public function render(): string;

    final public function __liveMount(array $params = []): void
    {
        $this->callLifecycle('mount', $params);
    }

    final public function __liveHydrate(): void
    {
        $this->callLifecycle('hydrate');
    }

    final public function __liveDehydrate(): void
    {
        $this->callLifecycle('dehydrate');
    }

    final public function errors(?string $field = null): array
    {
        if ($field === null) {
            return $this->liveErrors;
        }

        return isset($this->liveErrors[$field])
            ? (array) $this->liveErrors[$field]
            : [];
    }

    final public function error(string $field): ?string
    {
        $errors = $this->errors($field);
        return isset($errors[0]) ? (string) $errors[0] : null;
    }

    final protected function setErrors(array $errors): void
    {
        $this->liveErrors = $errors;
    }

    final protected function addError(string $field, string $message): void
    {
        $this->liveErrors[$field] ??= [];
        $this->liveErrors[$field][] = $message;
    }

    final protected function clearErrors(?string $field = null): void
    {
        if ($field === null) {
            $this->liveErrors = [];
            return;
        }

        unset($this->liveErrors[$field]);
    }

    private function callLifecycle(string $method, array $params = []): void
    {
        if (!method_exists($this, $method)) {
            return;
        }

        $reflection = new ReflectionMethod($this, $method);
        if ($reflection->isPrivate() || $reflection->isStatic()) {
            throw new RuntimeException("Prefab Live lifecycle method {$method}() must be public or protected and non-static.");
        }

        $reflection->invokeArgs($this, $params);
    }
}
