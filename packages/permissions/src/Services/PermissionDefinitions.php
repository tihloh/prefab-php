<?php

namespace Tihloh\Prefab\Permissions\Services;

use InvalidArgumentException;
use RuntimeException;

final class PermissionDefinitions
{
    private array $definitions;

    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }

    public static function fromJsonFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Permission definition file not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Permission definition JSON must decode to an object/array.');
        }

        return new self($decoded);
    }

    public function all(): array
    {
        return $this->definitions;
    }

    public function has(string $permission): bool
    {
        return array_key_exists($permission, $this->definitions);
    }

    public function get(string $permission): ?array
    {
        return $this->definitions[$permission] ?? null;
    }

    public function default(string $permission): bool
    {
        return (bool) ($this->definitions[$permission]['default'] ?? false);
    }

    public function validateOverrides(array $permissions): array
    {
        foreach ($permissions as $id => $value) {
            if (!$this->has((string) $id)) {
                throw new InvalidArgumentException("Undefined permission: {$id}");
            }
            if (!is_bool($value)) {
                throw new InvalidArgumentException("Permission {$id} must be boolean.");
            }
        }

        return $permissions;
    }
}
