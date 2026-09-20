<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use RuntimeException;

final class ThemeManager
{
    private ThemeRegistry $registry;
    private ThemeResolver $resolver;
    private string $packageRoot;
    private array $config;

    public function __construct(array $config = [], ?ThemeRegistry $registry = null)
    {
        $this->packageRoot = dirname(__DIR__);
        $this->config = array_replace_recursive([
            'default' => 'default',
            'mode' => 'light',
            'density' => 'comfortable',
            'densities' => ['comfortable', 'compact'],
            'themes' => [],
            'user' => [
                'enabled' => false,
                'theme' => false,
                'mode' => false,
                'density' => false,
            ],
            'public_path' => null,
            'asset_url' => '/assets/prefab-theme',
            'save_url' => null,
        ], $config);

        $paths = [$this->packageRoot . '/themes'];
        if (is_string($this->config['public_path']) && $this->config['public_path'] !== '') {
            $paths[] = rtrim($this->config['public_path'], DIRECTORY_SEPARATOR) . '/themes';
        }

        $this->registry = $registry ?? new ThemeRegistry($paths);
        $this->resolver = new ThemeResolver($this->registry, $this->config);
    }

    public function resolve(array $userPreferences = []): ThemeAppearance
    {
        return $this->resolver->resolve($userPreferences);
    }

    /** @return array<string, ThemeDefinition> */
    public function installed(): array
    {
        return $this->registry->all();
    }

    public function available(): array
    {
        return $this->resolver->enabledThemes();
    }

    public function attributes(ThemeAppearance $appearance): string
    {
        $attributes = [
            'data-theme="' . $this->escape($appearance->theme) . '"',
            'data-mode="' . $this->escape($appearance->mode) . '"',
            'data-density="' . $this->escape($appearance->density) . '"',
        ];

        if (in_array($appearance->mode, ['light', 'dark'], true)) {
            $attributes[] = 'data-bs-theme="' . $this->escape($appearance->mode) . '"';
        }

        return implode(' ', $attributes);
    }

    public function styles(ThemeAppearance $appearance): string
    {
        $enabled = $this->resolver->enabledThemes();
        $entry = $enabled[$appearance->theme] ?? null;
        if (!$entry) {
            throw new RuntimeException("Theme is not enabled: {$appearance->theme}");
        }

        /** @var ThemeDefinition $theme */
        $theme = $entry['theme'];
        $lines = [
            '<link rel="stylesheet" href="' . $this->escape($this->asset('/core.css')) . '" data-prefab-core>',
        ];

        if ($theme->base !== null) {
            $lines[] = '<link rel="stylesheet" href="' . $this->escape($this->themeAsset($theme, $theme->base)) . '" data-prefab-theme-base>';
        }

        foreach (['light', 'dark'] as $mode) {
            $file = $theme->modeFile($mode);
            if ($file === null || !in_array($mode, $entry['modes'], true)) { continue; }
            $media = match ($appearance->mode) {
                'system' => "(prefers-color-scheme: {$mode})",
                $mode => 'all',
                default => 'not all',
            };
            $lines[] = sprintf(
                '<link rel="stylesheet" href="%s" media="%s" data-prefab-theme-mode="%s">',
                $this->escape($this->themeAsset($theme, $file)),
                $this->escape($media),
                $this->escape($mode),
            );
        }

        foreach ($entry['modes'] as $mode) {
            if (in_array($mode, ['light', 'dark'], true)) { continue; }
            $file = $theme->modeFile($mode);
            if ($file === null || $appearance->mode !== $mode) { continue; }
            $lines[] = sprintf(
                '<link rel="stylesheet" href="%s" data-prefab-theme-mode="%s">',
                $this->escape($this->themeAsset($theme, $file)),
                $this->escape($mode),
            );
        }

        return implode("\n", $lines);
    }

    public function scripts(ThemeAppearance $appearance): string
    {
        $config = $this->clientConfig($appearance);
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            throw new RuntimeException('Unable to encode Prefab Theme client configuration.');
        }

        return '<script type="application/json" id="prefab-theme-config">' . $json . '</script>' . "\n"
            . '<script src="' . $this->escape($this->asset('/theme.js')) . '" defer></script>';
    }

    public function publish(?string $publicPath = null, bool $overwrite = false): array
    {
        $publicPath ??= is_string($this->config['public_path']) ? $this->config['public_path'] : null;
        if (!$publicPath) {
            throw new RuntimeException('Prefab Theme public_path is required to publish assets.');
        }

        $written = (new ThemePublisher($this->packageRoot))->publish($publicPath, $overwrite);
        $this->registry->addPath(rtrim($publicPath, DIRECTORY_SEPARATOR) . '/themes')->refresh();
        return $written;
    }

    public function installer(?string $themesPath = null): ThemeInstaller
    {
        if ($themesPath === null) {
            $publicPath = is_string($this->config['public_path']) ? $this->config['public_path'] : null;
            if (!$publicPath) {
                throw new RuntimeException('Prefab Theme public_path is required to install themes.');
            }
            $themesPath = rtrim($publicPath, DIRECTORY_SEPARATOR) . '/themes';
        }
        return new ThemeInstaller($themesPath);
    }

    public function refresh(): self
    {
        $this->registry->refresh();
        return $this;
    }

    public function explain(array $userPreferences = []): array
    {
        $appearance = $this->resolve($userPreferences);
        return [
            'appearance' => $appearance->toArray(),
            'installed' => array_keys($this->installed()),
            'enabled' => array_keys($this->available()),
            'registry_errors' => $this->registry->errors(),
            'user_policy' => $this->resolver->userPolicy(),
        ];
    }

    private function clientConfig(ThemeAppearance $appearance): array
    {
        $themes = [];
        foreach ($this->resolver->enabledThemes() as $id => $entry) {
            /** @var ThemeDefinition $theme */
            $theme = $entry['theme'];
            $modes = [];
            foreach ($entry['modes'] as $mode) {
                $file = $theme->modeFile($mode);
                if ($file !== null) { $modes[$mode] = $this->themeAsset($theme, $file); }
            }
            $themes[$id] = [
                'name' => $theme->name,
                'base' => $theme->base !== null ? $this->themeAsset($theme, $theme->base) : null,
                'modes' => $modes,
            ];
        }

        return [
            'current' => $appearance->toArray(),
            'themes' => $themes,
            'densities' => array_values((array) $this->config['densities']),
            'user' => $this->resolver->userPolicy(),
            'saveUrl' => $this->config['save_url'],
        ];
    }

    private function themeAsset(ThemeDefinition $theme, string $file): string
    {
        return $this->asset('/themes/' . rawurlencode($theme->id) . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $file));
    }

    private function asset(string $path): string
    {
        return rtrim((string) $this->config['asset_url'], '/') . '/' . ltrim($path, '/');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
