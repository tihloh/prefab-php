<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

final class Message
{
    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<Attachment> $attachments
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $subject = '',
        public readonly string $text = '',
        public readonly ?string $html = null,
        public readonly array $metadata = [],
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly ?string $replyTo = null,
        public readonly array $attachments = [],
    ) {}

    public static function make(string $text = '', string $subject = ''): self
    {
        return new self($subject, $text);
    }

    public function withAttachment(Attachment $attachment): self
    {
        return new self($this->subject, $this->text, $this->html, $this->metadata, $this->cc, $this->bcc, $this->replyTo, [...$this->attachments, $attachment]);
    }
}
