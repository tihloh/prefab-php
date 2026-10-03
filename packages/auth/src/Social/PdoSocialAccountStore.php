<?php

namespace Tihloh\Prefab\Auth\Social;

use PDO;
use PDOException;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;

final class PdoSocialAccountStore implements SocialAccountStoreInterface
{
    public function __construct(private PDO $pdo) {}

    public function findUserId(
        string $provider,
        string $providerUserId,
    ): int|string|null {
        $stmt = $this->pdo->prepare(
            'SELECT user_id
             FROM prefab_auth_social_accounts
             WHERE provider = ? AND provider_user_id = ?
             LIMIT 1',
        );
        $stmt->execute([
            strtolower(trim($provider)),
            $providerUserId,
        ]);

        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public function link(int|string $userId, SocialIdentity $identity): void
    {
        $provider = strtolower(trim($identity->provider));
        $providerUserId = trim($identity->providerUserId);

        $linkedUserId = $this->findUserId($provider, $providerUserId);

        if (
            $linkedUserId !== null
            && (string) $linkedUserId !== (string) $userId
        ) {
            throw new SocialAccountConflictException(
                $provider,
                $providerUserId,
                $linkedUserId,
                'This external account is already linked to another user.',
            );
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, provider_user_id
             FROM prefab_auth_social_accounts
             WHERE user_id = ? AND provider = ?
             LIMIT 1',
        );
        $stmt->execute([$userId, $provider]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            is_array($current)
            && (string) $current['provider_user_id'] !== $providerUserId
        ) {
            throw new SocialAccountConflictException(
                $provider,
                $providerUserId,
                $userId,
                'This user already has another account linked for this provider.',
            );
        }

        $values = [
            $identity->email,
            $identity->emailVerified === null
                ? null
                : ($identity->emailVerified ? 1 : 0),
            $identity->username,
            $identity->name,
            $identity->avatar,
        ];

        if (is_array($current)) {
            $stmt = $this->pdo->prepare(
                'UPDATE prefab_auth_social_accounts
                 SET email = ?,
                     email_verified = ?,
                     username = ?,
                     display_name = ?,
                     avatar_url = ?,
                     last_login_at = NOW(),
                     updated_at = NOW()
                 WHERE id = ?',
            );

            $stmt->execute([...$values, $current['id']]);

            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prefab_auth_social_accounts
                    (
                        user_id,
                        provider,
                        provider_user_id,
                        email,
                        email_verified,
                        username,
                        display_name,
                        avatar_url,
                        last_login_at,
                        created_at,
                        updated_at
                    )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
            );

            $stmt->execute([
                $userId,
                $provider,
                $providerUserId,
                ...$values,
            ]);
        } catch (PDOException $e) {
            $linkedUserId = $this->findUserId(
                $provider,
                $providerUserId,
            );

            if (
                $linkedUserId !== null
                && (string) $linkedUserId !== (string) $userId
            ) {
                throw new SocialAccountConflictException(
                    $provider,
                    $providerUserId,
                    $linkedUserId,
                    'This external account is already linked to another user.',
                );
            }

            throw $e;
        }
    }

    public function unlink(int|string $userId, string $provider): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM prefab_auth_social_accounts
             WHERE user_id = ? AND provider = ?',
        );
        $stmt->execute([
            $userId,
            strtolower(trim($provider)),
        ]);
    }

    public function accountsForUser(int|string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                provider,
                provider_user_id,
                email,
                email_verified,
                username,
                display_name,
                avatar_url,
                last_login_at,
                created_at,
                updated_at
             FROM prefab_auth_social_accounts
             WHERE user_id = ?
             ORDER BY provider',
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
