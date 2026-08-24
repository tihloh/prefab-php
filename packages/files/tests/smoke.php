<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Files\FileManager;

$root = sys_get_temp_dir() . '/prefab-files-' . bin2hex(random_bytes(6));
$events = [];

$files = new FileManager([
    'default' => 'private',
    'temporary_url' => '/files/temp',
    'signing_key' => 'test-signing-key',
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

$files->on('stored', function ($file, $disk) use (&$events) {
    $events[] = ['stored', $file->path(), $disk];
});

try {
    $info = $files->put('docs/test.txt', 'hello');

    if (!$files->exists('docs/test.txt') || $files->read('docs/test.txt') !== 'hello') {
        throw new RuntimeException('Basic storage/read failed.');
    }

    if ($info->size() !== 5 || $info->name() !== 'test.txt' || $files->size('docs/test.txt') !== 5) {
        throw new RuntimeException('File metadata/size is incorrect.');
    }

    if ($files->checksum('docs/test.txt') !== hash('sha256', 'hello')) {
        throw new RuntimeException('Checksum failed.');
    }

    $renamed = $files->put('docs/test.txt', 'second', ['collision' => 'rename']);
    if ($renamed->path() !== 'docs/test-1.txt' || $files->read('docs/test-1.txt') !== 'second') {
        throw new RuntimeException('Rename collision strategy failed.');
    }

    $skipped = $files->put('docs/test.txt', 'ignored', ['collision' => 'skip']);
    if ($skipped->path() !== 'docs/test.txt' || $files->read('docs/test.txt') !== 'hello') {
        throw new RuntimeException('Skip collision strategy failed.');
    }

    $errorCollision = false;
    try {
        $files->put('docs/test.txt', 'bad', ['collision' => 'error']);
    } catch (RuntimeException) {
        $errorCollision = true;
    }
    if (!$errorCollision) {
        throw new RuntimeException('Error collision strategy failed.');
    }

    $files->copy('docs/test.txt', 'docs/copied.txt');
    $files->move('docs/copied.txt', 'archive/moved.txt');
    if (!$files->exists('archive/moved.txt')) {
        throw new RuntimeException('Copy/move failed.');
    }

    $files->makeDirectory('empty/sub');
    if (!$files->directoryExists('empty/sub')) {
        throw new RuntimeException('Directory creation failed.');
    }
    if (!in_array('empty/sub', $files->directories('', true), true)) {
        throw new RuntimeException('Recursive directory listing failed.');
    }

    if ($files->directorySize('docs') < 11 || $files->usage() < 16) {
        throw new RuntimeException('Directory/disk usage calculation failed.');
    }

    $files->put('avatars/user.jpg', 'fake-image-data', 'public');
    if ($files->url('avatars/user.jpg', 'public') !== '/uploads/avatars/user.jpg') {
        throw new RuntimeException('Public URL generation failed.');
    }
    if (!$files->supports('public_url', 'public') || $files->supports('public_url', 'private')) {
        throw new RuntimeException('Driver capability detection failed.');
    }

    $download = $files->download('docs/test.txt', 'friendly.txt');
    if ($download->filename() !== 'friendly.txt' || $download->size() !== 5) {
        throw new RuntimeException('Download descriptor failed.');
    }
    fclose($download->stream());

    $temporary = $files->temporaryUrl('docs/test.txt', 600);
    parse_str((string) parse_url($temporary, PHP_URL_QUERY), $query);
    if (!$files->verifyTemporaryUrl(
        (string) ($query['path'] ?? ''),
        (int) ($query['expires'] ?? 0),
        (string) ($query['signature'] ?? ''),
        (string) ($query['disk'] ?? ''),
    )) {
        throw new RuntimeException('Temporary URL signing/verification failed.');
    }

    if ($events === [] || $events[0][0] !== 'stored') {
        throw new RuntimeException('Lifecycle hooks failed.');
    }

    $listed = $files->files('', true);
    if (count($listed) < 3) {
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
