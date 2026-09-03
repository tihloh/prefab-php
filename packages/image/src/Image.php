<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Image;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

final class Image
{
    private string $format;
    private int $quality = 82;
    private ?string $sourcePath = null;

    private function __construct(private GdImage $image, string $format)
    {
        $this->format = self::normalizeFormat($format);
    }

    public function __destruct()
    {
        imagedestroy($this->image);
    }

    public static function open(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Image is not readable: {$path}");
        }

        $info = @getimagesize($path);
        if ($info === false || empty($info['mime'])) {
            throw new RuntimeException("Unsupported or invalid image: {$path}");
        }

        $format = self::formatFromMime((string) $info['mime']);
        $instance = new self(self::decodeFile($path, $format), $format);
        $instance->sourcePath = $path;
        return $instance;
    }

    public static function fromString(string $data, ?string $format = null): self
    {
        $image = @imagecreatefromstring($data);
        if (!$image instanceof GdImage) {
            throw new RuntimeException('Invalid or unsupported image data.');
        }

        $detected = $format;
        if ($detected === null && function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($data);
            if (is_array($info) && !empty($info['mime'])) {
                $detected = self::formatFromMime((string) $info['mime']);
            }
        }

        return new self($image, $detected ?? 'png');
    }

    public static function inspect(string $path): ImageInfo
    {
        return ImageInfo::fromFile($path);
    }

    /** Generate a derivative only when the target is missing or older than the source. */
    public static function generate(string $source, string $target, callable $processor): string
    {
        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException("Image is not readable: {$source}");
        }

        $sourceMtime = filemtime($source);
        $targetMtime = is_file($target) ? filemtime($target) : false;
        if ($sourceMtime !== false && $targetMtime !== false && $targetMtime >= $sourceMtime) {
            return $target;
        }

        $image = self::open($source);
        $processed = $processor($image);
        if ($processed instanceof self) {
            $image = $processed;
        }
        $image->save($target);
        return $target;
    }

    public function width(): int { return imagesx($this->image); }
    public function height(): int { return imagesy($this->image); }
    public function ratio(): float { return $this->height() === 0 ? 0.0 : $this->width() / $this->height(); }
    public function formatName(): string { return $this->format; }
    public function mime(): string { return self::mimeForFormat($this->format); }

    public function quality(int $quality): self
    {
        $this->quality = max(0, min(100, $quality));
        return $this;
    }

    public function format(string $format): self
    {
        $format = self::normalizeFormat($format);
        self::assertEncoderAvailable($format);
        $this->format = $format;
        return $this;
    }

    public function resize(int $width, ?int $height = null, bool $upscale = false): self
    {
        if ($width < 1 || ($height !== null && $height < 1)) {
            throw new InvalidArgumentException('Resize dimensions must be positive integers.');
        }

        $sourceWidth = $this->width();
        $sourceHeight = $this->height();
        $scale = $height === null
            ? $width / $sourceWidth
            : min($width / $sourceWidth, $height / $sourceHeight);

        if (!$upscale) {
            $scale = min(1.0, $scale);
        }

        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        if ($targetWidth === $sourceWidth && $targetHeight === $sourceHeight) {
            return $this;
        }

        $canvas = self::canvas($targetWidth, $targetHeight, $this->needsAlpha());
        if (!imagecopyresampled($canvas, $this->image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight)) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to resize image.');
        }

        return $this->replaceImage($canvas);
    }

    public function cover(int $width, int $height, bool $upscale = false): self
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Cover dimensions must be positive integers.');
        }

        $sourceWidth = $this->width();
        $sourceHeight = $this->height();
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        if (!$upscale) {
            $scale = min(1.0, $scale);
        }

        $scaledWidth = max(1, (int) round($sourceWidth * $scale));
        $scaledHeight = max(1, (int) round($sourceHeight * $scale));
        $temp = self::canvas($scaledWidth, $scaledHeight, $this->needsAlpha());
        imagecopyresampled($temp, $this->image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $sourceWidth, $sourceHeight);

        $cropWidth = min($width, $scaledWidth);
        $cropHeight = min($height, $scaledHeight);
        $sourceX = max(0, (int) floor(($scaledWidth - $cropWidth) / 2));
        $sourceY = max(0, (int) floor(($scaledHeight - $cropHeight) / 2));
        $canvas = self::canvas($cropWidth, $cropHeight, $this->needsAlpha());
        imagecopy($canvas, $temp, 0, 0, $sourceX, $sourceY, $cropWidth, $cropHeight);
        imagedestroy($temp);

        return $this->replaceImage($canvas);
    }

    public function thumbnail(int $width, int $height): self
    {
        return $this->cover($width, $height);
    }

    public function crop(int $x, int $y, int $width, int $height): self
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Crop dimensions must be positive integers.');
        }

        $cropped = imagecrop($this->image, [
            'x' => max(0, $x),
            'y' => max(0, $y),
            'width' => $width,
            'height' => $height,
        ]);

        if (!$cropped instanceof GdImage) {
            throw new RuntimeException('Unable to crop image with the requested dimensions.');
        }

        return $this->replaceImage($cropped);
    }

    public function rotate(float $degrees): self
    {
        $background = imagecolorallocatealpha($this->image, 0, 0, 0, 127);
        $rotated = imagerotate($this->image, -$degrees, $background);
        if (!$rotated instanceof GdImage) {
            throw new RuntimeException('Unable to rotate image.');
        }
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        return $this->replaceImage($rotated);
    }

    public function flip(string $direction = 'horizontal'): self
    {
        $mode = match (strtolower($direction)) {
            'horizontal', 'h' => IMG_FLIP_HORIZONTAL,
            'vertical', 'v' => IMG_FLIP_VERTICAL,
            'both' => IMG_FLIP_BOTH,
            default => throw new InvalidArgumentException('Flip direction must be horizontal, vertical, or both.'),
        };

        if (!imageflip($this->image, $mode)) {
            throw new RuntimeException('Unable to flip image.');
        }
        return $this;
    }

    public function autoOrient(): self
    {
        if ($this->sourcePath === null || $this->format !== 'jpeg' || !function_exists('exif_read_data')) {
            return $this;
        }

        $exif = @exif_read_data($this->sourcePath, 'IFD0', true, false);
        $orientation = is_array($exif)
            ? (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1)
            : 1;

        match ($orientation) {
            2 => $this->flip('horizontal'),
            3 => $this->rotate(180),
            4 => $this->flip('vertical'),
            5 => $this->rotate(90)->flip('horizontal'),
            6 => $this->rotate(90),
            7 => $this->rotate(270)->flip('horizontal'),
            8 => $this->rotate(270),
            default => $this,
        };

        return $this;
    }

    public function encode(?string $format = null, ?int $quality = null): string
    {
        $format = $format === null ? $this->format : self::normalizeFormat($format);
        self::assertEncoderAvailable($format);
        $quality = $quality === null ? $this->quality : max(0, min(100, $quality));

        ob_start();
        try {
            $this->writeToOutput($format, $quality, null);
            $data = ob_get_contents();
            if (!is_string($data)) {
                throw new RuntimeException('Unable to encode image.');
            }
            return $data;
        } finally {
            ob_end_clean();
        }
    }

    public function save(string $path, ?string $format = null, ?int $quality = null): string
    {
        $format = $format === null ? self::formatFromPath($path, $this->format) : self::normalizeFormat($format);
        self::assertEncoderAvailable($format);
        $quality = $quality === null ? $this->quality : max(0, min(100, $quality));

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create image directory: {$directory}");
        }

        $temp = tempnam($directory, '.prefab-image-');
        if ($temp === false) {
            throw new RuntimeException("Unable to create temporary image file in {$directory}");
        }

        try {
            $this->writeToOutput($format, $quality, $temp);
            if (!@rename($temp, $path)) {
                throw new RuntimeException("Unable to save image: {$path}");
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }

        return $path;
    }

    /** Emit the processed image body and HTTP image/cache headers. Does not exit. */
    public function display(array $options = []): void
    {
        $format = isset($options['format']) ? self::normalizeFormat((string) $options['format']) : $this->format;
        $quality = isset($options['quality']) ? max(0, min(100, (int) $options['quality'])) : $this->quality;
        self::assertEncoderAvailable($format);

        if (!headers_sent()) {
            header('Content-Type: ' . self::mimeForFormat($format));

            $maxAge = max(0, (int) ($options['max_age'] ?? 0));
            if ($maxAge > 0) {
                $cache = 'public, max-age=' . $maxAge;
                if (!empty($options['immutable'])) {
                    $cache .= ', immutable';
                }
                header('Cache-Control: ' . $cache);
            }

            if (!empty($options['filename'])) {
                $filename = basename((string) $options['filename']);
                header('Content-Disposition: inline; filename="' . addcslashes($filename, "\"\\") . '"');
            }
        }

        $this->writeToOutput($format, $quality, null);
    }

    private function replaceImage(GdImage $replacement): self
    {
        imagedestroy($this->image);
        $this->image = $replacement;
        return $this;
    }

    private function needsAlpha(): bool
    {
        return in_array($this->format, ['png', 'webp', 'avif'], true);
    }

    private static function canvas(int $width, int $height, bool $alpha): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        if ($alpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        }
        return $canvas;
    }

    private static function decodeFile(string $path, string $format): GdImage
    {
        $image = match ($format) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };

        if (!$image instanceof GdImage) {
            throw new RuntimeException("Unable to decode {$format} image: {$path}");
        }

        return $image;
    }

    private function writeToOutput(string $format, int $quality, ?string $path): void
    {
        $ok = match ($format) {
            'jpeg' => imagejpeg($this->image, $path, $quality),
            'png' => imagepng($this->image, $path, self::pngCompression($quality)),
            'gif' => imagegif($this->image, $path),
            'webp' => imagewebp($this->image, $path, $quality),
            'avif' => imageavif($this->image, $path, $quality),
            default => false,
        };

        if (!$ok) {
            throw new RuntimeException("Unable to encode image as {$format}.");
        }
    }

    private static function assertEncoderAvailable(string $format): void
    {
        $available = match ($format) {
            'jpeg' => function_exists('imagejpeg'),
            'png' => function_exists('imagepng'),
            'gif' => function_exists('imagegif'),
            'webp' => function_exists('imagewebp'),
            'avif' => function_exists('imageavif'),
            default => false,
        };

        if (!$available) {
            throw new RuntimeException("GD does not support {$format} encoding on this server.");
        }
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        return match ($format) {
            'jpg', 'jpeg' => 'jpeg',
            'png', 'gif', 'webp', 'avif' => $format,
            default => throw new InvalidArgumentException("Unsupported image format: {$format}"),
        };
    }

    private static function formatFromMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => throw new RuntimeException("Unsupported image MIME type: {$mime}"),
        };
    }

    private static function formatFromPath(string $path, string $fallback): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $extension === '' ? $fallback : self::normalizeFormat($extension);
    }

    private static function mimeForFormat(string $format): string
    {
        return match ($format) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }

    private static function pngCompression(int $quality): int
    {
        return max(0, min(9, (int) round((100 - $quality) * 9 / 100)));
    }
}
