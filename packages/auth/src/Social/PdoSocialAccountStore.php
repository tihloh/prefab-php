<?php

namespace Tihloh\Prefab\Auth\Social;

use PDO;
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
        $stmt = $this->pdo->prepare('INSERT INTO prefab_auth_social_accounts (user_id, provider, provider_user_id, email, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), email = VALUES(email), updated_at = NOW()');
        $stmt->execute([$userId, $identity->provider, $identity->providerUserId, $identity->email]);
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
