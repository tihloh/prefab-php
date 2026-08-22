<?php

namespace Tihloh\Prefab\Auth\Contracts;

use Tihloh\Prefab\Auth\Social\SocialIdentity;

interface SocialAccountStoreInterface
{
    public function findUserId(string $provider, string $providerUserId): int|string|null;
    public function link(int|string $userId, SocialIdentity $identity): void;
    public function unlink(int|string $userId, string $provider): void;
    public function accountsForUser(int|string $userId): array;
}
