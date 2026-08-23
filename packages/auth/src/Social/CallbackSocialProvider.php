<?php

namespace Tihloh\Prefab\Auth\Social;

use Closure;
use Tihloh\Prefab\Auth\Contracts\SocialProviderInterface;

final class CallbackSocialProvider implements SocialProviderInterface
{
    public function __construct(
        private string $providerName,
        private Closure $authorizationUrlResolver,
        private Closure $callbackResolver,
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function authorizationUrl(string $state): string
    {
        return ($this->authorizationUrlResolver)($state);
    }

    public function identityFromCallback(array $query): SocialIdentity
    {
        $identity = ($this->callbackResolver)($query);
        if (!$identity instanceof SocialIdentity) {
            throw new \RuntimeException('Social callback resolver must return SocialIdentity.');
        }
        return $identity;
    }
}
