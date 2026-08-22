# Tihloh Prefab Auth

Framework-independent authentication for PHP applications.

## Responsibilities

- password login/logout
- current authenticated user/session lookup
- custom user-provider integration
- social/OAuth provider contracts
- password reset/social-account storage schema
- structured log payloads for auth operations
- optional Bootstrap UI recipes

Auth does not own the project user table. Any user model can integrate by implementing `AuthenticatableUserInterface` and being exposed through `AuthUserProviderInterface`.

## Standalone example

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

Implement `SocialProviderInterface` directly or through a framework adapter such as Laravel Socialite. Social providers normalize external identities into `SocialIdentity`.

## UI recipes

Copy and customize files under `examples/bootstrap/`. They are examples only and are never rendered automatically by the Auth prefab.
