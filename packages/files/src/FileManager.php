<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files;

use RuntimeException;
use Tihloh\Prefab\Files\Contracts\DiskInterface;

/**
 * Manages named storage disks and provides convenience helpers for common file
 * operations without coupling applications to one storage backend.
 */
final class FileManager
{
    /** @var array<string, DiskInterface> */
    private array $disks = [];
    private string $default;

    public function __construct(array $config = [])
    {
        $this->default = (string) ($config['default'] ?? 'local');

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

    public function hasDisk(string $name): bool
    {
        return isset($this->disks[$name]);
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->disks);
    }

    public function useDefault(string $name): self
    {
        if (!$this->hasDisk($name)) {
            throw new RuntimeException("Storage disk not found: {$name}");
        }
        $this->default = $name;
        return $this;
    }

    public function defaultName(): string
    {
        return $this->default;
    }

    public function disk(?string $name = null): DiskInterface
    {
        $name ??= $this->default;
        if (!isset($this->disks[$name])) {
            throw new RuntimeException("Storage disk not found: {$name}");
        }
        return $this->disks[$name];
    }

    public function put(string $path, string $contents, ?string $disk = null): FileInfo
    {
        return $this->disk($disk)->put($path, $contents);
    }

    /** @param resource $stream */
    public function putStream(string $path, $stream, ?string $disk = null): FileInfo
    {
        return $this->disk($disk)->putStream($path, $stream);
    }

    public function putFile(string $sourcePath, string $targetPath, ?string $disk = null): FileInfo
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException("Source file is not readable: {$sourcePath}");
        }

        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to open source file: {$sourcePath}");
        }

        try {
            return $this->putStream($targetPath, $stream, $disk);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Store an upload-like object without depending on prefab-input.
     *
     * The object must expose tmpPath() and may expose name()/extension(). This is
     * intentionally capability-based so prefab-files remains standalone.
     */
    public function storeUploaded(
        object $upload,
        string $directory = '',
        ?string $name = null,
        ?string $disk = null,
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
            $extension = method_exists($upload, 'extension') ? (string) $upload->extension() : pathinfo($original, PATHINFO_EXTENSION);
            $name = $this->uniqueName($extension);
        }

        $path = trim($directory, '/');
        $path = ($path === '' ? '' : $path . '/') . $name;
        return $this->putFile($source, $path, $disk);
    }

    public function read(string $path, ?string $disk = null): string
    {
        return $this->disk($disk)->read($path);
    }

    public function exists(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->exists($path);
    }

    public function delete(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->delete($path);
    }

    public function copy(string $from, string $to, ?string $disk = null): FileInfo
    {
        return $this->disk($disk)->copy($from, $to);
    }

    public function move(string $from, string $to, ?string $disk = null): FileInfo
    {
        return $this->disk($disk)->move($from, $to);
    }

    public function info(string $path, ?string $disk = null): FileInfo
    {
        return $this->disk($disk)->info($path);
    }

    public function path(string $path, ?string $disk = null): string
    {
        return $this->disk($disk)->path($path);
    }

    public function url(string $path, ?string $disk = null): ?string
    {
        return $this->disk($disk)->url($path);
    }

    public function files(string $directory = '', bool $recursive = false, ?string $disk = null): array
    {
        return $this->disk($disk)->files($directory, $recursive);
    }

    public function makeDirectory(string $directory, ?string $disk = null): bool
    {
        return $this->disk($disk)->makeDirectory($directory);
    }

    public function deleteDirectory(string $directory, bool $recursive = false, ?string $disk = null): bool
    {
        return $this->disk($disk)->deleteDirectory($directory, $recursive);
    }

    public function uniqueName(?string $extension = null): string
    {
        $name = bin2hex(random_bytes(16));
        $extension = strtolower(trim((string) $extension, ". \t\n\r\0\x0B"));
        return $extension === '' ? $name : $name . '.' . $extension;
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
