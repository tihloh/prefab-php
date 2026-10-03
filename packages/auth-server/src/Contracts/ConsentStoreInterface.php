<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Contracts;

interface ConsentStoreInterface
{
    /** @param string[] $scopes */
    public function hasConsent(int|string $userId, string $clientId, array $scopes): bool;

    /** @param string[] $scopes */
    public function grant(int|string $userId, string $clientId, array $scopes): void;

    public function revoke(int|string $userId, string $clientId): void;
}
