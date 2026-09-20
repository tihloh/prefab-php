<?php

declare(strict_types=1);

require __DIR__ . '/../src/ThemeDefinition.php';
require __DIR__ . '/../src/ThemeRegistry.php';
require __DIR__ . '/../src/ThemeAppearance.php';
require __DIR__ . '/../src/ThemeResolver.php';
require __DIR__ . '/../src/ThemePublisher.php';
require __DIR__ . '/../src/ThemeInstaller.php';
require __DIR__ . '/../src/ThemeManager.php';

use Tihloh\Prefab\Theme\ThemeManager;
use Tihloh\Prefab\Theme\ThemeRegistry;
use Tihloh\Prefab\Theme\ThemeResolver;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$registry = new ThemeRegistry([__DIR__ . '/../themes']);
check($registry->has('default'), 'Default theme must be discovered.');
check($registry->get('default')?->supportsMode('dark') === true, 'Default theme must support dark mode.');

$resolver = new ThemeResolver($registry, [
    'default' => 'default',
    'mode' => 'light',
    'themes' => [
        'default' => ['modes' => ['light', 'dark']],
    ],
    'user' => [
        'enabled' => true,
        'theme' => true,
        'mode' => true,
        'density' => true,
    ],
]);

$appearance = $resolver->resolve(['mode' => 'dark', 'density' => 'compact']);
check($appearance->theme === 'default', 'Application default theme should be used.');
check($appearance->mode === 'dark', 'Allowed user mode should override application mode.');
check($appearance->density === 'compact', 'Allowed user density should override application density.');
check($appearance->source['mode'] === 'user', 'Resolver should report user mode source.');

$locked = new ThemeResolver($registry, [
    'default' => 'default',
    'mode' => 'light',
    'user' => ['enabled' => false],
]);
check($locked->resolve(['mode' => 'dark'])->mode === 'light', 'Disabled user customization must be ignored.');

$public = sys_get_temp_dir() . '/prefab-theme-test-' . bin2hex(random_bytes(4));
$manager = new ThemeManager([
    'public_path' => $public,
    'asset_url' => '/assets/prefab-theme',
    'default' => 'default',
    'mode' => 'system',
    'themes' => ['default'],
]);
$manager->publish();

check(is_file($public . '/core.css'), 'Core CSS should publish.');
check(is_file($public . '/theme.js'), 'Theme JS should publish.');
check(is_file($public . '/themes/default/theme.json'), 'Default theme should publish.');

$resolved = $manager->resolve();
check($resolved->mode === 'system', 'System mode should be allowed when light and dark exist.');
check(str_contains($manager->styles($resolved), 'prefers-color-scheme: dark'), 'System mode should render dark media stylesheet.');
check(str_contains($manager->scripts($resolved), 'prefab-theme-config'), 'Theme scripts should include client configuration.');

function removeTree(string $directory): void
{
    if (!is_dir($directory)) { return; }

    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $path = $directory . '/' . $entry;
        is_dir($path) ? removeTree($path) : unlink($path);
    }

    rmdir($directory);
}

removeTree($public);

echo "Prefab Theme tests passed.\n";
