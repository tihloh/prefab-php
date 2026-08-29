<?php

/**
 * Synchronize Prefab's canonical interoperability files into standalone
 * packages.
 *
 * This is a repository-maintenance tool only. Published packages contain their
 * own guarded copies and never depend on tools/ or on a separate Core package.
 *
 * Usage from the repository root:
 *
 *     php tools/sync-prefab-bootstrap.php
 */

$root = dirname(__DIR__);

$groups = [
    [
        'source' => $root . '/tools/prefab-bootstrap.php',
        'targets' => [
            $root . '/packages/database/src/prefab.php',
            $root . '/packages/users/src/prefab.php',
            $root . '/packages/auth/src/prefab.php',
            $root . '/packages/permissions/src/prefab.php',
            $root . '/packages/logs/src/prefab.php',
            $root . '/packages/routes/src/prefab.php',
            $root . '/packages/input/src/prefab.php',
            $root . '/packages/files/src/prefab.php',
            $root . '/packages/messaging/src/prefab.php',
            $root . '/packages/notifications/src/prefab.php',
        ],
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
        throw new RuntimeException(
            "Unable to read canonical interoperability file: {$group['source']}",
        );
    }

    foreach ($group['targets'] as $target) {
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException(
                "Unable to synchronize interoperability file: {$target}",
            );
        }

        echo "Synced: {$target}" . PHP_EOL;
    }
}
