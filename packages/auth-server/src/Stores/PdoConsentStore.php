<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Stores;

use PDO;
use Tihloh\Prefab\AuthServer\Contracts\ConsentStoreInterface;

final class PdoConsentStore implements ConsentStoreInterface
{
    public function __construct(private PDO $pdo) {}

    public function hasConsent(
        int|string $userId,
        string $clientId,
        array $scopes
    ): bool {
        $stmt = $this->pdo->prepare(
            'SELECT scopes
             FROM prefab_auth_server_consents
             WHERE user_id = ? AND client_id = ?
             LIMIT 1'
        );
        $stmt->execute([(string) $userId, $clientId]);
        $json = $stmt->fetchColumn();

        if (!is_string($json)) {
            return false;
        }

        $granted = json_decode($json, true);

        if (!is_array($granted)) {
            return false;
        }

        return array_diff(array_values(array_unique($scopes)), $granted) === [];
    }

    public function grant(
        int|string $userId,
        string $clientId,
        array $scopes
    ): void {
        $scopes = array_values(array_unique(array_map('strval', $scopes)));

        $this->revoke($userId, $clientId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO prefab_auth_server_consents
                (user_id, client_id, scopes, created_at, updated_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            (string) $userId,
            $clientId,
            json_encode($scopes, JSON_THROW_ON_ERROR),
        ]);
    }

    public function revoke(int|string $userId, string $clientId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM prefab_auth_server_consents
             WHERE user_id = ? AND client_id = ?'
        );
        $stmt->execute([(string) $userId, $clientId]);
    }
}
