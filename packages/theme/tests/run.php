<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Theme\ThemeManager;
use Tihloh\Prefab\Theme\ThemeRegistry;
use Tihloh\Prefab\Theme\ThemeResolver;
use ZipArchive;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . '/' . $entry;
        is_dir($path) ? removeTree($path) : unlink($path);
    }

    rmdir($directory);
}

PrefabConfig::reset();
PrefabRuntime::reset();

$registry = new ThemeRegistry([__DIR__ . '/../themes']);
check($registry->has('default'), 'Default theme must be discovered.');
check(
    $registry->get('default')?->supportsMode('dark') === true,
    'Default theme must support dark mode.',
);

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

$appearance = $resolver->resolve([
    'mode' => 'dark',
    'density' => 'compact',
]);

check(
    $appearance->theme === 'default',
    'Application default theme should be used.',
);
check(
    $appearance->mode === 'dark',
    'Allowed user mode should override application mode.',
);
check(
    $appearance->density === 'compact',
    'Allowed user density should override application density.',
);
check(
    $appearance->source['mode'] === 'user',
    'Resolver should report user mode source.',
);

$locked = new ThemeResolver($registry, [
    'default' => 'default',
    'mode' => 'light',
    'user' => ['enabled' => false],
]);

check(
    $locked->resolve(['mode' => 'dark'])->mode === 'light',
    'Disabled user customization must be ignored.',
);

$public = sys_get_temp_dir()
    . '/prefab-theme-test-'
    . bin2hex(random_bytes(4));

PrefabConfig::set([
    'modules' => [
        'theme' => [
            'mode' => 'dark',
            'components' => [
                'admin' => true,
            ],
            'user' => [
                'enabled' => true,
                'mode' => true,
            ],
        ],
    ],
]);

$manager = new ThemeManager([
    'themes_path' => $public . '/installed-themes',
    'default' => 'default',
    'density' => 'compact',
    'themes' => ['default'],
]);

$manager->apply();

check(
    $manager->appearance()->mode === 'dark',
    'PrefabConfig module mode should configure Theme.',
);
check(
    $manager->appearance()->density === 'compact',
    'Direct ThemeManager configuration should override shared configuration.',
);
check(
    PrefabRuntime::resolve('theme_manager') === $manager,
    'ThemeManager should register the theme_manager Prefab capability.',
);
check(
    PrefabRuntime::get('theme') === $manager,
    'ThemeManager should register itself as the theme module.',
);

$styles = $manager->styles();
check(
    str_contains($styles, 'data-prefab-admin'),
    'Admin component stylesheet should load when enabled.',
);
check(
    str_contains($styles, '<style data-prefab-core>'),
    'Theme should inline core CSS by default without publishing.',
);
check(
    str_contains($styles, 'data-prefab-theme-mode="dark"'),
    'Resolved dark mode should render inline theme CSS.',
);
check(
    !str_contains($styles, '<link rel="stylesheet"'),
    'Default inline mode should not require published CSS files.',
);
check(
    str_contains($manager->attributes(), 'data-bs-theme="dark"'),
    'Explicit dark mode should synchronize Bootstrap color mode.',
);
check(
    str_contains($manager->scripts(), 'prefab-theme-config'),
    'Theme scripts should include client configuration.',
);
check(
    str_contains($manager->scripts(), '"storageKey":"prefab.theme"'),
    'Theme scripts should expose automatic persistence configuration.',
);
check(
    str_contains($manager->scripts(), '"toggle":{"enabled":false'),
    'Theme scripts should expose floating toggle configuration.',
);
check(
    str_contains($manager->scripts(), '"assetMode":"inline"'),
    'Theme scripts should declare inline asset mode by default.',
);
check(
    str_contains($manager->scripts(), 'window.PrefabTheme = api'),
    'Default inline mode should embed the Theme runtime without publishing.',
);


$runtime = file_get_contents(__DIR__ . '/../assets/theme.js') ?: '';
check(
    str_contains($runtime, '[pf\\\\:theme]'),
    'Theme runtime should support pf:theme.',
);
check(
    str_contains($runtime, '[pf\\\\:theme-mode]'),
    'Theme runtime should support pf:theme-mode.',
);
check(
    str_contains($runtime, '[pf\\\\:theme-density]'),
    'Theme runtime should support pf:theme-density.',
);
check(
    !str_contains($runtime, 'data-prefab-mode'),
    'Legacy generic Theme data controls should not own the public directive API.',
);

$published = new ThemeManager([
    'asset_mode' => 'published',
    'public_path' => $public . '/published',
    'asset_url' => '/assets/prefab-theme',
    'default' => 'default',
    'mode' => 'dark',
    'themes' => ['default'],
]);

$published->publish();

check(
    is_file($public . '/published/core.css'),
    'Optional publish() should still publish core CSS.',
);
check(
    is_file($public . '/published/admin.css'),
    'Optional publish() should still publish admin CSS.',
);
check(
    is_file($public . '/published/theme.js'),
    'Optional publish() should still publish the Theme runtime.',
);
check(
    is_file($public . '/published/themes/default/theme.json'),
    'Optional publish() should still publish the bundled default theme.',
);
check(
    str_contains($published->styles(), '<link rel="stylesheet"'),
    'Published asset mode should render stylesheet links.',
);
check(
    str_contains($published->styles(), 'dark.css'),
    'Published asset mode should reference the resolved theme mode file.',
);
check(
    str_contains($published->scripts(), 'src="/assets/prefab-theme/theme.js"'),
    'Published asset mode should reference the published runtime.',
);

$manager->apply([
    'mode' => 'light',
    'density' => 'comfortable',
]);
check(
    str_contains($manager->attributes(), 'data-bs-theme="light"'),
    'apply() should update the request appearance once for later rendering.',
);

$systemManager = new ThemeManager([
    'default' => 'default',
    'mode' => 'system',
    'themes' => ['default'],
]);

check(
    $systemManager->appearance()->mode === 'system',
    'System mode should be allowed when light and dark exist.',
);
check(
    str_contains(
        $systemManager->styles(),
        'prefers-color-scheme: dark',
    ),
    'System mode should render the dark media stylesheet.',
);

if (class_exists(ZipArchive::class)) {
    $archive = $public . '/sample-theme.zip';
    $zip = new ZipArchive();

    check(
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true,
        'Theme test ZIP should open.',
    );

    $zip->addFromString('theme.json', json_encode([
        'id' => 'sample',
        'name' => 'Sample',
        'version' => '1.0.0',
        'base' => 'base.css',
        'modes' => [
            'light' => 'light.css',
            'dark' => 'dark.css',
        ],
    ], JSON_THROW_ON_ERROR));
    $zip->addFromString('base.css', ':root { --pf-radius: 6px; }');
    $zip->addFromString('light.css', ':root { --pf-bg: #fff; }');
    $zip->addFromString('dark.css', ':root { --pf-bg: #111; }');
    $zip->close();

    $installed = $manager->installer()->installZip($archive);
    check($installed->id === 'sample', 'Theme ZIP should install.');
    $manager->refresh();
    check(
        isset($manager->installed()['sample']),
        'Installed theme should be discoverable after refresh.',
    );

    $unsafe = $public . '/unsafe-theme.zip';
    $zip = new ZipArchive();
    check(
        $zip->open($unsafe, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true,
        'Unsafe theme test ZIP should open.',
    );
    $zip->addFromString('theme.json', json_encode([
        'id' => 'unsafe',
        'name' => 'Unsafe',
        'modes' => ['light' => 'light.css'],
    ], JSON_THROW_ON_ERROR));
    $zip->addFromString('light.css', ':root {}');
    $zip->addFromString('payload.php', '<?php echo "unsafe";');
    $zip->close();

    $rejected = false;

    try {
        $manager->installer()->installZip($unsafe);
    } catch (RuntimeException) {
        $rejected = true;
    }

    check(
        $rejected,
        'Theme installer should reject server-side executable files.',
    );
}

$explain = $manager->explain();
check(
    isset($explain['runtime']['mode']),
    'Theme diagnostics should include Prefab runtime resolution data.',
);

removeTree($public);

echo "Prefab Theme tests passed.\n";
