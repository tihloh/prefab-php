<?php

namespace Tihloh\Prefab\Auth\Social;

use RuntimeException;

final class SocialAccountConflictException extends RuntimeException
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public int|string|null $linkedUserId = null,
        string $message = 'Social account is already linked.',
    ) {
        parent::__construct($message);
    }
}
