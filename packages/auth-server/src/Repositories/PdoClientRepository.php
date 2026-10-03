<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Repositories;

use PDO;
use RuntimeException;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Tihloh\Prefab\AuthServer\DTOs\RegisteredClient;
use Tihloh\Prefab\AuthServer\Entities\ClientEntity;

final class PdoClientRepository implements ClientRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $stmt = $this->pdo->prepare(
            'SELECT client_id, client_secret_hash, name, redirect_uris,
                    allowed_scopes, grant_types, confidential, active
             FROM prefab_auth_server_clients
             WHERE client_id = ?
             LIMIT 1'
        );
        $stmt->execute([$clientIdentifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function validateClient(
        string $clientIdentifier,
        ?string $clientSecret,
        ?string $grantType
    ): bool {
        $client = $this->getClientEntity($clientIdentifier);

        if (!$client instanceof ClientEntity || !$client->isActive()) {
            return false;
        }

        if ($grantType !== null && !$client->supportsGrantType($grantType)) {
            return false;
        }

        if (!$client->isConfidential()) {
            return $clientSecret === null || $clientSecret === '';
        }

        $hash = $client->secretHash();

        return is_string($hash)
            && $hash !== ''
            && is_string($clientSecret)
            && $clientSecret !== ''
            && password_verify($clientSecret, $hash);
    }

    /**
     * Register a new OAuth/OIDC client.
     *
     * @param string[] $redirectUris
     * @param string[] $allowedScopes
     * @param string[] $grantTypes
     */
    public function register(
        string $name,
        array $redirectUris,
        array $allowedScopes = ['openid', 'profile', 'email'],
        bool $confidential = true,
        array $grantTypes = ['authorization_code', 'refresh_token'],
    ): RegisteredClient {
        $redirectUris = $this->cleanList($redirectUris);
        $allowedScopes = $this->cleanList($allowedScopes);
        $grantTypes = $this->cleanList($grantTypes);

        if ($name === '' || $redirectUris === []) {
            throw new RuntimeException(
                'Client name and at least one redirect URI are required.'
            );
        }

        $clientId = 'pf_' . bin2hex(random_bytes(16));
        $secret = $confidential ? bin2hex(random_bytes(32)) : null;
        $hash = $secret !== null
            ? password_hash($secret, PASSWORD_DEFAULT)
            : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO prefab_auth_server_clients
                (client_id, client_secret_hash, name, redirect_uris,
                 allowed_scopes, grant_types, confidential, active,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            $clientId,
            $hash,
            $name,
            json_encode($redirectUris, JSON_THROW_ON_ERROR),
            json_encode($allowedScopes, JSON_THROW_ON_ERROR),
            json_encode($grantTypes, JSON_THROW_ON_ERROR),
            $confidential ? 1 : 0,
        ]);

        return new RegisteredClient(
            $clientId,
            $secret,
            $name,
            $redirectUris,
            $allowedScopes,
            $grantTypes,
            $confidential,
        );
    }

    private function hydrate(array $row): ClientEntity
    {
        return new ClientEntity(
            (string) $row['client_id'],
            (string) $row['name'],
            $this->decodeList((string) $row['redirect_uris']),
            (bool) $row['confidential'],
            $this->decodeList((string) $row['allowed_scopes']),
            $this->decodeList((string) $row['grant_types']),
            $row['client_secret_hash'] !== null
                ? (string) $row['client_secret_hash']
                : null,
            (bool) $row['active'],
        );
    }

    /** @return string[] */
    private function decodeList(string $json): array
    {
        $value = json_decode($json, true);

        return is_array($value)
            ? $this->cleanList($value)
            : [];
    }

    /** @param array<mixed> $values @return string[] */
    private function cleanList(array $values): array
    {
        $values = array_map(
            static fn ($value): string => trim((string) $value),
            $values
        );
        $values = array_filter(
            $values,
            static fn (string $value): bool => $value !== ''
        );

        return array_values(array_unique($values));
    }
}
