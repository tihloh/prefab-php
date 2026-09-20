<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

use RuntimeException;

final class ThemeResolver
{
    private array $config;

    public function __construct(
        private readonly ThemeRegistry $registry,
        array $config = [],
    ) {
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
        ], $config);
    }

    public function resolve(array $userPreferences = []): ThemeAppearance
    {
        $enabled = $this->enabledThemes();
        $default = strtolower((string) $this->config['default']);

        if (!isset($enabled[$default])) {
            throw new RuntimeException("Default theme is not installed/enabled: {$default}");
        }

        $theme = $default;
        $source = [
            'theme' => 'application',
            'mode' => 'application',
            'density' => 'application',
        ];

        if ($this->userAllows('theme')) {
            $candidate = strtolower(trim((string) ($userPreferences['theme'] ?? '')));
            if ($candidate !== '' && isset($enabled[$candidate])) {
                $theme = $candidate;
                $source['theme'] = 'user';
            }
        }

        $allowedModes = $enabled[$theme]['modes'];
        $mode = $this->validMode((string) $this->config['mode'], $allowedModes);

        if ($this->userAllows('mode')) {
            $candidate = strtolower(trim((string) ($userPreferences['mode'] ?? '')));
            if ($candidate !== '' && $this->isModeAllowed($candidate, $allowedModes)) {
                $mode = $candidate;
                $source['mode'] = 'user';
            }
        }

        $densities = array_values(array_unique(array_map(
            static fn ($value): string => strtolower((string) $value),
            (array) $this->config['densities'],
        )));
        $density = strtolower((string) $this->config['density']);
        if (!in_array($density, $densities, true)) {
            $density = $densities[0] ?? 'comfortable';
        }

        if ($this->userAllows('density')) {
            $candidate = strtolower(trim((string) ($userPreferences['density'] ?? '')));
            if ($candidate !== '' && in_array($candidate, $densities, true)) {
                $density = $candidate;
                $source['density'] = 'user';
            }
        }

        return new ThemeAppearance($theme, $mode, $density, $source);
    }

    /** @return array<string, array{theme:ThemeDefinition,modes:string[]}> */
    public function enabledThemes(): array
    {
        $configured = (array) $this->config['themes'];
        $default = strtolower((string) $this->config['default']);
        $entries = [];

        if ($configured === []) {
            $entries[$default] = null;
        } else {
            foreach ($configured as $key => $value) {
                if (is_int($key)) {
                    $entries[strtolower((string) $value)] = null;
                } else {
                    $entries[strtolower((string) $key)] = $value;
                }
            }
            $entries[$default] ??= null;
        }

        $enabled = [];
        foreach ($entries as $id => $settings) {
            $theme = $this->registry->get($id);
            if (!$theme) { continue; }

            $manifestModes = array_keys($theme->modes);
            $requested = is_array($settings)
                ? (array) ($settings['modes'] ?? $manifestModes)
                : $manifestModes;
            $modes = array_values(array_intersect(
                $manifestModes,
                array_map(static fn ($mode): string => strtolower((string) $mode), $requested),
            ));

            if ($modes === []) { continue; }
            $enabled[$id] = ['theme' => $theme, 'modes' => $modes];
        }

        return $enabled;
    }

    public function userPolicy(): array
    {
        return (array) $this->config['user'];
    }

    private function userAllows(string $setting): bool
    {
        $policy = (array) $this->config['user'];
        if (!(bool) ($policy['enabled'] ?? false)) { return false; }

        return (bool) (
            $policy[$setting]
            ?? $policy['allow_' . $setting]
            ?? false
        );
    }

    /** @param string[] $allowedModes */
    private function validMode(string $mode, array $allowedModes): string
    {
        $mode = strtolower(trim($mode));
        if ($this->isModeAllowed($mode, $allowedModes)) {
            return $mode;
        }
        return $allowedModes[0] ?? 'light';
    }

    /** @param string[] $allowedModes */
    private function isModeAllowed(string $mode, array $allowedModes): bool
    {
        if ($mode === 'system') {
            return in_array('light', $allowedModes, true)
                && in_array('dark', $allowedModes, true);
        }
        return in_array($mode, $allowedModes, true);
    }
}
