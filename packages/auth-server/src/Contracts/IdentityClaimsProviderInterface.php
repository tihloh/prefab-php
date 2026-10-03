<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Contracts;

interface IdentityClaimsProviderInterface
{
    /**
     * Return the stable OIDC subject for a local authenticated user.
     */
    public function subject(int|string $userId): string;

    /**
     * Return claims the user has allowed through the requested scopes.
     *
     * Reserved OIDC claims such as iss, sub, aud, iat and exp are owned by
     * Prefab Auth Server and should not be returned here.
     *
     * @param string[] $scopes
     * @return array<string,mixed>
     */
    public function claims(int|string $userId, array $scopes): array;
}
