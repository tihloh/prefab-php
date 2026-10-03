<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Repositories;

use PDO;
use PDOException;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Tihloh\Prefab\AuthServer\Entities\RefreshTokenEntity;

final class PdoRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(
        RefreshTokenEntityInterface $refreshTokenEntity
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prefab_auth_server_refresh_tokens
                    (id, access_token_id, expires_at, created_at)
                 VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                $refreshTokenEntity->getIdentifier(),
                $refreshTokenEntity->getAccessToken()->getIdentifier(),
                $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw UniqueTokenIdentifierConstraintViolationException::create();
            }

            throw $e;
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE prefab_auth_server_refresh_tokens
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$tokenId]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT revoked_at, expires_at
             FROM prefab_auth_server_refresh_tokens
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$tokenId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || $row['revoked_at'] !== null) {
            return true;
        }

        return strtotime((string) $row['expires_at']) <= time();
    }
}
