<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files\Contracts;

use Tihloh\Prefab\Files\FileInfo;

/**
 * Minimal storage-disk contract.
 *
 * Implementations may target local disk, network storage, object storage, or
 * another backend while keeping FileManager independent of that backend.
 */
interface DiskInterface
{
    public function put(string $path, string $contents): FileInfo;

    /** @param resource $stream */
    public function putStream(string $path, $stream): FileInfo;

    public function read(string $path): string;

    /** @return resource */
    public function readStream(string $path);

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    public function copy(string $from, string $to): FileInfo;

    public function move(string $from, string $to): FileInfo;

    /** @return FileInfo[] */
    public function files(string $directory = '', bool $recursive = false): array;

    public function makeDirectory(string $directory): bool;

    public function deleteDirectory(string $directory, bool $recursive = false): bool;

    public function info(string $path): FileInfo;

    public function path(string $path): string;

    public function url(string $path): ?string;
}
