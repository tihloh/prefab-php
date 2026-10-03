<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Repositories;

use PDO;
use PDOException;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Tihloh\Prefab\AuthServer\Entities\AccessTokenEntity;

final class PdoAccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): AccessTokenEntityInterface {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);

        if ($userIdentifier !== null) {
            $token->setUserIdentifier($userIdentifier);
        }

        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }

        return $token;
    }

    public function persistNewAccessToken(
        AccessTokenEntityInterface $accessTokenEntity
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prefab_auth_server_access_tokens
                    (id, user_id, client_id, scopes, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                $accessTokenEntity->getIdentifier(),
                $accessTokenEntity->getUserIdentifier(),
                $accessTokenEntity->getClient()->getIdentifier(),
                json_encode(
                    array_map(
                        static fn ($scope): string => $scope->getIdentifier(),
                        $accessTokenEntity->getScopes()
                    ),
                    JSON_THROW_ON_ERROR
                ),
                $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            if ($this->isDuplicate($e)) {
                throw UniqueTokenIdentifierConstraintViolationException::create();
            }

            throw $e;
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE prefab_auth_server_access_tokens
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$tokenId]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT revoked_at
             FROM prefab_auth_server_access_tokens
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$tokenId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !is_array($row) || $row['revoked_at'] !== null;
    }

    private function isDuplicate(PDOException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}
