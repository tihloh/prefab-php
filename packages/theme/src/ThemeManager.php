<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;

final class ThemeManager
{
    private ThemeRegistry $registry;
    private ThemeResolver $resolver;
    private string $packageRoot;
    private ThemeAssetRenderer $assetRenderer;
    private array $localConfig;
    private array $config = [];
    private array $sources = [];
    private ?ThemeAppearance $appearance = null;

    public function __construct(array $config = [], ?ThemeRegistry $registry = null)
    {
        $this->packageRoot = dirname(__DIR__);
        $this->assetRenderer = new ThemeAssetRenderer($this->packageRoot);
        $this->localConfig = $config;
        $this->registry = $registry ?? new ThemeRegistry();

        $this->configureState();
        PrefabRuntime::register('theme', $this);
    }

    public function prefabConfigure(): void
    {
        $this->configureState();

        foreach ($this->sources as $resource => $source) {
            PrefabRuntime::recordResolution('theme', $resource, $source);
        }

        PrefabRuntime::provide(
            'theme_manager',
            $this,
            'prefab-theme',
        );
    }

    public function resolve(array $userPreferences = []): ThemeAppearance
    {
        return PrefabRuntime::traceCall(
            'theme',
            'resolve',
            ['user_preferences' => array_keys($userPreferences)],
            fn (): ThemeAppearance => $this->resolver->resolve($userPreferences),
        );
    }

    public function apply(array $userPreferences = []): self
    {
        $this->appearance = $this->resolve($userPreferences);
        return $this;
    }

    public function appearance(): ThemeAppearance
    {
        return $this->appearance ??= $this->resolve();
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

    public function attributes(?ThemeAppearance $appearance = null): string
    {
        $appearance ??= $this->appearance();
        $attributes = [
            'data-theme="' . $this->escape($appearance->theme) . '"',
            'data-mode="' . $this->escape($appearance->mode) . '"',
            'data-density="' . $this->escape($appearance->density) . '"',
        ];

        if ($appearance->accent !== null) {
            $attributes[] = 'data-accent="' . $this->escape($appearance->accent) . '"';
        }

        if (in_array($appearance->mode, ['light', 'dark'], true)) {
            $attributes[] = 'data-bs-theme="' . $this->escape($appearance->mode) . '"';
        }

        return implode(' ', $attributes);
    }

    public function styles(?ThemeAppearance $appearance = null): string
    {
        $appearance ??= $this->appearance();

        $styles = $this->assetMode() === 'published'
            ? $this->publishedStyles($appearance)
            : $this->inlineStyles($appearance);
        $accent = $this->accentStyle($appearance);

        return $accent === '' ? $styles : $styles . "\n" . $accent;
    }

    public function scripts(?ThemeAppearance $appearance = null): string
    {
        $appearance ??= $this->appearance();
        $config = $this->clientConfig($appearance);
        $json = json_encode(
            $config,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );

        if ($json === false) {
            throw new RuntimeException('Unable to encode Prefab Theme client configuration.');
        }

        $configTag = '<script type="application/json" id="prefab-theme-config">' . $json . '</script>';

        if ($this->assetMode() === 'published') {
            return $configTag . "\n"
                . '<script src="' . $this->escape($this->asset('/theme.js')) . '" defer></script>';
        }

        return $configTag . "\n"
            . '<script>' . $this->safeScript($this->assetRenderer->runtimeJs()) . '</script>';
    }

    public function publish(?string $publicPath = null, bool $overwrite = true): array
    {
        return PrefabRuntime::traceCall(
            'theme',
            'publish',
            ['overwrite' => $overwrite],
            function () use ($publicPath, $overwrite): array {
                $publicPath ??= is_string($this->config['public_path'])
                    ? $this->config['public_path']
                    : null;

                if (!$publicPath) {
                    throw new RuntimeException('Prefab Theme public_path is required to publish assets.');
                }

                $written = (new ThemePublisher($this->packageRoot))
                    ->publish($publicPath, $overwrite);

                $this->registry
                    ->addPath(rtrim($publicPath, DIRECTORY_SEPARATOR) . '/themes')
                    ->refresh();

                return $written;
            },
        );
    }

    public function installer(?string $themesPath = null): ThemeInstaller
    {
        if ($themesPath === null) {
            $themesPath = is_string($this->config['themes_path'])
                && $this->config['themes_path'] !== ''
                ? $this->config['themes_path']
                : null;

            if ($themesPath === null) {
                $publicPath = is_string($this->config['public_path'])
                    ? $this->config['public_path']
                    : null;

                if ($publicPath) {
                    $themesPath = rtrim($publicPath, DIRECTORY_SEPARATOR) . '/themes';
                }
            }

            if (!$themesPath) {
                throw new RuntimeException(
                    'Prefab Theme themes_path is required only when installing downloaded themes.'
                );
            }
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
            'components' => (array) $this->config['components'],
            'registry_errors' => $this->registry->errors(),
            'user_policy' => $this->resolver->userPolicy(),
            'runtime' => PrefabRuntime::explainData('theme'),
        ];
    }

    public function config(): array
    {
        return $this->config;
    }

    private function configureState(): void
    {
        $defaults = self::defaults();
        $config = [];
        $sources = [];

        foreach ($defaults as $key => $default) {
            $resolved = PrefabConfig::resolve(
                'theme',
                $key,
                $this->localConfig,
                $default,
            );

            $value = $resolved['value'];

            if (
                is_array($default)
                && is_array($value)
                && !array_is_list($default)
                && !array_is_list($value)
            ) {
                $value = array_replace_recursive($default, $value);
            }

            $config[$key] = $value;
            $sources[$key] = (string) $resolved['source'];
        }

        $this->config = $config;
        $this->sources = $sources;

        $this->registry->addPath($this->packageRoot . '/themes');

        if (is_string($this->config['themes_path']) && $this->config['themes_path'] !== '') {
            $this->registry->addPath(
                rtrim($this->config['themes_path'], DIRECTORY_SEPARATOR),
            );
        }

        if (is_string($this->config['public_path']) && $this->config['public_path'] !== '') {
            $this->registry->addPath(
                rtrim($this->config['public_path'], DIRECTORY_SEPARATOR) . '/themes',
            );
        }

        $this->resolver = new ThemeResolver($this->registry, $this->config);
        $this->appearance = null;
    }

    private function clientConfig(ThemeAppearance $appearance): array
    {
        $themes = [];
        $inline = $this->assetMode() === 'inline';

        foreach ($this->resolver->enabledThemes() as $id => $entry) {
            /** @var ThemeDefinition $theme */
            $theme = $entry['theme'];
            $modes = [];

            foreach ($entry['modes'] as $mode) {
                $file = $theme->modeFile($mode);

                if ($file !== null) {
                    $modes[$mode] = $inline
                        ? $this->assetRenderer->themeCss($theme, $file)
                        : $this->themeAsset($theme, $file);
                }
            }

            $themes[$id] = [
                'name' => $theme->name,
                'base' => $theme->base !== null
                    ? ($inline
                        ? $this->assetRenderer->themeCss($theme, $theme->base)
                        : $this->themeAsset($theme, $theme->base))
                    : null,
                'modes' => $modes,
            ];
        }

        return [
            'current' => $appearance->toArray(),
            'themes' => $themes,
            'densities' => array_values((array) $this->config['densities']),
            'accents' => $this->resolver->accents(),
            'customAccent' => $this->resolver->customAccentAllowed(),
            'user' => $this->resolver->userPolicy(),
            'toggle' => (array) $this->config['toggle'],
            'assetMode' => $this->assetMode(),
            'saveUrl' => $this->config['save_url'],
            'storageKey' => (string) $this->config['storage_key'],
        ];
    }

    private function inlineStyles(ThemeAppearance $appearance): string
    {
        [$theme, $entry] = $this->resolvedTheme($appearance);
        $lines = [
            '<style data-prefab-core>'
            . $this->safeStyle($this->assetRenderer->coreCss())
            . '</style>',
        ];

        if ($this->adminComponentsEnabled()) {
            $lines[] = '<style data-prefab-admin>'
                . $this->safeStyle($this->assetRenderer->adminCss())
                . '</style>';
        }

        if ($theme->base !== null) {
            $lines[] = '<style data-prefab-theme-base>'
                . $this->safeStyle($this->assetRenderer->themeCss($theme, $theme->base))
                . '</style>';
        }

        foreach (['light', 'dark'] as $mode) {
            $file = $theme->modeFile($mode);

            if ($file === null || !in_array($mode, $entry['modes'], true)) {
                continue;
            }

            $media = match ($appearance->mode) {
                'system' => "(prefers-color-scheme: {$mode})",
                $mode => 'all',
                default => 'not all',
            };

            $lines[] = sprintf(
                '<style media="%s" data-prefab-theme-mode="%s">%s</style>',
                $this->escape($media),
                $this->escape($mode),
                $this->safeStyle($this->assetRenderer->themeCss($theme, $file)),
            );
        }

        foreach ($entry['modes'] as $mode) {
            if (in_array($mode, ['light', 'dark'], true)) {
                continue;
            }

            $file = $theme->modeFile($mode);

            if ($file === null || $appearance->mode !== $mode) {
                continue;
            }

            $lines[] = sprintf(
                '<style data-prefab-theme-mode="%s">%s</style>',
                $this->escape($mode),
                $this->safeStyle($this->assetRenderer->themeCss($theme, $file)),
            );
        }

        return implode("\n", $lines);
    }

    private function publishedStyles(ThemeAppearance $appearance): string
    {
        [$theme, $entry] = $this->resolvedTheme($appearance);
        $lines = [
            '<link rel="stylesheet" href="' . $this->escape($this->asset('/core.css')) . '" data-prefab-core>',
        ];

        if ($this->adminComponentsEnabled()) {
            $lines[] = '<link rel="stylesheet" href="' . $this->escape($this->asset('/admin.css')) . '" data-prefab-admin>';
        }

        if ($theme->base !== null) {
            $lines[] = '<link rel="stylesheet" href="' . $this->escape($this->themeAsset($theme, $theme->base)) . '" data-prefab-theme-base>';
        }

        foreach (['light', 'dark'] as $mode) {
            $file = $theme->modeFile($mode);

            if ($file === null || !in_array($mode, $entry['modes'], true)) {
                continue;
            }

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
            if (in_array($mode, ['light', 'dark'], true)) {
                continue;
            }

            $file = $theme->modeFile($mode);

            if ($file === null || $appearance->mode !== $mode) {
                continue;
            }

            $lines[] = sprintf(
                '<link rel="stylesheet" href="%s" data-prefab-theme-mode="%s">',
                $this->escape($this->themeAsset($theme, $file)),
                $this->escape($mode),
            );
        }

        return implode("\n", $lines);
    }

    /** @return array{0: ThemeDefinition, 1: array} */
    private function accentStyle(ThemeAppearance $appearance): string
    {
        $color = $this->resolver->accentColor($appearance->accent);

        if ($color === null) {
            return '';
        }

        return sprintf(
            '<style data-prefab-accent>html[data-accent]{--pf-primary:%s;--pf-primary-contrast:%s}</style>',
            $this->escape($color),
            $this->escape($this->accentContrast($color)),
        );
    }

    private function accentContrast(string $color): string
    {
        $hex = ltrim($color, '#');
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance >= 150 ? '#111111' : '#ffffff';
    }

    private function resolvedTheme(ThemeAppearance $appearance): array
    {
        $entry = $this->resolver->enabledThemes()[$appearance->theme] ?? null;

        if (!$entry) {
            throw new RuntimeException("Theme is not enabled: {$appearance->theme}");
        }

        return [$entry['theme'], $entry];
    }

    private function assetMode(): string
    {
        return strtolower((string) $this->config['asset_mode']) === 'published'
            ? 'published'
            : 'inline';
    }

    private function adminComponentsEnabled(): bool
    {
        return (bool) (
            is_array($this->config['components'])
                ? ($this->config['components']['admin'] ?? false)
                : false
        );
    }

    private function themeAsset(ThemeDefinition $theme, string $file): string
    {
        return $this->asset(
            '/themes/'
            . rawurlencode($theme->id)
            . '/'
            . str_replace(DIRECTORY_SEPARATOR, '/', $file),
        );
    }

    private function asset(string $path): string
    {
        return rtrim((string) $this->config['asset_url'], '/')
            . '/'
            . ltrim($path, '/');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }

    private function safeStyle(string $css): string
    {
        return preg_replace('~</style~i', '<\\/style', $css) ?? $css;
    }

    private function safeScript(string $script): string
    {
        return preg_replace('~</script~i', '<\\/script', $script) ?? $script;
    }

    private static function defaults(): array
    {
        return [
            'default' => 'default',
            'mode' => 'light',
            'density' => 'comfortable',
            'densities' => ['comfortable', 'compact'],
            'accent' => null,
            'accents' => [
                'blue' => '#0d6efd',
                'purple' => '#6f42c1',
                'green' => '#198754',
                'teal' => '#0f766e',
                'orange' => '#fd7e14',
                'red' => '#dc3545',
            ],
            'custom_accent' => false,
            'themes' => [],
            'user' => [
                'enabled' => false,
                'theme' => false,
                'mode' => false,
                'density' => false,
                'accent' => false,
            ],
            'components' => [
                'admin' => false,
            ],
            'toggle' => [
                'enabled' => false,
                'position' => 'bottom-right',
            ],
            'asset_mode' => 'inline',
            'themes_path' => null,
            'public_path' => null,
            'asset_url' => '/assets/prefab-theme',
            'save_url' => null,
            'storage_key' => 'prefab.theme',
        ];
    }
}
