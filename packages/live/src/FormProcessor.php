<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use ReflectionMethod;
use RuntimeException;
use Tihloh\Prefab\Input\Input;

final class FormProcessor
{
    public function __construct(private StateSerializer $serializer)
    {
    }

    public function validate(Component $component, ?array $fields = null): bool
    {
        $rules = $this->definition($component, 'rules');
        $checks = $this->definition($component, 'liveChecks');
        $selected = $fields === null
            ? array_values(array_unique([...array_keys($rules), ...array_keys($checks)]))
            : $this->normalizeFields($fields);

        if ($fields === null) {
            $component->__liveResetValidation();
        } else {
            $component->__liveClearValidation($selected);
        }

        if ($selected === []) {
            return true;
        }

        $errors = [];
        $schema = $fields === null ? $rules : $this->schemaForFields($rules, $selected);

        if ($schema !== []) {
            if (!class_exists(Input::class)) {
                throw new RuntimeException(
                    'Prefab Live component rules() requires tihloh/prefab-input. Install prefab-input or remove rules().'
                );
            }

            $result = Input::from($this->serializer->snapshot($component))->process($schema);
            $this->applyProcessedState($component, $result->all());
            $errors = $result->errors();
        }

        $state = $this->serializer->snapshot($component);
        foreach ($selected as $field) {
            if ($this->hasError($errors, $field)) {
                continue;
            }

            foreach ($this->checksForField($checks, $field) as $check) {
                [$exists, $value] = $this->getPath($state, $field);
                $message = $check($exists ? $value : null, $field, $component);

                if ($message === null || $message === true) {
                    continue;
                }

                if (!is_string($message) || trim($message) === '') {
                    throw new RuntimeException(
                        "Prefab Live check for {$field} must return null/true when valid or a non-empty error string when invalid."
                    );
                }

                $errors[$field] ??= [];
                $errors[$field][] = $message;
                break;
            }
        }

        $component->__liveApplyValidation($errors, $selected);
        return $errors === [];
    }

    private function definition(Component $component, string $method): array
    {
        $reflection = new ReflectionMethod($component, $method);
        if ($reflection->isPrivate() || $reflection->isStatic()) {
            throw new RuntimeException("Prefab Live {$method}() must be public or protected and non-static.");
        }

        $value = $reflection->invoke($component);
        if (!is_array($value)) {
            throw new RuntimeException("Prefab Live {$method}() must return an array.");
        }

        return $value;
    }

    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            $field = trim((string) $field);
            if ($field !== '') {
                $normalized[] = $field;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function schemaForFields(array $rules, array $fields): array
    {
        $schema = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $rules)) {
                $schema[$field] = $rules[$field];
                continue;
            }

            foreach ($rules as $pattern => $definition) {
                if ($this->matches((string) $pattern, $field)) {
                    $schema[$field] = $definition;
                    break;
                }
            }
        }

        return $schema;
    }

    private function checksForField(array $checks, string $field): array
    {
        $definition = $checks[$field] ?? null;
        if ($definition === null) {
            foreach ($checks as $pattern => $candidate) {
                if ($this->matches((string) $pattern, $field)) {
                    $definition = $candidate;
                    break;
                }
            }
        }

        if ($definition === null) {
            return [];
        }

        $definitions = is_array($definition) && !is_callable($definition)
            ? $definition
            : [$definition];

        foreach ($definitions as $check) {
            if (!is_callable($check)) {
                throw new RuntimeException("Prefab Live check for {$field} must be callable.");
            }
        }

        return $definitions;
    }

    private function applyProcessedState(Component $component, array $processed): void
    {
        foreach ($processed as $property => $value) {
            $this->serializer->applyUpdates($component, [(string) $property => $value]);
        }
    }

    private function hasError(array $errors, string $field): bool
    {
        if (isset($errors[$field]) && $errors[$field] !== []) {
            return true;
        }

        foreach ($errors as $key => $messages) {
            if ($messages !== [] && (
                $this->matches((string) $key, $field)
                || $this->matches($field, (string) $key)
            )) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $pattern, string $field): bool
    {
        if ($pattern === $field) {
            return true;
        }

        if (!str_contains($pattern, '*')) {
            return false;
        }

        $regex = '/^' . str_replace('\*', '[^.]+', preg_quote($pattern, '/')) . '$/';
        return preg_match($regex, $field) === 1;
    }

    /** @return array{0: bool, 1: mixed} */
    private function getPath(array $data, string $path): array
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
    }
}
