<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

use InvalidArgumentException;

final class Attachment
{
    private function __construct(
        public readonly ?string $path,
        public readonly ?string $contents,
        public readonly string $name,
        public readonly ?string $mime = null,
    ) {}

    public static function fromPath(string $path, ?string $name = null, ?string $mime = null): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Attachment file [{$path}] does not exist.");
        }
        return new self($path, null, $name ?? basename($path), $mime);
    }

    public static function fromData(string $contents, string $name, ?string $mime = null): self
    {
        return new self(null, $contents, $name, $mime);
    }

    public function data(): string
    {
        if ($this->contents !== null) return $this->contents;
        $data = @file_get_contents((string) $this->path);
        if ($data === false) throw new InvalidArgumentException('Unable to read attachment.');
        return $data;
    }
}
