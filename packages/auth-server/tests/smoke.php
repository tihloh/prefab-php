<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\AuthServer\Contracts\IdentityClaimsProviderInterface;
use Tihloh\Prefab\AuthServer\Repositories\PdoClientRepository;
use Tihloh\Prefab\AuthServer\Services\OidcServerManager;
use Tihloh\Prefab\AuthServer\Services\SchemaInstaller;

final class SmokeUser implements AuthenticatableUserInterface
{
    public function authId(): int|string { return 1; }
    public function authPasswordHash(): ?string { return null; }
    public function authIsActive(): bool { return true; }
}

final class SmokeUsers implements AuthUserProviderInterface
{
    private SmokeUser $user;

    public function __construct()
    {
        $this->user = new SmokeUser();
    }

    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface
    {
        return $this->user;
    }

    public function findById(int|string $id): ?AuthenticatableUserInterface
    {
        return (string) $id === '1' ? $this->user : null;
    }
}

final class SmokeSession implements AuthSessionStoreInterface
{
    private int|string|null $id = null;

    public function put(int|string $userId): void { $this->id = $userId; }
    public function userId(): int|string|null { return $this->id; }
    public function forget(): void { $this->id = null; }
}

final class SmokeClaims implements IdentityClaimsProviderInterface
{
    public function subject(int|string $userId): string
    {
        return 'user-' . $userId;
    }

    public function claims(int|string $userId, array $scopes): array
    {
        $claims = [];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = 'Demo User';
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = 'demo@example.test';
            $claims['email_verified'] = true;
        }

        return $claims;
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
SchemaInstaller::install($pdo);

$key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
assert($key !== false);

$privatePem = '';
assert(openssl_pkey_export($key, $privatePem));
$details = openssl_pkey_get_details($key);
assert(is_array($details) && isset($details['key']));
$publicPem = (string) $details['key'];

$dir = sys_get_temp_dir() . '/prefab-auth-server-' . bin2hex(random_bytes(4));
assert(mkdir($dir, 0700));
$privatePath = $dir . '/private.key';
$publicPath = $dir . '/public.key';
file_put_contents($privatePath, $privatePem);
file_put_contents($publicPath, $publicPem);
chmod($privatePath, 0600);
chmod($publicPath, 0644);

$auth = new AuthManager(new SmokeUsers(), new SmokeSession());
$result = $auth->login(new SmokeUser());
assert($result->success === true);

$server = OidcServerManager::fromPdo(
    $pdo,
    $auth,
    new SmokeClaims(),
    [
        'issuer' => 'https://accounts.example.test',
        'private_key' => $privatePath,
        'public_key' => $publicPath,
        'encryption_key' => base64_encode(random_bytes(32)),
    ],
);

$clients = $server->clients();
assert($clients instanceof PdoClientRepository);

$client = $clients->register(
    'Demo Client',
    ['https://client.example.test/callback'],
    ['openid', 'profile', 'email'],
    false,
);

assert($client->clientSecret === null);

$discovery = $server->discovery();
assert($discovery['issuer'] === 'https://accounts.example.test');
assert($discovery['authorization_endpoint'] === 'https://accounts.example.test/oauth/authorize');
assert(in_array('S256', $discovery['code_challenge_methods_supported'], true));

$jwks = $server->jwks();
assert(($jwks['keys'][0]['kty'] ?? null) === 'RSA');
assert(($jwks['keys'][0]['alg'] ?? null) === 'RS256');

$verifier = str_repeat('A', 64);
$challenge = rtrim(
    strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'),
    '='
);

$params = [
    'response_type' => 'code',
    'client_id' => $client->clientId,
    'redirect_uri' => 'https://client.example.test/callback',
    'scope' => 'openid profile email',
    'state' => 'state-123',
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
];

$request = (new ServerRequest(
    'GET',
    'https://accounts.example.test/oauth/authorize?' . http_build_query($params)
))->withQueryParams($params);

$authorization = $server->validateAuthorizationRequest($request);
assert($server->hasConsent($authorization) === false);

$response = $server->completeAuthorizationRequest(
    $authorization,
    true,
    new Response(),
);
assert($response->getStatusCode() === 302);
assert($server->hasConsent($authorization) === true);

$location = $response->getHeaderLine('Location');
parse_str((string) parse_url($location, PHP_URL_QUERY), $callback);
assert(isset($callback['code']));
assert(($callback['state'] ?? null) === 'state-123');

$tokenBody = [
    'grant_type' => 'authorization_code',
    'client_id' => $client->clientId,
    'redirect_uri' => 'https://client.example.test/callback',
    'code' => $callback['code'],
    'code_verifier' => $verifier,
];

$tokenRequest = (new ServerRequest(
    'POST',
    'https://accounts.example.test/oauth/token'
))->withParsedBody($tokenBody);

$tokenResponse = $server->respondToAccessTokenRequest(
    $tokenRequest,
    new Response(),
);

assert($tokenResponse->getStatusCode() === 200);
$tokens = json_decode((string) $tokenResponse->getBody(), true);
assert(is_array($tokens));
assert(isset($tokens['access_token']));
assert(isset($tokens['refresh_token']));
assert(isset($tokens['id_token']));

$userInfoRequest = (new ServerRequest(
    'GET',
    'https://accounts.example.test/oauth/userinfo'
))->withHeader('Authorization', 'Bearer ' . $tokens['access_token']);

$userInfo = $server->userInfo($userInfoRequest);
assert($userInfo['sub'] === 'user-1');
assert($userInfo['name'] === 'Demo User');
assert($userInfo['email'] === 'demo@example.test');
assert($userInfo['email_verified'] === true);

@unlink($privatePath);
@unlink($publicPath);
@rmdir($dir);

echo "Prefab Auth Server OIDC smoke OK\n";
