<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Templates;

use Tihloh\Prefab\Messaging\Message;

final class Template
{
    public function __construct(
        private readonly string $subject = '',
        private readonly string $text = '',
        private readonly ?string $html = null,
    ) {}

    /** @param array<string, scalar|null> $data */
    public function render(array $data = []): Message
    {
        return new Message(
            $this->replace($this->subject, $data),
            $this->replace($this->text, $data),
            $this->html === null ? null : $this->replace($this->html, $data),
        );
    }

    /** @param array<string, scalar|null> $data */
    private function replace(string $value, array $data): string
    {
        $replace = [];
        foreach ($data as $key => $item) {
            $replace['{{' . $key . '}}'] = $item === null ? '' : (string) $item;
        }
        return strtr($value, $replace);
    }
}
