<?php

/**
 * Synchronize Prefab's canonical shared infrastructure into Prefab Core.
 *
 * Feature packages consume this infrastructure through tihloh/prefab-core
 * and must not carry synchronized copies of Core files.
 *
 * Usage from the repository root:
 *
 *     php tools/sync-prefab-bootstrap.php
 */

$root = dirname(__DIR__);
$core = static fn (string $file): string => $root . '/packages/core/src/' . $file;

$groups = [
    [
        'source' => $root . '/tools/prefab-bootstrap.php',
        'target' => $core('prefab.php'),
    ],
    [
        'source' => $root . '/tools/prefab-diagnostics.php',
        'target' => $core('prefab-diagnostics.php'),
    ],
    [
        'source' => $root . '/tools/prefab-debug-ui.php',
        'target' => $core('prefab-debug-ui.php'),
    ],
    [
        'source' => $root . '/tools/database-contracts.php',
        'target' => $core('database.php'),
    ],
];

foreach ($groups as $group) {
    $content = file_get_contents($group['source']);
    if ($content === false) {
        throw new RuntimeException("Unable to read canonical Core file: {$group['source']}");
    }

    if (file_put_contents($group['target'], $content) === false) {
        throw new RuntimeException("Unable to synchronize Core file: {$group['target']}");
    }

    echo "Synced: {$group['target']}" . PHP_EOL;
}
