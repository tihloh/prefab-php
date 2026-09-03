<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Image;

use RuntimeException;

final class ImageInfo
{
    public function __construct(
        private int $width,
        private int $height,
        private string $mime,
        private ?string $type = null,
        private ?int $size = null,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Image is not readable: {$path}");
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException("Unsupported or invalid image: {$path}");
        }

        return new self(
            (int) $info[0],
            (int) $info[1],
            (string) ($info['mime'] ?? 'application/octet-stream'),
            isset($info[2]) ? image_type_to_extension((int) $info[2], false) : null,
            filesize($path) ?: null,
        );
    }

    public function width(): int { return $this->width; }
    public function height(): int { return $this->height; }
    public function mime(): string { return $this->mime; }
    public function type(): ?string { return $this->type; }
    public function size(): ?int { return $this->size; }
    public function ratio(): float { return $this->height === 0 ? 0.0 : $this->width / $this->height; }

    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'ratio' => $this->ratio(),
            'mime' => $this->mime,
            'type' => $this->type,
            'size' => $this->size,
        ];
    }
}
