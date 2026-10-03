<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\DTOs;

final class RegisteredClient
{
    /**
     * The plain client secret is returned only at creation time.
     *
     * @param string[] $redirectUris
     * @param string[] $allowedScopes
     * @param string[] $grantTypes
     */
    public function __construct(
        public string $clientId,
        public ?string $clientSecret,
        public string $name,
        public array $redirectUris,
        public array $allowedScopes,
        public array $grantTypes,
        public bool $confidential,
    ) {}
}
