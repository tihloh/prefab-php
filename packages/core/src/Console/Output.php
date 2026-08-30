<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Console;

final class Output
{
    public function line(string $text = ''): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    public function info(string $text): void
    {
        $this->line($text);
    }

    public function error(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }
}
