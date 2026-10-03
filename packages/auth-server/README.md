# Prefab Auth Server

**Prefab Auth Server** lets an authenticated PHP application become an OAuth 2.0 / OpenID Connect identity provider.

It is the provider-side counterpart to Prefab Auth social/external sign-in:

```text
App 1
├── Prefab Auth
└── Prefab Auth Server
        │
        │ OAuth 2.0 + OpenID Connect
        ▼
App 2
└── "Continue with App 1"
```

The module does not own the application's user table. Prefab Auth still decides who is signed in, while the host application supplies the identity claims it is willing to share.

## Requirements

- PHP 8.1+
- Prefab Auth
- OpenSSL
- a PSR-7 request/response implementation
- PDO only when using the built-in repositories

OAuth protocol mechanics are provided by `league/oauth2-server`. Prefab adds the application-facing integration, persistence, consent, OIDC identity layer, discovery, JWKS and UserInfo behavior.

## Installation

```bash
composer require tihloh/prefab-auth-server
```

## Responsibilities

Prefab Auth Server owns:

- OAuth client registration/storage;
- Authorization Code flow;
- PKCE (S256);
- authorization and refresh tokens;
- OIDC ID tokens signed with RS256;
- discovery metadata;
- JWKS;
- UserInfo;
- scope filtering;
- user consent storage.

The application still owns:

- login UI and authentication;
- user/profile storage;
- the authorization/consent page presentation;
- which claims each scope exposes;
- application-specific permissions.

## 1. Install the schema

For built-in PDO storage:

```php
use Tihloh\Prefab\AuthServer\Services\SchemaInstaller;

SchemaInstaller::install($pdo);
```

A MySQL/MariaDB migration is also included under `migrations/`.

## 2. Generate keys

Keep the private key outside the public web root.

```bash
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

The last value is the OAuth authorization-code encryption key.

## 3. Provide identity claims

```php
use Tihloh\Prefab\AuthServer\Contracts\IdentityClaimsProviderInterface;

final class AppClaims implements IdentityClaimsProviderInterface
{
    public function subject(int|string $userId): string
    {
        return (string) $userId;
    }

    public function claims(int|string $userId, array $scopes): array
    {
        $user = loadUser($userId);

        $claims = [];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
            $claims['preferred_username'] = $user->username;
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified;
        }

        return $claims;
    }
}
```

The provider can read from Prefab Users, Eloquent, a CodeIgniter model, a legacy table or another source.

## 4. Create the server

```php
use Tihloh\Prefab\AuthServer\Services\OidcServerManager;

$server = OidcServerManager::fromPdo(
    $pdo,
    $auth,
    new AppClaims(),
    [
        'issuer' => 'https://accounts.example.com',
        'private_key' => '/secure/private.key',
        'public_key' => '/secure/public.key',
        'encryption_key' => $_ENV['OAUTH_ENCRYPTION_KEY'],
    ],
);
```

## 5. Register App 2

```php
$client = $server->clients()->register(
    name: 'App 2',
    redirectUris: [
        'https://app2.example.com/auth/app1/callback',
    ],
    allowedScopes: ['openid', 'profile', 'email'],
);
```

For a confidential web application, `clientSecret` is returned once at registration. Only its password hash is stored.

For a public/mobile client:

```php
$client = $server->clients()->register(
    name: 'Mobile App',
    redirectUris: ['com.example.app:/oauth/callback'],
    confidential: false,
);
```

Public clients must use PKCE. Prefab advertises and accepts S256 only.

## 6. HTTP endpoints

The host router maps these paths; Auth Server does not force a routing framework.

```text
/.well-known/openid-configuration
/oauth/authorize
/oauth/token
/oauth/userinfo
/oauth/jwks
```

Discovery:

```php
returnJson($server->discovery());
```

JWKS:

```php
returnJson($server->jwks());
```

Authorization endpoint:

```php
$authorization = $server->validateAuthorizationRequest($request);

// If not already approved, render your own consent page.
// $server->hasConsent($authorization) can skip repeat consent.

return $server->completeAuthorizationRequest(
    $authorization,
    approved: true,
    response: $response,
);
```

Token endpoint:

```php
return $server->respondToAccessTokenRequest($request, $response);
```

UserInfo:

```php
returnJson($server->userInfo($request));
```

## 7. Default scopes

```text
openid   authenticated identity
profile  basic profile claims
email    email + email_verified
```

Applications may add custom scopes such as `organization`, `office` or `employee` by supplying scope definitions and returning matching claims.

Do not use provider-side roles as automatic permissions in the client application. App 1 proves identity and shares approved factual claims; App 2 still owns its authorization rules.

## 8. Current MVP boundary

The first implementation intentionally supports:

```text
Authorization Code
+ PKCE S256
+ Refresh Tokens
+ OIDC ID Token
+ UserInfo
+ Discovery
+ JWKS
+ Consent
```

It does not enable password grant, implicit grant, client credentials, dynamic client registration or device flow.

OIDC `nonce` is intentionally rejected in this initial version instead of accepting it without returning it in the ID token. Nonce support is the next protocol-hardening step.

## Framework interoperability

```text
Prefab Users ───────┐
Laravel/Eloquent ───┤
CodeIgniter model ──┼── Prefab Auth ── Prefab Auth Server
Legacy/custom ──────┘
```

Only Prefab Auth is required as the authentication boundary. Prefab Users remains optional.

That keeps the original Prefab rule intact: **standalone first, framework-independent, explicit configuration wins, better together when modules are combined.**
