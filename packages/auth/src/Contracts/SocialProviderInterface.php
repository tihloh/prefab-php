<?php

namespace Tihloh\Prefab\Auth\Contracts;

use Tihloh\Prefab\Auth\Social\SocialIdentity;

interface SocialProviderInterface
{
    public function name(): string;
    public function authorizationUrl(string $state): string;
    public function identityFromCallback(array $query): SocialIdentity;
}
