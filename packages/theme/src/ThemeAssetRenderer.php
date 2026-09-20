<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use RuntimeException;

final class ThemeAssetRenderer
{
    public function __construct(
        private readonly string $packageRoot,
    ) {}

    public function coreCss(): string
    {
        return $this->read($this->packageRoot . '/assets/core.css');
    }

    public function adminCss(): string
    {
        return $this->read($this->packageRoot . '/assets/admin.css');
    }

    public function runtimeJs(): string
    {
        return $this->read($this->packageRoot . '/assets/theme.js');
    }

    public function themeCss(ThemeDefinition $theme, string $file): string
    {
        $root = realpath($theme->directory);
        $path = realpath(
            $theme->directory
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file),
        );

        if ($root === false || $path === false || !$this->inside($path, $root)) {
            throw new RuntimeException("Theme asset is outside the theme directory: {$file}");
        }

        $css = $this->read($path);

        return preg_replace_callback(
            '~url\(\s*(["\']?)([^)"\']+)\1\s*\)~i',
            function (array $match) use ($path, $root): string {
                $source = trim($match[2]);

                if (
                    $source === ''
                    || str_starts_with($source, '#')
                    || str_starts_with($source, 'data:')
                    || preg_match('~^[a-z][a-z0-9+.-]*:~i', $source)
                    || str_starts_with($source, '//')
                    || str_starts_with($source, '/')
                ) {
                    return $match[0];
                }

                $parts = preg_split('/(?=[?#])/', $source, 2);
                $relative = $parts[0] ?? $source;
                $asset = realpath(dirname($path) . DIRECTORY_SEPARATOR . rawurldecode($relative));

                if ($asset === false || !$this->inside($asset, $root) || !is_file($asset)) {
                    return $match[0];
                }

                $mime = $this->mime($asset);
                if ($mime === null) {
                    return $match[0];
                }

                $bytes = file_get_contents($asset);
                if ($bytes === false) {
                    return $match[0];
                }

                return 'url("data:' . $mime . ';base64,' . base64_encode($bytes) . '")';
            },
            $css,
        ) ?? $css;
    }

    private function read(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException("Theme asset is not readable: {$file}");
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Unable to read Theme asset: {$file}");
        }

        return $content;
    }

    private function inside(string $path, string $root): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        return $path === $root
            || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function mime(string $file): ?string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            default => null,
        };
    }
}
