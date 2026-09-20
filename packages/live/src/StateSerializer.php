<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use InvalidArgumentException;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use Tihloh\Prefab\Live\Attributes\Locked;

final class StateSerializer
{
    public function snapshot(Component $component): array
    {
        $state = [];
        $reflection = new ReflectionObject($component);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->isReadOnly() || !$property->isInitialized($component)) {
                continue;
            }

            $value = $property->getValue($component);
            $this->assertSerializable($value, $property->getName());
            $state[$property->getName()] = $value;
        }

        ksort($state, SORT_STRING);
        return $state;
    }

    public function hydrate(Component $component, array $state): void
    {
        foreach ($state as $name => $value) {
            $property = $this->property($component, (string) $name);
            $property->setValue($component, $this->coerce($property, $value));
        }
    }

    public function applyUpdates(Component $component, array $updates): void
    {
        foreach ($updates as $path => $value) {
            $path = trim((string) $path);
            if ($path === '') {
                throw new InvalidArgumentException('Prefab Live update path cannot be empty.');
            }

            $segments = explode('.', $path);
            $propertyName = array_shift($segments);
            $property = $this->property($component, $propertyName);

            if ($property->getAttributes(Locked::class) !== []) {
                throw new InvalidArgumentException("Prefab Live state property is locked: {$propertyName}");
            }

            if ($segments === []) {
                $property->setValue($component, $this->coerce($property, $value));
                continue;
            }

            $root = $property->getValue($component);
            if (!is_array($root)) {
                throw new InvalidArgumentException("Prefab Live nested update requires array state: {$propertyName}");
            }

            $this->setArrayPath($root, $segments, $value);
            $property->setValue($component, $this->coerce($property, $root));
        }
    }

    private function property(Component $component, string $name): ReflectionProperty
    {
        $reflection = new ReflectionObject($component);

        if (!$reflection->hasProperty($name)) {
            throw new InvalidArgumentException("Prefab Live state property does not exist: {$name}");
        }

        $property = $reflection->getProperty($name);
        if (!$property->isPublic() || $property->isStatic() || $property->isReadOnly()) {
            throw new InvalidArgumentException("Prefab Live state property is not writable public state: {$name}");
        }

        return $property;
    }

    private function coerce(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        if ($value === null) {
            if ($type->allowsNull() || $type->getName() === 'mixed') {
                return null;
            }
            throw new InvalidArgumentException("Prefab Live state property {$property->getName()} cannot be null.");
        }

        return match ($type->getName()) {
            'mixed' => $value,
            'string' => is_scalar($value)
                ? (string) $value
                : throw new InvalidArgumentException("Prefab Live state property {$property->getName()} must be a string."),
            'int' => $this->toInt($property->getName(), $value),
            'float' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
                ? (float) $value
                : throw new InvalidArgumentException("Prefab Live state property {$property->getName()} must be numeric."),
            'bool' => $this->toBool($property->getName(), $value),
            'array' => is_array($value)
                ? $value
                : throw new InvalidArgumentException("Prefab Live state property {$property->getName()} must be an array."),
            default => $value,
        };
    }

    private function toInt(string $name, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Prefab Live state property {$name} must be an integer.");
    }

    private function toBool(string $name, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') return true;
        if ($value === 0 || $value === '0') return false;

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', 'on', 'yes'], true)) return true;
            if (in_array($normalized, ['false', 'off', 'no', ''], true)) return false;
        }

        throw new InvalidArgumentException("Prefab Live state property {$name} must be boolean.");
    }

    private function setArrayPath(array &$state, array $segments, mixed $value): void
    {
        $cursor =& $state;

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException('Prefab Live nested update contains an empty path segment.');
            }

            if ($index === array_key_last($segments)) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            if (!is_array($cursor[$segment])) {
                throw new InvalidArgumentException('Prefab Live nested update crosses a non-array value.');
            }

            $cursor =& $cursor[$segment];
        }
    }

    private function assertSerializable(mixed $value, string $path): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException("Prefab Live public state must be JSON-safe scalar/array data: {$path}");
        }

        foreach ($value as $key => $item) {
            $this->assertSerializable($item, $path . '.' . (string) $key);
        }
    }
}
