<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Files\FileManager;

$root = sys_get_temp_dir() . '/prefab-files-' . bin2hex(random_bytes(6));

$files = new FileManager([
    'default' => 'private',
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => $root . '/private',
        ],
        'public' => [
            'driver' => 'local',
            'root' => $root . '/public',
            'url' => '/uploads',
        ],
    ],
]);

try {
    $info = $files->put('docs/test.txt', 'hello');

    if (!$files->exists('docs/test.txt')) {
        throw new RuntimeException('Stored file was not found.');
    }

    if ($files->read('docs/test.txt') !== 'hello') {
        throw new RuntimeException('Stored file contents do not match.');
    }

    if ($info->size() !== 5 || $info->name() !== 'test.txt') {
        throw new RuntimeException('File metadata is incorrect.');
    }

    $files->copy('docs/test.txt', 'docs/copied.txt');
    $files->move('docs/copied.txt', 'archive/moved.txt');

    if (!$files->exists('archive/moved.txt')) {
        throw new RuntimeException('Move failed.');
    }

    $files->put('avatars/user.jpg', 'fake-image-data', 'public');
    if ($files->url('avatars/user.jpg', 'public') !== '/uploads/avatars/user.jpg') {
        throw new RuntimeException('Public URL generation failed.');
    }

    $listed = $files->files('', true);
    if (count($listed) < 2) {
        throw new RuntimeException('Recursive file listing failed.');
    }

    $blocked = false;
    try {
        $files->put('../escape.txt', 'bad');
    } catch (RuntimeException) {
        $blocked = true;
    }

    if (!$blocked) {
        throw new RuntimeException('Path traversal was not blocked.');
    }

    echo "Prefab Files OK\n";
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        @rmdir($root);
    }
}
