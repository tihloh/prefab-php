<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Console;

final class Input
{
    private array $arguments = [];
    private array $options = [];

    public function __construct(array $tokens = [])
    {
        foreach ($tokens as $token) {
            if (!is_string($token)) {
                continue;
            }

            if (str_starts_with($token, '--')) {
                $option = substr($token, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    $this->options[$name] = $value;
                } else {
                    $this->options[$option] = true;
                }
                continue;
            }

            $this->arguments[] = $token;
        }
    }

    public function argument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function flag(string $name): bool
    {
        return ($this->options[$name] ?? false) === true;
    }

    public function options(): array
    {
        return $this->options;
    }
}
