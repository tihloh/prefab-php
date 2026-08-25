<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

final class Message
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $subject = '',
        public readonly string $text = '',
        public readonly ?string $html = null,
        public readonly array $metadata = [],
    ) {}

    public static function make(string $text = '', string $subject = ''): self
    {
        return new self($subject, $text);
    }
}
