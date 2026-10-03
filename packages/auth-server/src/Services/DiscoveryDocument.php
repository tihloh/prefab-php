<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Services;

final class DiscoveryDocument
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        private string $issuer,
        private array $scopes = ['openid', 'profile', 'email'],
    ) {
        $this->issuer = rtrim($issuer, '/');
    }

    /** @return array<string,mixed> */
    public function document(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->issuer . '/oauth/authorize',
            'token_endpoint' => $this->issuer . '/oauth/token',
            'userinfo_endpoint' => $this->issuer . '/oauth/userinfo',
            'jwks_uri' => $this->issuer . '/oauth/jwks',
            'response_types_supported' => ['code'],
            'grant_types_supported' => [
                'authorization_code',
                'refresh_token',
            ],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
                'none',
            ],
            'scopes_supported' => array_values(array_unique($this->scopes)),
            'claims_supported' => [
                'sub',
                'name',
                'given_name',
                'family_name',
                'middle_name',
                'preferred_username',
                'picture',
                'email',
                'email_verified',
            ],
            'code_challenge_methods_supported' => ['S256'],
        ];
    }
}
