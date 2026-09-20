<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use RuntimeException;
use ZipArchive;

final class ThemeInstaller
{
    private const ALLOWED_EXTENSIONS = [
        'json', 'css', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'woff2',
    ];

    public function __construct(
        private readonly string $themesPath,
        private readonly int $maxFiles = 200,
        private readonly int $maxBytes = 20_000_000,
    ) {}

    public function installZip(string $archive, bool $replace = false): ThemeDefinition
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Installing theme ZIP files requires the PHP zip extension.');
        }
        if (!is_file($archive) || !is_readable($archive)) {
            throw new RuntimeException("Theme archive is not readable: {$archive}");
        }

        $this->ensureThemesPath();
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException("Unable to open theme archive: {$archive}");
        }

        $temp = $this->themesPath . '/.install-' . bin2hex(random_bytes(8));
        if (!mkdir($temp, 0775, true) && !is_dir($temp)) {
            $zip->close();
            throw new RuntimeException("Unable to create theme install directory: {$temp}");
        }

        try {
            $prefix = $this->validateArchive($zip);
            $this->extractArchive($zip, $temp, $prefix);
            $theme = ThemeDefinition::fromDirectory($temp);

            if ($theme->id === 'default') {
                throw new RuntimeException("The built-in 'default' theme cannot be replaced by a downloaded theme.");
            }

            $target = $this->themesPath . '/' . $theme->id;
            if (is_dir($target) && !$replace) {
                throw new RuntimeException("Theme '{$theme->id}' is already installed.");
            }

            if (is_dir($target)) {
                $backup = $this->themesPath . '/.backup-' . $theme->id . '-' . bin2hex(random_bytes(4));
                if (!rename($target, $backup)) {
                    throw new RuntimeException("Unable to prepare theme '{$theme->id}' for update.");
                }
                try {
                    if (!rename($temp, $target)) {
                        throw new RuntimeException("Unable to install theme '{$theme->id}'.");
                    }
                    $this->removeDirectory($backup);
                } catch (\Throwable $e) {
                    if (is_dir($backup) && !is_dir($target)) { rename($backup, $target); }
                    throw $e;
                }
            } elseif (!rename($temp, $target)) {
                throw new RuntimeException("Unable to install theme '{$theme->id}'.");
            }

            return ThemeDefinition::fromDirectory($target);
        } finally {
            $zip->close();
            if (is_dir($temp)) { $this->removeDirectory($temp); }
        }
    }

    public function uninstall(string $id): void
    {
        $id = strtolower(trim($id));
        if ($id === 'default') {
            throw new RuntimeException("The built-in 'default' theme cannot be uninstalled.");
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id)) {
            throw new RuntimeException('Invalid theme id.');
        }

        $target = $this->themesPath . '/' . $id;
        if (is_dir($target)) {
            $this->removeDirectory($target);
        }
    }

    private function validateArchive(ZipArchive $zip): string
    {
        if ($zip->numFiles < 1 || $zip->numFiles > $this->maxFiles) {
            throw new RuntimeException('Theme archive contains an invalid number of files.');
        }

        $total = 0;
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) {
                throw new RuntimeException('Unable to inspect theme archive.');
            }

            $name = (string) ($stat['name'] ?? '');
            $this->validateEntryName($name);
            $total += (int) ($stat['size'] ?? 0);
            if ($total > $this->maxBytes) {
                throw new RuntimeException('Theme archive exceeds the maximum uncompressed size.');
            }

            if (!str_ends_with($name, '/')) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    throw new RuntimeException("Theme archive contains a disallowed file: {$name}");
                }
            }
            $names[] = $name;
        }

        if (in_array('theme.json', $names, true)) {
            return '';
        }

        $roots = [];
        foreach ($names as $name) {
            $parts = explode('/', $name);
            if (($parts[0] ?? '') !== '') { $roots[$parts[0]] = true; }
        }

        if (count($roots) !== 1) {
            throw new RuntimeException('Theme archive must contain theme.json at the root or inside one top-level directory.');
        }

        $prefix = array_key_first($roots) . '/';
        if (!in_array($prefix . 'theme.json', $names, true)) {
            throw new RuntimeException('Theme archive is missing theme.json.');
        }
        return $prefix;
    }

    private function extractArchive(ZipArchive $zip, string $target, string $prefix): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($prefix !== '' && !str_starts_with($name, $prefix)) { continue; }

            $relative = $prefix === '' ? $name : substr($name, strlen($prefix));
            if ($relative === '' || str_ends_with($relative, '/')) { continue; }

            $this->validateEntryName($relative);
            $destination = $target . '/' . $relative;
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create theme directory: {$directory}");
            }

            $input = $zip->getStream($name);
            if (!is_resource($input)) {
                throw new RuntimeException("Unable to read theme file: {$name}");
            }
            $output = fopen($destination, 'wb');
            if ($output === false) {
                fclose($input);
                throw new RuntimeException("Unable to write theme file: {$relative}");
            }

            try {
                stream_copy_to_stream($input, $output);
            } finally {
                fclose($input);
                fclose($output);
            }
        }
    }

    private function validateEntryName(string $name): void
    {
        $normalized = str_replace('\\', '/', $name);
        $parts = explode('/', $normalized);
        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || str_contains($normalized, "\0")
            || in_array('..', $parts, true)
            || !preg_match('#^[A-Za-z0-9._/-]+$#', $normalized)
        ) {
            throw new RuntimeException("Unsafe path in theme archive: {$name}");
        }
    }

    private function ensureThemesPath(): void
    {
        if (!is_dir($this->themesPath) && !mkdir($this->themesPath, 0775, true) && !is_dir($this->themesPath)) {
            throw new RuntimeException("Unable to create themes directory: {$this->themesPath}");
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
