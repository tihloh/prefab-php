# Tihloh Prefab Auth

Framework-independent authentication for PHP applications.

## Responsibilities

- password login/logout
- current authenticated user/session lookup
- custom user-provider integration
- complete social sign-in flow
- social account linking/unlinking
- OAuth state validation
- password reset/social-account storage schema
- structured log payloads for auth operations
- optional Bootstrap UI recipes

Auth does not own the project user table. Any user model can integrate by implementing `AuthenticatableUserInterface` and being exposed through `AuthUserProviderInterface`.

## Standalone password example

```php
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;

$auth = new AuthManager($userProvider, new NativeSessionStore());

$result = $auth->attempt($_POST['identifier'], $_POST['password'], [
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);

if ($result->success) {
    $logs?->record($result->log);
}
```

## Current-user API

```php
$auth->check();
$auth->id();
$auth->user();
$auth->logout();
```

## Social sign-in

Register one or more providers in `SocialProviderRegistry`. A provider may be implemented directly or wrapped through `CallbackSocialProvider`, allowing Laravel Socialite, league/oauth2-client, or another OAuth/OIDC SDK to be used without coupling Prefab Auth to that library.

```php
$registry->register($googleProvider);
$registry->register($githubProvider);

$social = new SocialAuthManager(
    $registry,
    new PdoSocialAccountStore($pdo),
    new NativeSessionSocialStateStore(),
    $userProvider,
    $socialUserResolver,
    $auth,
);
```

Start sign-in:

```php
header('Location: ' . $social->authorizationUrl('google'));
exit;
```

Handle callback:

```php
$result = $social->callback('google', $_GET, [
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);

if ($result->success) {
    $logs?->record($result->log);
}
```

`SocialUserResolverInterface` controls what happens when a social account is not linked yet. The host project may find an existing user by verified email, create a new user, require registration, or reject the sign-in.

Management data is available through:

```php
$social->providers();
$social->accountsForUser($userId);
$social->unlink($userId, 'google');
```

## UI recipes

Copy and customize files under `examples/bootstrap/`. They are examples only and are never rendered automatically by the Auth prefab.
