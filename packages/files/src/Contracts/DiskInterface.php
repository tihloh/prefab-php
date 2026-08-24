<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files\Contracts;

use Tihloh\Prefab\Files\FileInfo;

/**
 * Storage backend contract used by FileManager.
 *
 * The contract stays intentionally small enough for local, object and network
 * storage adapters while exposing the operations that application code commonly
 * needs. Optional behavior is advertised through supports().
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

    /** @return string[] */
    public function directories(string $directory = '', bool $recursive = false): array;

    public function directoryExists(string $directory): bool;

    public function makeDirectory(string $directory): bool;

    public function deleteDirectory(string $directory, bool $recursive = false): bool;

    public function info(string $path): FileInfo;

    public function checksum(string $path, string $algorithm = 'sha256'): string;

    public function size(string $path): int;

    public function directorySize(string $directory = ''): int;

    public function path(string $path): string;

    public function url(string $path): ?string;

    /** Whether this backend supports a named optional capability. */
    public function supports(string $capability): bool;
}
