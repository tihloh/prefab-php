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
    ) {}

    public function path(): string { return $this->path; }
    public function name(): string { return basename($this->path); }
    public function extension(): string { return strtolower(pathinfo($this->path, PATHINFO_EXTENSION)); }
    public function size(): int { return $this->size; }
    public function mime(): ?string { return $this->mime; }
    public function modifiedAt(): ?int { return $this->modifiedAt; }
    public function url(): ?string { return $this->url; }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name(),
            'extension' => $this->extension(),
            'size' => $this->size,
            'mime' => $this->mime,
            'modified_at' => $this->modifiedAt,
            'url' => $this->url,
        ];
    }
}
