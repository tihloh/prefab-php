<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Services;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResourceServer;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\AuthServer\Contracts\ConsentStoreInterface;
use Tihloh\Prefab\AuthServer\Contracts\IdentityClaimsProviderInterface;
use Tihloh\Prefab\AuthServer\Entities\UserEntity;
use Tihloh\Prefab\AuthServer\Repositories\PdoAccessTokenRepository;
use Tihloh\Prefab\AuthServer\Repositories\PdoAuthCodeRepository;
use Tihloh\Prefab\AuthServer\Repositories\PdoClientRepository;
use Tihloh\Prefab\AuthServer\Repositories\PdoRefreshTokenRepository;
use Tihloh\Prefab\AuthServer\Repositories\ScopeRepository;
use Tihloh\Prefab\AuthServer\Stores\PdoConsentStore;

final class OidcServerManager
{
    private AuthorizationServer $authorizationServer;
    private ResourceServer $resourceServer;
    private DiscoveryDocument $discovery;
    private JwkSetService $jwks;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        private AuthManager $auth,
        private IdentityClaimsProviderInterface $claims,
        private ClientRepositoryInterface $clients,
        private AccessTokenRepositoryInterface $accessTokens,
        private ScopeRepositoryInterface $scopes,
        private AuthCodeRepositoryInterface $authCodes,
        private RefreshTokenRepositoryInterface $refreshTokens,
        private ConsentStoreInterface $consents,
        private array $config,
    ) {
        foreach ([
            'issuer',
            'private_key',
            'public_key',
            'encryption_key',
        ] as $required) {
            if (!isset($config[$required]) || $config[$required] === '') {
                throw new RuntimeException(
                    "Missing Auth Server configuration: {$required}"
                );
            }
        }

        $issuer = rtrim((string) $config['issuer'], '/');
        $keyId = isset($config['key_id'])
            ? (string) $config['key_id']
            : null;

        $idTokens = new IdTokenIssuer(
            $issuer,
            $claims,
            (string) $config['private_key'],
            (string) $config['public_key'],
            isset($config['private_key_passphrase'])
                ? (string) $config['private_key_passphrase']
                : null,
            (string) ($config['id_token_ttl'] ?? 'PT10M'),
            $keyId,
        );

        $this->authorizationServer = new AuthorizationServer(
            $clients,
            $accessTokens,
            $scopes,
            (string) $config['private_key'],
            (string) $config['encryption_key'],
            new OidcBearerTokenResponse($idTokens),
        );

        $grant = new AuthCodeGrant(
            $authCodes,
            $refreshTokens,
            new DateInterval((string) ($config['auth_code_ttl'] ?? 'PT10M')),
        );
        $grant->setRefreshTokenTTL(
            new DateInterval((string) ($config['refresh_token_ttl'] ?? 'P30D'))
        );

        $this->authorizationServer->enableGrantType(
            $grant,
            new DateInterval((string) ($config['access_token_ttl'] ?? 'PT1H')),
        );

        $this->resourceServer = new ResourceServer(
            $accessTokens,
            (string) $config['public_key'],
        );

        $scopeNames = $scopes instanceof ScopeRepository
            ? array_keys($scopes->definitions())
            : ['openid', 'profile', 'email'];

        $this->discovery = new DiscoveryDocument($issuer, $scopeNames);
        $this->jwks = new JwkSetService(
            (string) $config['public_key'],
            $keyId,
        );
    }

    /**
     * Build the default standalone/PDO implementation.
     *
     * @param array<string,mixed> $config
     * @param array<string,string> $scopeDefinitions
     */
    public static function fromPdo(
        PDO $pdo,
        AuthManager $auth,
        IdentityClaimsProviderInterface $claims,
        array $config,
        array $scopeDefinitions = [],
    ): self {
        return new self(
            $auth,
            $claims,
            new PdoClientRepository($pdo),
            new PdoAccessTokenRepository($pdo),
            new ScopeRepository($scopeDefinitions),
            new PdoAuthCodeRepository($pdo),
            new PdoRefreshTokenRepository($pdo),
            new PdoConsentStore($pdo),
            $config,
        );
    }

    public function validateAuthorizationRequest(
        ServerRequestInterface $request
    ): AuthorizationRequestInterface {
        $query = $request->getQueryParams();
        $method = (string) ($query['code_challenge_method'] ?? '');

        if ($method !== '' && $method !== 'S256') {
            throw OAuthServerException::invalidRequest(
                'code_challenge_method',
                'Prefab Auth Server supports S256 PKCE only.'
            );
        }

        $requestedScopes = preg_split(
            '/\s+/',
            trim((string) ($query['scope'] ?? ''))
        ) ?: [];

        if (!in_array('openid', $requestedScopes, true)) {
            throw OAuthServerException::invalidRequest(
                'scope',
                'The openid scope is required.'
            );
        }

        return $this->authorizationServer
            ->validateAuthorizationRequest($request);
    }

    public function completeAuthorizationRequest(
        AuthorizationRequestInterface $request,
        bool $approved,
        ResponseInterface $response
    ): ResponseInterface {
        $userId = $this->auth->id();

        if ($userId === null) {
            throw new RuntimeException(
                'The user must be authenticated before completing authorization.'
            );
        }

        $request->setUser(new UserEntity((string) $userId));
        $request->setAuthorizationApproved($approved);

        if ($approved) {
            $this->consents->grant(
                $userId,
                $request->getClient()->getIdentifier(),
                $this->scopeNames($request->getScopes()),
            );
        }

        return $this->authorizationServer
            ->completeAuthorizationRequest($request, $response);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->authorizationServer
            ->respondToAccessTokenRequest($request, $response);
    }

    public function validateResourceRequest(
        ServerRequestInterface $request
    ): ServerRequestInterface {
        return $this->resourceServer
            ->validateAuthenticatedRequest($request);
    }

    /** @return array<string,mixed> */
    public function userInfo(ServerRequestInterface $request): array
    {
        $request = $this->validateResourceRequest($request);
        $userId = $request->getAttribute('oauth_user_id');
        $scopes = $request->getAttribute('oauth_scopes', []);

        if ($userId === null || $userId === '') {
            throw new RuntimeException(
                'The access token is not associated with a user.'
            );
        }

        $scopeNames = is_array($scopes)
            ? array_values(array_map('strval', $scopes))
            : [];

        return [
            'sub' => $this->claims->subject((string) $userId),
            ...$this->claims->claims((string) $userId, $scopeNames),
        ];
    }

    /** @return array<string,mixed> */
    public function discovery(): array
    {
        return $this->discovery->document();
    }

    /** @return array<string,mixed> */
    public function jwks(): array
    {
        return $this->jwks->document();
    }

    public function hasConsent(
        AuthorizationRequestInterface $request
    ): bool {
        $userId = $this->auth->id();

        return $userId !== null && $this->consents->hasConsent(
            $userId,
            $request->getClient()->getIdentifier(),
            $this->scopeNames($request->getScopes()),
        );
    }

    public function clients(): ClientRepositoryInterface
    {
        return $this->clients;
    }

    public function consents(): ConsentStoreInterface
    {
        return $this->consents;
    }

    /** @param array<mixed> $scopes @return string[] */
    private function scopeNames(array $scopes): array
    {
        return array_map(
            static fn ($scope): string => $scope->getIdentifier(),
            $scopes
        );
    }
}
