<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Services;

use DateInterval;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use RuntimeException;
use Tihloh\Prefab\AuthServer\Contracts\IdentityClaimsProviderInterface;

final class IdTokenIssuer
{
    private Configuration $jwt;
    private DateInterval $ttl;
    private string $keyId;

    public function __construct(
        private string $issuer,
        private IdentityClaimsProviderInterface $claims,
        string $privateKey,
        string $publicKey,
        ?string $privateKeyPassphrase = null,
        string $ttl = 'PT10M',
        ?string $keyId = null,
    ) {
        $privatePem = JwkSetService::contents($privateKey);
        $publicPem = JwkSetService::contents($publicKey);

        $this->jwt = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($privatePem, $privateKeyPassphrase ?? ''),
            InMemory::plainText($publicPem),
        );
        $this->ttl = new DateInterval($ttl);
        $this->keyId = $keyId ?? JwkSetService::keyIdFor($publicPem);
        $this->issuer = rtrim($issuer, '/');
    }

    public function issue(
        AccessTokenEntityInterface $accessToken
    ): ?string {
        $scopes = array_map(
            static fn ($scope): string => $scope->getIdentifier(),
            $accessToken->getScopes()
        );

        if (!in_array('openid', $scopes, true)) {
            return null;
        }

        $localUserId = $accessToken->getUserIdentifier();

        if ($localUserId === null || $localUserId === '') {
            return null;
        }

        $subject = trim($this->claims->subject($localUserId));

        if ($subject === '') {
            throw new RuntimeException('OIDC subject cannot be empty.');
        }

        $now = new DateTimeImmutable();
        $builder = $this->jwt->builder()
            ->withHeader('kid', $this->keyId)
            ->issuedBy($this->issuer)
            ->permittedFor($accessToken->getClient()->getIdentifier())
            ->relatedTo($subject)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->expiresAt($now->add($this->ttl));

        $reserved = [
            'iss', 'sub', 'aud', 'exp', 'iat', 'nbf', 'jti', 'nonce',
        ];

        foreach ($this->claims->claims($localUserId, $scopes) as $name => $value) {
            if (!in_array((string) $name, $reserved, true)) {
                $builder = $builder->withClaim((string) $name, $value);
            }
        }

        return $builder
            ->getToken(
                $this->jwt->signer(),
                $this->jwt->signingKey()
            )
            ->toString();
    }
}
