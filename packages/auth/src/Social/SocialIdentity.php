<?php

namespace Tihloh\Prefab\Auth\Social;

final readonly class SocialIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $avatar = null,
        public array $raw = [],
    ) {}
}
