# Prefab Auth

**Prefab Auth** provides framework-independent authentication for PHP applications while allowing the host project to keep ownership of its users, database and application structure.

> Use only password authentication if that is all you need. Add social authentication and other integrations when the application grows.

Auth does not own the project's user table. Any compatible user source can be connected through the Auth contracts, including Prefab Users or a project/framework adapter.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

```bash
composer require tihloh/prefab-auth
```

## Responsibilities

Prefab Auth handles authentication concerns such as:

- password login and logout;
- current authenticated user/session lookup;
- custom user-provider integration;
- social sign-in;
- social account linking and unlinking;
- OAuth state validation;
- password-reset/social-account storage support;
- structured authentication log payloads;
- optional Bootstrap UI recipes.

It deliberately does not require the application to replace its existing user model or database design.

---

# 1. Password authentication

A minimal standalone setup uses a user provider and session store:

```php
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;

$auth = new AuthManager(
    $userProvider,
    new NativeSessionStore(),
);
```

Attempt a login:

```php
$result = $auth->attempt(
    $_POST['identifier'] ?? '',
    $_POST['password'] ?? '',
    [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ],
);

if ($result->success) {
    // Authentication succeeded.
}
```

Authentication returns a result instead of forcing HTML, JSON or redirects. The host application decides how the result is presented.

---

# 2. Current authenticated user

The common current-user API is intentionally small:

```php
$auth->check();
$auth->id();
$auth->user();
$auth->logout();
```

Typical use:

```php
if ($auth->check()) {
    $user = $auth->user();
    echo 'Welcome ' . $user->name;
}
```

Logout:

```php
$auth->logout();
```

The session implementation remains replaceable through the Auth session abstraction.

---

# 3. User-provider ownership

Prefab Auth authenticates users; it does not require its own `users` table.

Conceptually:

```text
Your users / employees / accounts
              ↓
AuthUserProviderInterface
              ↓
         Prefab Auth
```

A compatible user object implements `AuthenticatableUserInterface`. This allows an existing project model, Prefab Users, Laravel model adapter, or another implementation to participate without making Auth dependent on it.

This separation is important: authentication can change without forcing the project to redesign its business/user data.

---

# 4. Authentication result and logging

Authentication operations can produce structured log information:

```php
$result = $auth->attempt($identifier, $password);

if ($result->success) {
    $logs?->record($result->log);
}
```

This allows Prefab Logs or project-specific logging to store authentication activity without Auth requiring a logging package.

Useful request context may be supplied during authentication:

```php
[
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]
```

---

# 5. Social authentication

Social authentication is optional. Applications that do not need OAuth/OIDC can ignore this section entirely.

Providers are registered through `SocialProviderRegistry`:

```php
$registry->register($googleProvider);
$registry->register($githubProvider);
```

A provider may implement the Prefab social-provider contract directly or be wrapped with `CallbackSocialProvider`. This allows integration with libraries such as Laravel Socialite, `league/oauth2-client`, or another OAuth/OIDC SDK without coupling Prefab Auth to that library.

Create the social manager:

```php
$social = new SocialAuthManager(
    $registry,
    new PdoSocialAccountStore($pdo),
    new NativeSessionSocialStateStore(),
    $userProvider,
    $socialUserResolver,
    $auth,
);
```

---

# 6. Starting social sign-in

Request the provider authorization URL:

```php
$url = $social->authorizationUrl('google');

header('Location: ' . $url);
exit;
```

Flow:

```text
Your application
      ↓
authorizationUrl('google')
      ↓
Google / provider
      ↓
user approves sign-in
      ↓
callback URL
      ↓
Prefab Auth callback()
```

OAuth state is validated as part of the social authentication flow.

---

# 7. Handling the social callback

```php
$result = $social->callback(
    'google',
    $_GET,
    [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ],
);

if ($result->success) {
    // The user is authenticated.
}
```

The host application remains responsible for choosing the HTTP response or redirect.

---

# 8. Resolving a new social user

When the provider account has not been linked previously, `SocialUserResolverInterface` decides what happens.

A project may choose to:

- find an existing user by verified email;
- create a new local user;
- require registration or approval;
- reject the sign-in.

This keeps application-specific account policy outside the generic authentication engine.

---

# 9. Social account management

Inspect available providers:

```php
$social->providers();
```

Inspect accounts linked to a user:

```php
$social->accountsForUser($userId);
```

Unlink an account:

```php
$social->unlink($userId, 'google');
```

An application can therefore build its own "Connected Accounts" screen without Auth forcing a UI.

---

# 10. Database independence

Auth is not tied to Prefab Database. Storage implementations may use plain PDO, Prefab's database abstraction, or another compatible adapter where supported.

Conceptually:

```text
Project/framework database
          ↓
compatible Auth storage/provider
          ↓
       Prefab Auth
```

This keeps Auth usable as an independent Composer package.

---

# 11. Cooperation with Prefab Users

Prefab Users can provide the user-provider capability used by Auth.

A full Prefab application can therefore conceptually resolve:

```text
Prefab Users
     ↓
user provider
     ↓
Prefab Auth
     ↓
current user
```

Neither package needs to become a hard dependency of the other.

Explicit configuration always remains available when automatic cooperation is not desired.

---

# 12. Cooperation with Prefab Logs

Authentication events can be represented as structured log payloads.

```text
login attempt
     ↓
Prefab Auth
     ↓
structured auth event
     ↓
Prefab Logs / custom logger
```

This allows technical audit history without coupling authentication to one logging implementation.

---

# 13. Cooperation with Prefab Permissions

Auth answers:

> Who is the current user?

Permissions answers:

> Is that user allowed to perform this action?

They remain separate responsibilities:

```text
Auth
 ↓
current user
 ↓
Permissions
 ↓
can('documents.approve')
```

Keeping authentication and authorization separate makes both modules easier to replace and reuse.

---

# 14. Cooperation with Prefab Routes

Routes can protect an endpoint through middleware or route integration metadata while Auth supplies the current-user capability.

Conceptually:

```text
GET /admin
    ↓
Prefab Routes
    ↓
auth middleware
    ↓
Prefab Auth
    ↓
controller
```

The modules remain individually installable.

---

# 15. HTTP integration

Prefab Auth deliberately does not force a router or response system.

A login endpoint can be implemented with any router:

```php
$routes->post('/login', function () use ($auth) {
    return $auth->attempt(
        $_POST['email'] ?? '',
        $_POST['password'] ?? '',
    );
});
```

The same AuthManager can therefore be used by plain PHP, Prefab Routes, Laravel adapters, APIs or other application architectures.

---

# 16. UI recipes

Prefab Auth contains optional Bootstrap examples under:

```text
examples/bootstrap/
```

These are recipes, not runtime dependencies.

You may copy and customize them for login, social sign-in and related screens. Auth never automatically injects Bootstrap, HTML, CSS or JavaScript into the host application.

---

# 17. Security responsibilities

Prefab Auth handles authentication mechanics, but the host application still owns its overall security policy. Applications should use HTTPS, secure session/cookie settings, CSRF protection for state-changing browser requests, appropriate OAuth redirect URIs, secure secrets/configuration and suitable rate limiting around login/reset endpoints.

Authentication should not be confused with authorization: successfully signing in does not automatically grant permission to every application action.

---

# 18. Practical small application

For a small project:

```php
$auth = new AuthManager(
    $userProvider,
    new NativeSessionStore(),
);

$result = $auth->attempt($email, $password);

if ($result->success) {
    echo 'Logged in';
}
```

Nothing related to social providers, permissions or logging is required.

---

# 19. Practical larger application

A larger application may combine independent capabilities:

```text
Prefab Database
      ↓
Prefab Users
      ↓
Prefab Auth
      ↓
Prefab Permissions
      ↓
Prefab Routes
      ↓
application controller

Prefab Logs observes activity where configured
```

Each module remains independently configurable and replaceable.

---

# 20. API quick reference

Common AuthManager operations:

| API | Purpose |
|---|---|
| `attempt()` | Authenticate credentials |
| `check()` | Determine whether a user is authenticated |
| `id()` | Return the authenticated user's ID |
| `user()` | Return the authenticated user |
| `logout()` | End the current authenticated session |

Common social operations:

| API | Purpose |
|---|---|
| `authorizationUrl()` | Begin provider authentication |
| `callback()` | Complete provider authentication |
| `providers()` | List configured providers |
| `accountsForUser()` | List a user's linked social accounts |
| `unlink()` | Remove a social-account link |

---

# 21. Design philosophy

Prefab Auth separates authentication from user ownership, routing, authorization, logging and presentation.

```text
Small application
      ↓
password login + session

Application grows
      ↓
custom provider + logging

Application grows further
      ↓
social authentication

Large modular application
      ↓
Users + Auth + Permissions + Routes + Logs
```

The simple authentication API remains valid as the rest of the application grows.

That is the goal of Prefab Auth: **authentication that can stand alone, cooperate automatically when useful, and never require the application to become a specific framework.**
