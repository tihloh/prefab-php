<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Input;

/**
 * Normalized PHP upload value.
 *
 * This object represents a temporary incoming upload only. Prefab Input validates
 * uploads but deliberately does not decide where permanent files are stored.
 */
final class UploadedFile
{
    public function __construct(
        private string $originalName,
        private string $tmpPath,
        private int $error,
        private int $size,
        private ?string $clientType = null,
    ) {}

    public function name(): string { return $this->originalName; }
    public function tmpPath(): string { return $this->tmpPath; }
    public function error(): int { return $this->error; }
    public function size(): int { return $this->size; }
    public function clientType(): ?string { return $this->clientType; }

    /** MIME detected from the temporary file when possible; never trusts client MIME first. */
    public function mime(): ?string
    {
        if (!$this->isValid() || !is_file($this->tmpPath)) {
            return null;
        }

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($this->tmpPath);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return null;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->tmpPath !== ''
            && is_file($this->tmpPath);
    }

    public function isImage(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $info = @getimagesize($this->tmpPath);
        return is_array($info) && isset($info[0], $info[1]);
    }

    /** @return array{width:int,height:int}|null */
    public function dimensions(): ?array
    {
        if (!$this->isImage()) {
            return null;
        }

        $info = @getimagesize($this->tmpPath);
        return is_array($info)
            ? ['width' => (int) $info[0], 'height' => (int) $info[1]]
            : null;
    }

    /**
     * Validate an extension using both original extension and detected MIME for
     * common formats. This avoids treating a renamed executable as a PDF/image.
     */
    public function matchesExtension(array $allowed): bool
    {
        $extension = $this->extension();
        $allowed = array_map(static fn ($v) => strtolower(ltrim((string) $v, '.')), $allowed);

        if (!in_array($extension, $allowed, true)) {
            return false;
        }

        $mime = $this->mime();
        if ($mime === null) {
            return false;
        }

        $known = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'doc' => ['application/msword', 'application/CDFV2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/CDFV2'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        ];

        if (!isset($known[$extension])) {
            return false;
        }

        return in_array($mime, $known[$extension], true);
    }

    public function matchesMime(array $allowed): bool
    {
        $mime = $this->mime();
        if ($mime === null) {
            return false;
        }

        foreach ($allowed as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === strtolower($mime)) {
                return true;
            }
            if (str_ends_with($pattern, '/*') && str_starts_with(strtolower($mime), substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
