<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use InvalidArgumentException;
use RuntimeException;

final class ThemeDefinition
{
    /** @param array<string, string> $modes */
    /** @param array<string, bool> $supports */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $directory,
        public readonly ?string $base,
        public readonly array $modes,
        public readonly array $supports = [],
        public readonly ?string $description = null,
        public readonly ?string $author = null,
    ) {}

    public static function fromDirectory(string $directory): self
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $manifest = $directory . DIRECTORY_SEPARATOR . 'theme.json';

        if (!is_file($manifest) || !is_readable($manifest)) {
            throw new RuntimeException("Theme manifest not found: {$manifest}");
        }

        $data = json_decode((string) file_get_contents($manifest), true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid theme manifest: {$manifest}");
        }

        $id = strtolower(trim((string) ($data['id'] ?? '')));
        if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException('Theme id must use lowercase letters, numbers, dash or underscore.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException("Theme '{$id}' requires a name.");
        }

        $base = isset($data['base']) && $data['base'] !== ''
            ? self::relativeFile((string) $data['base'])
            : null;

        if ($base !== null && !is_file($directory . DIRECTORY_SEPARATOR . $base)) {
            throw new RuntimeException("Theme '{$id}' base file not found: {$base}");
        }

        $modes = [];
        foreach (($data['modes'] ?? []) as $mode => $file) {
            $mode = strtolower(trim((string) $mode));
            if ($mode === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $mode)) {
                throw new InvalidArgumentException("Theme '{$id}' contains an invalid mode name.");
            }
            $file = self::relativeFile((string) $file);
            if (!is_file($directory . DIRECTORY_SEPARATOR . $file)) {
                throw new RuntimeException("Theme '{$id}' mode file not found: {$file}");
            }
            $modes[$mode] = $file;
        }

        if ($modes === []) {
            throw new InvalidArgumentException("Theme '{$id}' requires at least one mode.");
        }

        $supports = [];
        foreach (($data['supports'] ?? []) as $feature => $enabled) {
            $supports[(string) $feature] = (bool) $enabled;
        }

        return new self(
            id: $id,
            name: $name,
            version: trim((string) ($data['version'] ?? '1.0.0')) ?: '1.0.0',
            directory: $directory,
            base: $base,
            modes: $modes,
            supports: $supports,
            description: isset($data['description']) ? (string) $data['description'] : null,
            author: isset($data['author']) ? (string) $data['author'] : null,
        );
    }

    public function supportsMode(string $mode): bool
    {
        return isset($this->modes[$mode]);
    }

    public function modeFile(string $mode): ?string
    {
        return $this->modes[$mode] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'base' => $this->base,
            'modes' => $this->modes,
            'supports' => $this->supports,
        ];
    }

    private static function relativeFile(string $file): string
    {
        $file = trim(str_replace('\\', '/', $file));
        $parts = explode('/', $file);

        if (
            $file === ''
            || str_starts_with($file, '/')
            || in_array('..', $parts, true)
            || !preg_match('#^[A-Za-z0-9._/-]+$#', $file)
        ) {
            throw new InvalidArgumentException("Unsafe theme file path: {$file}");
        }

        return implode(DIRECTORY_SEPARATOR, array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '' && $part !== '.',
        )));
    }
}
