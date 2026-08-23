<?php

/**
 * Synchronize the canonical Prefab interoperability bootstrap into every
 * standalone package.
 *
 * This is a repository-maintenance tool only. Published Prefab packages do not
 * depend on this script or on another package at runtime.
 *
 * Usage from the repository root:
 *
 *     php tools/sync-prefab-bootstrap.php
 */

$root = dirname(__DIR__);
$source = $root . '/tools/prefab-bootstrap.php';

$targets = [
    $root . '/packages/database/src/prefab.php',
    $root . '/packages/users/src/prefab.php',
    $root . '/packages/auth/src/prefab.php',
    $root . '/packages/permissions/src/prefab.php',
    $root . '/packages/logs/src/prefab.php',
];

$bootstrap = file_get_contents($source);

if ($bootstrap === false) {
    throw new RuntimeException("Unable to read canonical bootstrap: {$source}");
}

foreach ($targets as $target) {
    if (file_put_contents($target, $bootstrap) === false) {
        throw new RuntimeException("Unable to synchronize bootstrap: {$target}");
    }

    echo "Synced: {$target}" . PHP_EOL;
}
