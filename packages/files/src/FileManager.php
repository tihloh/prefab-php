<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files;

use DateTimeInterface;
use RuntimeException;
use Tihloh\Prefab\Files\Contracts\DiskInterface;

/**
 * Named-disk storage manager.
 *
 * Small applications may configure one root and call put()/read(). Larger
 * applications can opt into named disks, collision policies, hooks, checksums,
 * usage reporting and signed temporary URLs without changing the basic API.
 */
final class FileManager
{
    /** @var array<string, DiskInterface> */
    private array $disks = [];
    /** @var array<string, callable[]> */
    private array $listeners = [];
    private string $default;
    private ?string $temporaryUrlBase = null;
    private ?string $signingKey = null;

    public function __construct(array $config = [])
    {
        $this->default = (string) ($config['default'] ?? 'local');
        $this->temporaryUrlBase = isset($config['temporary_url'])
            ? rtrim((string) $config['temporary_url'], '?')
            : null;
        $this->signingKey = isset($config['signing_key'])
            ? (string) $config['signing_key']
            : null;

        foreach ($config['disks'] ?? [] as $name => $definition) {
            $this->add((string) $name, $this->makeDisk($definition));
        }

        if ($this->disks === [] && isset($config['root'])) {
            $this->add('local', new LocalDisk(
                (string) $config['root'],
                isset($config['url']) ? (string) $config['url'] : null,
            ));
            $this->default = 'local';
        }
    }

    public function add(string $name, DiskInterface $disk): self
    {
        $this->disks[$name] = $disk;
        return $this;
    }

    public function hasDisk(string $name): bool { return isset($this->disks[$name]); }
    /** @return string[] */
    public function names(): array { return array_keys($this->disks); }

    public function useDefault(string $name): self
    {
        if (!$this->hasDisk($name)) {
            throw new RuntimeException("Storage disk not found: {$name}");
        }
        $this->default = $name;
        return $this;
    }

    public function defaultName(): string { return $this->default; }

    public function disk(?string $name = null): DiskInterface
    {
        $name ??= $this->default;
        if (!isset($this->disks[$name])) {
            throw new RuntimeException("Storage disk not found: {$name}");
        }
        return $this->disks[$name];
    }

    /** Register a lightweight lifecycle listener (`stored`, `deleted`, `copied`, `moved`). */
    public function on(string $event, callable $listener): self
    {
        $this->listeners[strtolower($event)][] = $listener;
        return $this;
    }

    /**
     * Store string content.
     *
     * The third argument stays backward compatible with the original API: pass
     * a disk name string, or pass an options array such as ['collision'=>'rename'].
     */
    public function put(
        string $path,
        string $contents,
        string|array|null $diskOrOptions = null,
        array $options = [],
    ): FileInfo {
        [$disk, $options] = $this->resolveDiskOptions($diskOrOptions, $options);
        $storage = $this->disk($disk);
        $target = $this->resolveCollision($storage, $path, $options['collision'] ?? 'overwrite');

        if ($target === null) {
            return $storage->info($path);
        }

        $info = $storage->put($target, $contents);
        $this->emit('stored', $info, $disk ?? $this->default, ['source' => 'contents']);
        return $info;
    }

    /** @param resource $stream */
    public function putStream(
        string $path,
        $stream,
        string|array|null $diskOrOptions = null,
        array $options = [],
    ): FileInfo {
        [$disk, $options] = $this->resolveDiskOptions($diskOrOptions, $options);
        $storage = $this->disk($disk);
        $target = $this->resolveCollision($storage, $path, $options['collision'] ?? 'overwrite');

        if ($target === null) {
            return $storage->info($path);
        }

        $info = $storage->putStream($target, $stream);
        $this->emit('stored', $info, $disk ?? $this->default, ['source' => 'stream']);
        return $info;
    }

    public function putFile(
        string $sourcePath,
        string $targetPath,
        string|array|null $diskOrOptions = null,
        array $options = [],
    ): FileInfo {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException("Source file is not readable: {$sourcePath}");
        }

        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to open source file: {$sourcePath}");
        }

        try {
            return $this->putStream($targetPath, $stream, $diskOrOptions, $options);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Store an upload-like object without depending on prefab-input.
     * The object must expose tmpPath() and may expose name()/extension().
     */
    public function storeUploaded(
        object $upload,
        string $directory = '',
        ?string $name = null,
        string|array|null $diskOrOptions = null,
        array $options = [],
    ): FileInfo {
        if (!method_exists($upload, 'tmpPath')) {
            throw new RuntimeException('Uploaded object must provide tmpPath().');
        }

        $source = (string) $upload->tmpPath();
        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException('Uploaded temporary file is not readable.');
        }

        if ($name === null) {
            $original = method_exists($upload, 'name') ? (string) $upload->name() : 'upload';
            $extension = method_exists($upload, 'extension')
                ? (string) $upload->extension()
                : pathinfo($original, PATHINFO_EXTENSION);
            $name = $this->uniqueName($extension);
        }

        $path = trim($directory, '/');
        $path = ($path === '' ? '' : $path . '/') . $name;
        return $this->putFile($source, $path, $diskOrOptions, $options);
    }

    public function read(string $path, ?string $disk = null): string { return $this->disk($disk)->read($path); }
    /** @return resource */
    public function readStream(string $path, ?string $disk = null) { return $this->disk($disk)->readStream($path); }
    public function exists(string $path, ?string $disk = null): bool { return $this->disk($disk)->exists($path); }

    public function delete(string $path, ?string $disk = null): bool
    {
        $storage = $this->disk($disk);
        $info = $storage->exists($path) ? $storage->info($path) : null;
        $deleted = $storage->delete($path);
        if ($deleted && $info !== null) {
            $this->emit('deleted', $info, $disk ?? $this->default);
        }
        return $deleted;
    }

    public function copy(
        string $from,
        string $to,
        string|array|null $diskOrOptions = null,
        array $options = [],
    ): FileInfo {
        [$disk, $options] = $this->resolveDiskOptions($diskOrOptions, $options);
        $storage = $this->disk($disk);
        $target = $this->resolveCollision($storage, $to, $options['collision'] ?? 'overwrite');
        if ($target === null) {
            return $storage->info($to);
        }
        $info = $storage->copy($from, $target);
        $this->emit('copied', $info, $disk ?? $this->default, ['from' => $from]);
        return $info;
    }

    public function move(
        string $from,
        string $to,
        string|array|null $diskOrOptions = null,
        array $options = [],
    ): FileInfo {
        [$disk, $options] = $this->resolveDiskOptions($diskOrOptions, $options);
        $storage = $this->disk($disk);
        $target = $this->resolveCollision($storage, $to, $options['collision'] ?? 'overwrite');
        if ($target === null) {
            return $storage->info($to);
        }
        $info = $storage->move($from, $target);
        $this->emit('moved', $info, $disk ?? $this->default, ['from' => $from]);
        return $info;
    }

    public function info(string $path, ?string $disk = null): FileInfo { return $this->disk($disk)->info($path); }
    public function checksum(string $path, string $algorithm = 'sha256', ?string $disk = null): string { return $this->disk($disk)->checksum($path, $algorithm); }
    public function size(string $path, ?string $disk = null): int { return $this->disk($disk)->size($path); }
    public function directorySize(string $directory = '', ?string $disk = null): int { return $this->disk($disk)->directorySize($directory); }
    public function usage(?string $disk = null): int { return $this->disk($disk)->directorySize(''); }
    public function path(string $path, ?string $disk = null): string { return $this->disk($disk)->path($path); }
    public function url(string $path, ?string $disk = null): ?string { return $this->disk($disk)->url($path); }
    public function supports(string $capability, ?string $disk = null): bool { return $this->disk($disk)->supports($capability); }

    public function files(string $directory = '', bool $recursive = false, ?string $disk = null): array
    {
        return $this->disk($disk)->files($directory, $recursive);
    }

    public function directories(string $directory = '', bool $recursive = false, ?string $disk = null): array
    {
        return $this->disk($disk)->directories($directory, $recursive);
    }

    public function directoryExists(string $directory, ?string $disk = null): bool
    {
        return $this->disk($disk)->directoryExists($directory);
    }

    public function makeDirectory(string $directory, ?string $disk = null): bool
    {
        return $this->disk($disk)->makeDirectory($directory);
    }

    public function deleteDirectory(string $directory, bool $recursive = false, ?string $disk = null): bool
    {
        return $this->disk($disk)->deleteDirectory($directory, $recursive);
    }

    /**
     * Build a signed application URL for a private file.
     *
     * Files only signs and verifies the URL; the host route/controller remains
     * responsible for authorization and streaming the file.
     */
    public function temporaryUrl(
        string $path,
        int|DateTimeInterface $expires = 600,
        ?string $disk = null,
    ): string {
        if ($this->temporaryUrlBase === null || $this->signingKey === null || $this->signingKey === '') {
            throw new RuntimeException('Temporary URLs require temporary_url and signing_key configuration.');
        }
        $disk ??= $this->default;
        $expiresAt = $expires instanceof DateTimeInterface ? $expires->getTimestamp() : time() + max(1, $expires);
        $payload = $disk . "\n" . $path . "\n" . $expiresAt;
        $signature = hash_hmac('sha256', $payload, $this->signingKey);

        return $this->temporaryUrlBase . '?' . http_build_query([
            'disk' => $disk,
            'path' => $path,
            'expires' => $expiresAt,
            'signature' => $signature,
        ]);
    }

    public function verifyTemporaryUrl(
        string $path,
        int $expires,
        string $signature,
        ?string $disk = null,
    ): bool {
        if ($this->signingKey === null || $this->signingKey === '' || $expires < time()) {
            return false;
        }
        $disk ??= $this->default;
        $expected = hash_hmac('sha256', $disk . "\n" . $path . "\n" . $expires, $this->signingKey);
        return hash_equals($expected, $signature) && $this->exists($path, $disk);
    }

    public function uniqueName(?string $extension = null): string
    {
        $name = bin2hex(random_bytes(16));
        $extension = strtolower(trim((string) $extension, ". \t\n\r\0\x0B"));
        return $extension === '' ? $name : $name . '.' . $extension;
    }

    private function resolveCollision(DiskInterface $disk, string $path, string $strategy): ?string
    {
        if (!$disk->exists($path)) {
            return $path;
        }

        return match (strtolower($strategy)) {
            'overwrite' => $path,
            'skip' => null,
            'error' => throw new RuntimeException("Storage path already exists: {$path}"),
            'rename' => $this->nextAvailablePath($disk, $path),
            default => throw new RuntimeException("Unsupported collision strategy: {$strategy}"),
        };
    }

    private function nextAvailablePath(DiskInterface $disk, string $path): string
    {
        $directory = str_replace('\\', '/', dirname($path));
        $directory = $directory === '.' ? '' : trim($directory, '/');
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        for ($i = 1; $i < 100000; $i++) {
            $name = $filename . '-' . $i . ($extension === '' ? '' : '.' . $extension);
            $candidate = ($directory === '' ? '' : $directory . '/') . $name;
            if (!$disk->exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Unable to find an available filename for: {$path}");
    }

    private function resolveDiskOptions(string|array|null $diskOrOptions, array $options): array
    {
        if (is_array($diskOrOptions)) {
            return [null, array_replace($diskOrOptions, $options)];
        }
        return [$diskOrOptions, $options];
    }

    private function emit(string $event, FileInfo $file, string $disk, array $context = []): void
    {
        foreach ($this->listeners[strtolower($event)] ?? [] as $listener) {
            $listener($file, $disk, $context, $this);
        }
    }

    private function makeDisk(mixed $definition): DiskInterface
    {
        if ($definition instanceof DiskInterface) {
            return $definition;
        }
        if (!is_array($definition)) {
            throw new RuntimeException('Storage disk configuration must be a DiskInterface or array.');
        }

        $driver = strtolower((string) ($definition['driver'] ?? 'local'));
        return match ($driver) {
            'local' => new LocalDisk(
                (string) ($definition['root'] ?? throw new RuntimeException('Local disk requires root.')),
                isset($definition['url']) ? (string) $definition['url'] : null,
            ),
            default => throw new RuntimeException("Unsupported storage driver: {$driver}"),
        };
    }
}
