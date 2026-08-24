<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Validation;

/**
 * Immutable-style validation result snapshot.
 *
 * The arrays are exposed through methods so callers can safely inspect the
 * result without needing access to Validator internals.
 */
final class ValidationResult
{
    public function __construct(
        private array $data,
        private array $validated,
        private array $errors,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function data(): array
    {
        return $this->data;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
    }
}
