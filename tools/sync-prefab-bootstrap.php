<?php

/**
 * Synchronize Prefab's canonical shared interoperability and diagnostics files
 * into all standalone packages.
 *
 * Usage from the repository root:
 *
 *     php tools/sync-prefab-bootstrap.php
 */

$root = dirname(__DIR__);
$packages = ['database', 'users', 'auth', 'permissions', 'logs', 'routes', 'input', 'files', 'messaging', 'notifications'];

$targets = static function (string $file) use ($root, $packages): array {
    return array_map(
        static fn (string $package): string => $root . '/packages/' . $package . '/src/' . $file,
        $packages,
    );
};

$groups = [
    [
        'source' => $root . '/tools/prefab-bootstrap.php',
        'targets' => $targets('prefab.php'),
    ],
    [
        'source' => $root . '/tools/prefab-diagnostics.php',
        'targets' => $targets('prefab-diagnostics.php'),
    ],
    [
        'source' => $root . '/tools/prefab-debug-ui.php',
        'targets' => $targets('prefab-debug-ui.php'),
    ],
    [
        'source' => $root . '/tools/database-contracts.php',
        'targets' => [
            $root . '/packages/database/src/database.php',
            $root . '/packages/users/src/database.php',
            $root . '/packages/permissions/src/database.php',
            $root . '/packages/logs/src/database.php',
        ],
    ],
];

foreach ($groups as $group) {
    $content = file_get_contents($group['source']);
    if ($content === false) {
        throw new RuntimeException("Unable to read canonical interoperability file: {$group['source']}");
    }

    foreach ($group['targets'] as $target) {
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException("Unable to synchronize interoperability file: {$target}");
        }
        echo "Synced: {$target}" . PHP_EOL;
    }
}
