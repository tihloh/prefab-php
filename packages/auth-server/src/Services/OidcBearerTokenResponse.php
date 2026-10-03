<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Services;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

final class OidcBearerTokenResponse extends BearerTokenResponse
{
    public function __construct(private IdTokenIssuer $idTokens) {}

    protected function getExtraParams(
        AccessTokenEntityInterface $accessToken
    ): array {
        $idToken = $this->idTokens->issue($accessToken);

        return $idToken === null
            ? []
            : ['id_token' => $idToken];
    }
}
