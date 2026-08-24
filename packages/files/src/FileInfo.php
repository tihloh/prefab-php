<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files;

/** Immutable metadata snapshot for a stored file. */
final class FileInfo
{
    public function __construct(
        private string $path,
        private int $size,
        private ?string $mime = null,
        private ?int $modifiedAt = null,
        private ?string $url = null,
        private ?string $checksum = null,
    ) {}

    public function path(): string { return $this->path; }
    public function name(): string { return basename($this->path); }
    public function filename(): string { return pathinfo($this->path, PATHINFO_FILENAME); }
    public function directory(): string { return str_replace('\\', '/', dirname($this->path)) === '.' ? '' : str_replace('\\', '/', dirname($this->path)); }
    public function extension(): string { return strtolower(pathinfo($this->path, PATHINFO_EXTENSION)); }
    public function size(): int { return $this->size; }
    public function mime(): ?string { return $this->mime; }
    public function modifiedAt(): ?int { return $this->modifiedAt; }
    public function url(): ?string { return $this->url; }
    public function checksum(): ?string { return $this->checksum; }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name(),
            'filename' => $this->filename(),
            'directory' => $this->directory(),
            'extension' => $this->extension(),
            'size' => $this->size,
            'mime' => $this->mime,
            'modified_at' => $this->modifiedAt,
            'url' => $this->url,
            'checksum' => $this->checksum,
        ];
    }
}
