<?php

namespace Tihloh\Prefab\Auth\Social;

use PDO;
use PDOException;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;

final class PdoSocialAccountStore implements SocialAccountStoreInterface
{
    public function __construct(private PDO $pdo) {}

    public function findUserId(string $provider, string $providerUserId): int|string|null
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM prefab_auth_social_accounts WHERE provider = ? AND provider_user_id = ? LIMIT 1');
        $stmt->execute([$provider, $providerUserId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    public function link(int|string $userId, SocialIdentity $identity): void
    {
        $linkedUserId = $this->findUserId(
            $identity->provider,
            $identity->providerUserId,
        );

        if (
            $linkedUserId !== null
            && (string) $linkedUserId !== (string) $userId
        ) {
            throw new SocialAccountConflictException(
                $identity->provider,
                $identity->providerUserId,
                $linkedUserId,
                'This external account is already linked to another user.',
            );
        }

        $stmt = $this->pdo->prepare(
            'SELECT provider_user_id
             FROM prefab_auth_social_accounts
             WHERE user_id = ? AND provider = ?
             LIMIT 1',
        );
        $stmt->execute([$userId, $identity->provider]);
        $currentProviderUserId = $stmt->fetchColumn();

        if (
            $currentProviderUserId !== false
            && (string) $currentProviderUserId !== $identity->providerUserId
        ) {
            throw new SocialAccountConflictException(
                $identity->provider,
                $identity->providerUserId,
                $userId,
                'This user already has another account linked for this provider.',
            );
        }

        if ($currentProviderUserId !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE prefab_auth_social_accounts
                 SET email = ?, updated_at = NOW()
                 WHERE user_id = ? AND provider = ?',
            );
            $stmt->execute([
                $identity->email,
                $userId,
                $identity->provider,
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prefab_auth_social_accounts
                    (user_id, provider, provider_user_id, email, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())',
            );
            $stmt->execute([
                $userId,
                $identity->provider,
                $identity->providerUserId,
                $identity->email,
            ]);
        } catch (PDOException $e) {
            $linkedUserId = $this->findUserId(
                $identity->provider,
                $identity->providerUserId,
            );

            if (
                $linkedUserId !== null
                && (string) $linkedUserId !== (string) $userId
            ) {
                throw new SocialAccountConflictException(
                    $identity->provider,
                    $identity->providerUserId,
                    $linkedUserId,
                    'This external account is already linked to another user.',
                );
            }

            throw $e;
        }
    }

    public function unlink(int|string $userId, string $provider): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM prefab_auth_social_accounts WHERE user_id = ? AND provider = ?');
        $stmt->execute([$userId, $provider]);
    }

    public function accountsForUser(int|string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT provider, provider_user_id, email, created_at, updated_at FROM prefab_auth_social_accounts WHERE user_id = ? ORDER BY provider');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
