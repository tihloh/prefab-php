<?php

namespace Tihloh\Prefab\Permissions\Services;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Immutable collection of permission definitions used by PermissionManager.
 *
 * Definitions may be supplied directly as a PHP array or loaded from a
 * standalone PHP/JSON template file. All supported sources are normalized
 * into this class so permission resolution behaves identically afterward.
 */
final class PermissionDefinitions
{
    /** @param array<string, array<string, mixed>> $definitions */
    public function __construct(
        private array $definitions,
    ) {
    }

    /**
     * Normalize any supported permission template source.
     *
     * Supported sources:
     * - PermissionDefinitions instance
     * - PHP array
     * - .php file returning an array
     * - .json file containing an object/associative array
     */
    public static function from(PermissionDefinitions|array|string $source): self
    {
        if ($source instanceof self) {
            return $source;
        }

        if (is_array($source)) {
            return new self($source);
        }

        return self::fromFile($source);
    }

    /**
     * Load definitions from a PHP or JSON template file.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Permission definition file not found: {$path}");
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php' => self::fromPhpFile($path),
            'json' => self::fromJsonFile($path),
            default => throw new RuntimeException(
                'Unsupported permission definition file. Use a .php or .json template.'
            ),
        };
    }

    /**
     * Load a PHP template. The file must return the permission definitions array.
     */
    public static function fromPhpFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Permission definition file not found: {$path}");
        }

        $definitions = require $path;

        if (!is_array($definitions)) {
            throw new RuntimeException('Permission definition PHP file must return an array.');
        }

        return new self($definitions);
    }

    /**
     * Load a JSON permission template.
     *
     * @throws JsonException When the JSON document is invalid.
     */
    public static function fromJsonFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Permission definition file not found: {$path}");
        }

        $decoded = json_decode(
            (string) file_get_contents($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($decoded)) {
            throw new RuntimeException('Permission definition JSON must decode to an object/array.');
        }

        return new self($decoded);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function has(string $permission): bool
    {
        return array_key_exists($permission, $this->definitions);
    }

    /** @return array<string, mixed>|null */
    public function get(string $permission): ?array
    {
        return $this->definitions[$permission] ?? null;
    }

    /**
     * Return the definition's default decision. Undefined defaults are denied.
     */
    public function default(string $permission): bool
    {
        return (bool) ($this->definitions[$permission]['default'] ?? false);
    }

    /**
     * Validate an override map against known permission IDs.
     *
     * @param array<string, bool> $permissions
     * @return array<string, bool>
     */
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
