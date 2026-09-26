<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use ReflectionMethod;
use RuntimeException;

abstract class Component
{
    private array $liveErrors = [];
    private array $liveValidatedFields = [];
    private mixed $liveValidator = null;

    abstract public function render(): string;

    protected function rules(): array
    {
        return [];
    }

    protected function liveChecks(): array
    {
        return [];
    }

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

    final public function __liveBindValidator(callable $validator): void
    {
        $this->liveValidator = $validator;
    }

    final public function __liveResetValidation(): void
    {
        $this->liveErrors = [];
        $this->liveValidatedFields = [];
    }

    final public function __liveApplyValidation(array $errors, array $fields): void
    {
        foreach ($fields as $field) {
            unset($this->liveErrors[(string) $field]);
        }

        foreach ($errors as $field => $messages) {
            $messages = array_values(array_filter(
                (array) $messages,
                static fn (mixed $message): bool => is_string($message) && trim($message) !== '',
            ));

            if ($messages !== []) {
                $this->liveErrors[(string) $field] = $messages;
            }
        }

        $this->liveValidatedFields = array_values(array_unique(array_map('strval', $fields)));
    }

    final public function __liveClearValidation(array $fields): void
    {
        foreach ($fields as $field) {
            unset($this->liveErrors[(string) $field]);
        }
        $this->liveValidatedFields = array_values(array_unique(array_map('strval', $fields)));
    }

    final public function __liveValidatedFields(): array
    {
        return $this->liveValidatedFields;
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

    final protected function validate(?array $fields = null): bool
    {
        if (!is_callable($this->liveValidator)) {
            throw new RuntimeException('Prefab Live validation is unavailable outside a LiveManager lifecycle.');
        }

        return ($this->liveValidator)($this, $fields) === true;
    }

    final protected function validateOnly(string $field): bool
    {
        return $this->validate([$field]);
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
