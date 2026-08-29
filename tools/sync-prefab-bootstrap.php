<?php

/**
 * Synchronize Prefab's canonical shared interoperability and diagnostics files.
 *
 * Core is the long-term owner of these files. During migration we also keep
 * the legacy copies in feature packages synchronized so current releases keep
 * working until every package requires tihloh/prefab-core.
 *
 * Usage from the repository root:
 *
 *     php tools/sync-prefab-bootstrap.php
 */

$root = dirname(__DIR__);
$legacyPackages = ['database', 'users', 'auth', 'permissions', 'logs', 'routes', 'input', 'files', 'messaging', 'notifications'];

$legacyTargets = static function (string $file) use ($root, $legacyPackages): array {
    return array_map(
        static fn (string $package): string => $root . '/packages/' . $package . '/src/' . $file,
        $legacyPackages,
    );
};

$core = static fn (string $file): string => $root . '/packages/core/src/' . $file;

$groups = [
    [
        'source' => $root . '/tools/prefab-bootstrap.php',
        'targets' => [$core('prefab.php'), ...$legacyTargets('prefab.php')],
    ],
    [
        'source' => $root . '/tools/prefab-diagnostics.php',
        'targets' => [$core('prefab-diagnostics.php'), ...$legacyTargets('prefab-diagnostics.php')],
    ],
    [
        'source' => $root . '/tools/prefab-debug-ui.php',
        'targets' => [$core('prefab-debug-ui.php'), ...$legacyTargets('prefab-debug-ui.php')],
    ],
    [
        'source' => $root . '/tools/database-contracts.php',
        'targets' => [
            $core('database.php'),
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
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create interoperability directory: {$directory}");
        }
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException("Unable to synchronize interoperability file: {$target}");
        }
        echo "Synced: {$target}" . PHP_EOL;
    }
}
