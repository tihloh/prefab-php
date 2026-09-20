<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use Throwable;

final class ThemeRegistry
{
    /** @var string[] */
    private array $paths = [];
    /** @var array<string, ThemeDefinition>|null */
    private ?array $themes = null;
    /** @var array<string, string> */
    private array $errors = [];

    public function __construct(array $paths = [])
    {
        foreach ($paths as $path) {
            $this->addPath((string) $path);
        }
    }

    public function addPath(string $path): self
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if ($path !== '' && !in_array($path, $this->paths, true)) {
            $this->paths[] = $path;
            $this->themes = null;
        }
        return $this;
    }

    /** @return array<string, ThemeDefinition> */
    public function all(): array
    {
        return $this->discover();
    }

    public function get(string $id): ?ThemeDefinition
    {
        return $this->discover()[strtolower($id)] ?? null;
    }

    public function has(string $id): bool
    {
        return $this->get($id) !== null;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        $this->discover();
        return $this->errors;
    }

    public function refresh(): self
    {
        $this->themes = null;
        $this->errors = [];
        return $this;
    }

    /** @return array<string, ThemeDefinition> */
    private function discover(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $this->themes = [];
        $this->errors = [];

        foreach ($this->paths as $root) {
            if (!is_dir($root)) { continue; }

            $entries = scandir($root);
            if ($entries === false) { continue; }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') { continue; }
                $directory = $root . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($directory)) { continue; }

                try {
                    $theme = ThemeDefinition::fromDirectory($directory);
                    if (!isset($this->themes[$theme->id])) {
                        $this->themes[$theme->id] = $theme;
                    }
                } catch (Throwable $e) {
                    $this->errors[$directory] = $e->getMessage();
                }
            }
        }

        ksort($this->themes);
        return $this->themes;
    }
}
