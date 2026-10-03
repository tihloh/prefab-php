<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Repositories;

use PDO;
use PDOException;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Tihloh\Prefab\AuthServer\Entities\AuthCodeEntity;

final class PdoAuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(
        AuthCodeEntityInterface $authCodeEntity
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prefab_auth_server_auth_codes
                    (id, user_id, client_id, redirect_uri, scopes,
                     expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                $authCodeEntity->getIdentifier(),
                $authCodeEntity->getUserIdentifier(),
                $authCodeEntity->getClient()->getIdentifier(),
                $authCodeEntity->getRedirectUri(),
                json_encode(
                    array_map(
                        static fn ($scope): string => $scope->getIdentifier(),
                        $authCodeEntity->getScopes()
                    ),
                    JSON_THROW_ON_ERROR
                ),
                $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw UniqueTokenIdentifierConstraintViolationException::create();
            }

            throw $e;
        }
    }

    public function revokeAuthCode(string $codeId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE prefab_auth_server_auth_codes
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$codeId]);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT revoked_at, expires_at
             FROM prefab_auth_server_auth_codes
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$codeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || $row['revoked_at'] !== null) {
            return true;
        }

        return strtotime((string) $row['expires_at']) <= time();
    }
}
