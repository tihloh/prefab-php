<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Repositories;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Tihloh\Prefab\AuthServer\Entities\ClientEntity;
use Tihloh\Prefab\AuthServer\Entities\ScopeEntity;

final class ScopeRepository implements ScopeRepositoryInterface
{
    /** @var array<string,string> */
    private array $definitions;

    /** @param array<string,string> $definitions */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions + [
            'openid' => 'Authenticate your identity',
            'profile' => 'Access your basic profile',
            'email' => 'Access your email address',
        ];
    }

    public function getScopeEntityByIdentifier(
        string $identifier
    ): ?ScopeEntityInterface {
        return array_key_exists($identifier, $this->definitions)
            ? new ScopeEntity($identifier)
            : null;
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        if (!$clientEntity instanceof ClientEntity) {
            return [];
        }

        $allowed = $clientEntity->allowedScopes();

        return array_values(array_filter(
            $scopes,
            static fn (ScopeEntityInterface $scope): bool =>
                in_array($scope->getIdentifier(), $allowed, true)
        ));
    }

    /** @return array<string,string> */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
