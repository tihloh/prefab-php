<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files;

use finfo;
use RuntimeException;
use Tihloh\Prefab\Files\Contracts\DiskInterface;

/**
 * Local filesystem disk rooted inside one configured directory.
 *
 * Relative storage paths are normalized and traversal attempts are rejected so
 * callers cannot escape the disk root through ../ segments.
 */
final class LocalDisk implements DiskInterface
{
    private string $root;
    private ?string $baseUrl;

    public function __construct(string $root, ?string $baseUrl = null)
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($root === '') {
            throw new RuntimeException('Local disk root cannot be empty.');
        }

        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException("Unable to create storage root: {$root}");
        }

        $real = realpath($root);
        if ($real === false) {
            throw new RuntimeException("Unable to resolve storage root: {$root}");
        }

        $this->root = rtrim(str_replace('\\', '/', $real), '/');
        $this->baseUrl = $baseUrl === null ? null : rtrim($baseUrl, '/');
    }

    public function put(string $path, string $contents): FileInfo
    {
        $target = $this->absolute($path);
        $this->ensureParent($target);

        $temporary = $target . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write temporary file: {$path}");
        }

        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException("Unable to finalize file write: {$path}");
        }

        return $this->info($path);
    }

    public function putStream(string $path, $stream): FileInfo
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('putStream() requires a readable stream resource.');
        }

        $target = $this->absolute($path);
        $this->ensureParent($target);
        $temporary = $target . '.tmp.' . bin2hex(random_bytes(6));
        $output = fopen($temporary, 'wb');

        if ($output === false) {
            throw new RuntimeException("Unable to open temporary file for writing: {$path}");
        }

        try {
            if (stream_copy_to_stream($stream, $output) === false) {
                throw new RuntimeException("Unable to write stream: {$path}");
            }
        } finally {
            fclose($output);
        }

        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException("Unable to finalize streamed file: {$path}");
        }

        return $this->info($path);
    }

    public function read(string $path): string
    {
        $absolute = $this->absoluteExisting($path);
        $contents = file_get_contents($absolute);
        if ($contents === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }
        return $contents;
    }

    public function readStream(string $path)
    {
        $stream = fopen($this->absoluteExisting($path), 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to open file stream: {$path}");
        }
        return $stream;
    }

    public function exists(string $path): bool
    {
        return is_file($this->absolute($path));
    }

    public function delete(string $path): bool
    {
        $absolute = $this->absolute($path);
        return !is_file($absolute) || unlink($absolute);
    }

    public function copy(string $from, string $to): FileInfo
    {
        $source = $this->absoluteExisting($from);
        $target = $this->absolute($to);
        $this->ensureParent($target);

        if (!copy($source, $target)) {
            throw new RuntimeException("Unable to copy {$from} to {$to}");
        }
        return $this->info($to);
    }

    public function move(string $from, string $to): FileInfo
    {
        $source = $this->absoluteExisting($from);
        $target = $this->absolute($to);
        $this->ensureParent($target);

        if (!rename($source, $target)) {
            throw new RuntimeException("Unable to move {$from} to {$to}");
        }
        return $this->info($to);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $relativeDirectory = $this->normalize($directory, true);
        $base = $relativeDirectory === '' ? $this->root : $this->absolute($relativeDirectory);
        if (!is_dir($base)) {
            return [];
        }

        $items = [];
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
        } else {
            $iterator = new \IteratorIterator(new \DirectoryIterator($base));
        }

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $absolute = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($absolute, strlen($this->root)), '/');
            $items[] = $this->info($relative);
        }

        usort($items, static fn (FileInfo $a, FileInfo $b) => strcmp($a->path(), $b->path()));
        return $items;
    }

    public function makeDirectory(string $directory): bool
    {
        $absolute = $this->absolute($directory);
        return is_dir($absolute) || mkdir($absolute, 0775, true);
    }

    public function deleteDirectory(string $directory, bool $recursive = false): bool
    {
        $absolute = $this->absolute($directory);
        if (!is_dir($absolute)) {
            return true;
        }

        if (!$recursive) {
            return rmdir($absolute);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        return rmdir($absolute);
    }

    public function info(string $path): FileInfo
    {
        $relative = $this->normalize($path);
        $absolute = $this->absoluteExisting($relative);
        $size = filesize($absolute);
        $modified = filemtime($absolute);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: null;

        return new FileInfo(
            $relative,
            $size === false ? 0 : $size,
            $mime,
            $modified === false ? null : $modified,
            $this->url($relative),
        );
    }

    public function path(string $path): string
    {
        return $this->absolute($path);
    }

    public function url(string $path): ?string
    {
        if ($this->baseUrl === null) {
            return null;
        }
        return $this->baseUrl . '/' . str_replace('%2F', '/', rawurlencode($this->normalize($path)));
    }

    private function absolute(string $path): string
    {
        $relative = $this->normalize($path);
        return $this->root . '/' . $relative;
    }

    private function absoluteExisting(string $path): string
    {
        $absolute = $this->absolute($path);
        if (!is_file($absolute)) {
            throw new RuntimeException("File not found: {$path}");
        }
        return $absolute;
    }

    private function normalize(string $path, bool $allowEmpty = false): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || str_contains($segment, "\0")) {
                throw new RuntimeException('Unsafe storage path.');
            }
            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        if ($normalized === '' && !$allowEmpty) {
            throw new RuntimeException('Storage file path cannot be empty.');
        }
        return $normalized;
    }

    private function ensureParent(string $target): void
    {
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException("Unable to create storage directory: {$parent}");
        }
    }
}
