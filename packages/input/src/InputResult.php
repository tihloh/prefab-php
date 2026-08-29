<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Input;

use Tihloh\Prefab\PrefabRuntime;

/**
 * Snapshot returned after input processing.
 *
 * `validated()` intentionally contains only fields declared by the schema and
 * only when those fields passed validation. Raw input remains available for
 * diagnostics but should not normally be persisted directly.
 */
final class InputResult
{
    public function __construct(
        private array $raw,
        private array $processed,
        private array $validated,
        private array $errors,
    ) {
        PrefabRuntime::traceStart('input', 'process', [
            'fields' => count($raw),
        ]);
        PrefabRuntime::traceEnd([
            'valid' => $errors === [],
            'validated' => count($validated),
            'errors' => count($errors),
        ]);
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function all(): array
    {
        return $this->processed;
    }

    /** Return all validated data, or one validated field when a path is supplied. */
    public function validated(?string $field = null, mixed $default = null): mixed
    {
        if ($field === null) {
            return $this->validated;
        }

        $value = self::getPath($this->validated, $field, $exists);
        return $exists ? $value : $default;
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

    public function value(string $field, mixed $default = null): mixed
    {
        $value = self::getPath($this->processed, $field, $exists);
        return $exists ? $value : $default;
    }

    private static function getPath(array $data, string $path, ?bool &$exists = null): mixed
    {
        $exists = true;
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $exists = false;
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
