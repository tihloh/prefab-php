<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use RuntimeException;

final class ThemePublisher
{
    public function __construct(private readonly string $packageRoot) {}

    public function publish(string $publicPath, bool $overwrite = true): array
    {
        $publicPath = rtrim($publicPath, DIRECTORY_SEPARATOR);
        $written = [];

        $files = [
            $this->packageRoot . '/assets/core.css' => $publicPath . '/core.css',
            $this->packageRoot . '/assets/admin.css' => $publicPath . '/admin.css',
            $this->packageRoot . '/assets/theme.js' => $publicPath . '/theme.js',
        ];

        foreach ($files as $source => $target) {
            $this->copyFile($source, $target, $overwrite);
            $written[] = $target;
        }

        $sourceThemes = $this->packageRoot . '/themes';
        if (is_dir($sourceThemes)) {
            foreach (scandir($sourceThemes) ?: [] as $theme) {
                if ($theme === '.' || $theme === '..') { continue; }
                $source = $sourceThemes . '/' . $theme;
                if (!is_dir($source)) { continue; }
                $this->copyDirectory($source, $publicPath . '/themes/' . $theme, $overwrite, $written);
            }
        }

        return $written;
    }

    private function copyDirectory(string $source, string $target, bool $overwrite, array &$written): void
    {
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException("Unable to create directory: {$target}");
        }

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $from = $source . '/' . $entry;
            $to = $target . '/' . $entry;
            if (is_dir($from)) {
                $this->copyDirectory($from, $to, $overwrite, $written);
            } else {
                $this->copyFile($from, $to, $overwrite);
                $written[] = $to;
            }
        }
    }

    private function copyFile(string $source, string $target, bool $overwrite): void
    {
        if (!is_file($source)) {
            throw new RuntimeException("Prefab Theme asset not found: {$source}");
        }
        if (is_file($target) && !$overwrite) { return; }

        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }
        if (!copy($source, $target)) {
            throw new RuntimeException("Unable to publish theme asset: {$target}");
        }
    }
}
