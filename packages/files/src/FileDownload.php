<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Files;

/**
 * Transport-neutral download descriptor for a stored file.
 *
 * It exposes the stream and common HTTP headers without emitting a response,
 * so Prefab Files remains usable with Prefab Routes, another framework, CLI,
 * tests, or a custom response layer.
 */
final class FileDownload
{
    /** @param resource $stream */
    public function __construct(
        private $stream,
        private string $filename,
        private int $size,
        private ?string $mime = null,
        private bool $inline = false,
    ) {}

    /** @return resource */
    public function stream()
    {
        return $this->stream;
    }

    public function filename(): string { return $this->filename; }
    public function size(): int { return $this->size; }
    public function mime(): ?string { return $this->mime; }
    public function inline(): bool { return $this->inline; }

    public function headers(): array
    {
        $filename = str_replace(["\r", "\n", '"'], ['', '', '\\"'], $this->filename);

        return [
            'Content-Type' => $this->mime ?? 'application/octet-stream',
            'Content-Length' => (string) $this->size,
            'Content-Disposition' => ($this->inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
        ];
    }
}
