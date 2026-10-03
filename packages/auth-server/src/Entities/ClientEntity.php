<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;

final class ClientEntity implements ClientEntityInterface
{
    /**
     * @param string[] $redirectUris
     * @param string[] $allowedScopes
     * @param string[] $grantTypes
     */
    public function __construct(
        private string $identifier,
        private string $name,
        private array $redirectUris,
        private bool $confidential,
        private array $allowedScopes,
        private array $grantTypes,
        private ?string $secretHash = null,
        private bool $active = true,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUri(): string|array
    {
        return count($this->redirectUris) === 1
            ? $this->redirectUris[0]
            : $this->redirectUris;
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }

    /** @return string[] */
    public function allowedScopes(): array
    {
        return $this->allowedScopes;
    }

    public function supportsGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }

    public function secretHash(): ?string
    {
        return $this->secretHash;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
