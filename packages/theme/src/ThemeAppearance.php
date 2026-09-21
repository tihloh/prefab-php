<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Theme;

final class ThemeAppearance
{
    /** @param array<string, string> $source */
    public function __construct(
        public readonly string $theme,
        public readonly string $mode,
        public readonly string $density,
        public readonly array $source = [],
        public readonly ?string $accent = null,
    ) {}

    public function toArray(): array
    {
        return [
            'theme' => $this->theme,
            'mode' => $this->mode,
            'density' => $this->density,
            'accent' => $this->accent,
            'source' => $this->source,
        ];
    }
}
